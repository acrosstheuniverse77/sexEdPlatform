<?php

namespace App\Services\Community;

use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPreScreenDecision;
use App\Models\CommunityPost;
use App\Models\CommunityPostVersion;
use App\Models\Connector;
use App\Models\ConnectorMembership;
use App\Models\Seminar;
use App\Models\User;
use App\Notifications\Community\CommunityPostEscalatedNotification;
use App\Notifications\Community\CommunityPostPendingReviewNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityPostService
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunitySpaceService $spaces,
        private readonly CommunityContentPreScreeningService $preScreening,
    ) {
    }

    public function create(User $author, Connector $connector, array $payload): CommunityPost
    {
        $this->access->abortUnlessCanCreatePost($author, $connector);
        $this->abortUnlessSeminarBelongsToConnector($connector, $payload['seminar_id'] ?? null);

        return DB::transaction(function () use ($author, $connector, $payload): CommunityPost {
            $space = $this->spaces->spaceForConnector($connector);
            $result = $this->preScreening->screenPost($payload);

            if ($result->decision === CommunityPreScreenDecision::BlockWithFeedback) {
                throw ValidationException::withMessages([
                    'body' => $result->message ?? 'Revise the content before posting.',
                ]);
            }

            $status = $this->statusForDecision($result->decision);

            $post = CommunityPost::query()->create([
                'community_space_id' => $space->id,
                'connector_id' => $connector->id,
                'author_id' => $author->id,
                'post_type' => $payload['post_type'],
                'seminar_id' => $payload['seminar_id'] ?? null,
                'status' => $status,
                'title' => $payload['title'],
                'body' => $payload['body'],
                'resource_url' => $payload['resource_url'] ?? null,
                'prescreen_decision' => $result->decision->value,
                'prescreen_flags' => $result->flags,
                'submitted_at' => now(),
                'published_at' => $status === CommunityPostStatus::Published ? now() : null,
                'published_by' => $status === CommunityPostStatus::Published ? $author->id : null,
                'escalated_at' => $status === CommunityPostStatus::Escalated ? now() : null,
                'escalated_by' => $status === CommunityPostStatus::Escalated ? $author->id : null,
            ]);

            $this->recordVersion($post, $author);
            $this->notifyForInitialStatus($post->fresh(['connector', 'space']));

            return $post->fresh(['space', 'connector', 'author', 'versions']);
        });
    }

    public function update(User $actor, CommunityPost $post, array $payload): CommunityPost
    {
        abort_unless($this->access->canEditPost($actor, $post), 403);
        $post->loadMissing('connector');
        $this->abortUnlessSeminarBelongsToConnector($post->connector, $payload['seminar_id'] ?? null);

        return DB::transaction(function () use ($actor, $post, $payload): CommunityPost {
            $result = $this->preScreening->screenPost($payload);

            if ($result->decision === CommunityPreScreenDecision::BlockWithFeedback) {
                throw ValidationException::withMessages([
                    'body' => $result->message ?? 'Revise the content before posting.',
                ]);
            }

            $status = $this->statusForDecision($result->decision);

            $post->forceFill([
                'post_type' => $payload['post_type'],
                'seminar_id' => $payload['seminar_id'] ?? null,
                'status' => $status,
                'title' => $payload['title'],
                'body' => $payload['body'],
                'resource_url' => $payload['resource_url'] ?? null,
                'prescreen_decision' => $result->decision->value,
                'prescreen_flags' => $result->flags,
                'submitted_at' => now(),
                'published_at' => $status === CommunityPostStatus::Published ? ($post->published_at ?? now()) : null,
                'published_by' => $status === CommunityPostStatus::Published ? ($post->published_by ?? $actor->id) : null,
                'escalated_at' => $status === CommunityPostStatus::Escalated ? now() : $post->escalated_at,
                'escalated_by' => $status === CommunityPostStatus::Escalated ? $actor->id : $post->escalated_by,
            ])->save();

            $this->recordVersion($post->fresh(), $actor);
            $this->notifyForInitialStatus($post->fresh(['connector', 'space']));

            return $post->fresh(['space', 'connector', 'author', 'versions']);
        });
    }

    public function recordVersion(CommunityPost $post, ?User $editor): CommunityPostVersion
    {
        $nextVersion = ((int) $post->versions()->max('version_number')) + 1;

        return $post->versions()->create([
            'edited_by' => $editor?->id,
            'version_number' => $nextVersion,
            'title' => $post->title,
            'body' => $post->body,
            'resource_url' => $post->resource_url,
            'post_type' => $post->post_type?->value ?? $post->post_type,
            'prescreen_decision' => $post->prescreen_decision,
            'prescreen_flags' => $post->prescreen_flags,
        ]);
    }

    private function statusForDecision(CommunityPreScreenDecision $decision): CommunityPostStatus
    {
        return match ($decision) {
            CommunityPreScreenDecision::Allow => CommunityPostStatus::Published,
            CommunityPreScreenDecision::PendingReview => CommunityPostStatus::PendingReview,
            CommunityPreScreenDecision::AutoHideAndEscalate => CommunityPostStatus::Escalated,
            CommunityPreScreenDecision::BlockWithFeedback => CommunityPostStatus::Draft,
        };
    }

    private function abortUnlessSeminarBelongsToConnector(Connector $connector, mixed $seminarId): void
    {
        if ($seminarId === null || $seminarId === '') {
            return;
        }

        abort_unless(
            Seminar::query()
                ->whereKey($seminarId)
                ->where('connector_id', $connector->id)
                ->exists(),
            422
        );
    }

    private function notifyForInitialStatus(CommunityPost $post): void
    {
        if ($post->status === CommunityPostStatus::PendingReview) {
            $this->connectorModerators($post, 'community.approve_posts')->each(
                fn (User $moderator) => $moderator->notify(new CommunityPostPendingReviewNotification($post))
            );
        }

        if ($post->status === CommunityPostStatus::Escalated) {
            $this->platformAdmins()->each(
                fn (User $admin) => $admin->notify(new CommunityPostEscalatedNotification($post, 'Automated community safety escalation.'))
            );
        }
    }

    private function connectorModerators(CommunityPost $post, string $permission)
    {
        return ConnectorMembership::query()
            ->with('user')
            ->where('connector_id', $post->connector_id)
            ->where('status', 'active')
            ->whereHas('role.permissions', fn ($query) => $query->where('permission_key', $permission))
            ->get()
            ->pluck('user')
            ->filter(fn (?User $user) => $user && ! $user->isMinorForCommunityFeed());
    }

    private function platformAdmins()
    {
        return User::role('admin')->get();
    }
}
