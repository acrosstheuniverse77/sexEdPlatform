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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommunityPostService
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunitySpaceService $spaces,
        private readonly CommunityContentPreScreeningService $preScreening,
    ) {}

    public function create(User $author, Connector $connector, array $payload): CommunityPost
    {
        $this->access->abortUnlessCanCreatePost($author, $connector);
        $this->abortUnlessSeminarBelongsToConnector($connector, $payload['seminar_id'] ?? null);

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($author, $connector, $payload, &$storedPaths): CommunityPost {
                $space = $this->spaces->spaceForConnector($connector);
                $topic = $this->resolveTopic($payload);
                $payload['title'] = $topic;
                $payload['resource_url'] = null;
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
                    'topic' => $topic,
                    'seminar_id' => $payload['seminar_id'] ?? null,
                    'status' => $status,
                    'title' => $topic,
                    'body' => $payload['body'],
                    'resource_url' => null,
                    'prescreen_decision' => $result->decision->value,
                    'prescreen_flags' => $result->flags,
                    'submitted_at' => now(),
                    'published_at' => $status === CommunityPostStatus::Published ? now() : null,
                    'published_by' => $status === CommunityPostStatus::Published ? $author->id : null,
                    'escalated_at' => $status === CommunityPostStatus::Escalated ? now() : null,
                    'escalated_by' => $status === CommunityPostStatus::Escalated ? $author->id : null,
                ]);

                $this->syncMedia($author, $post, $payload, $storedPaths);
                $this->recordVersion($post->fresh(), $author);
                $this->notifyForInitialStatus($post->fresh(['connector', 'space']));

                return $post->fresh(['space', 'connector', 'author', 'versions', 'activeMedia']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    public function update(User $actor, CommunityPost $post, array $payload): CommunityPost
    {
        abort_unless($this->access->canEditPost($actor, $post), 403);
        $post->loadMissing('connector');
        $this->abortUnlessSeminarBelongsToConnector($post->connector, $payload['seminar_id'] ?? null);

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($actor, $post, $payload, &$storedPaths): CommunityPost {
                $topic = $this->resolveTopic($payload);
                $payload['title'] = $topic;
                $payload['resource_url'] = null;
                $result = $this->preScreening->screenPost($payload);

                if ($result->decision === CommunityPreScreenDecision::BlockWithFeedback) {
                    throw ValidationException::withMessages([
                        'body' => $result->message ?? 'Revise the content before posting.',
                    ]);
                }

                $status = $this->statusForDecision($result->decision);
                $post->forceFill([
                    'post_type' => $payload['post_type'],
                    'topic' => $topic,
                    'seminar_id' => $payload['seminar_id'] ?? null,
                    'status' => $status,
                    'title' => $topic,
                    'body' => $payload['body'],
                    'resource_url' => null,
                    'prescreen_decision' => $result->decision->value,
                    'prescreen_flags' => $result->flags,
                    'submitted_at' => now(),
                    'published_at' => $status === CommunityPostStatus::Published ? ($post->published_at ?? now()) : null,
                    'published_by' => $status === CommunityPostStatus::Published ? ($post->published_by ?? $actor->id) : null,
                    'escalated_at' => $status === CommunityPostStatus::Escalated ? now() : $post->escalated_at,
                    'escalated_by' => $status === CommunityPostStatus::Escalated ? $actor->id : $post->escalated_by,
                ])->save();

                $this->syncMedia($actor, $post, $payload, $storedPaths);
                $this->recordVersion($post->fresh(), $actor);
                $this->notifyForInitialStatus($post->fresh(['connector', 'space']));

                return $post->fresh(['space', 'connector', 'author', 'versions', 'activeMedia']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    public function recordVersion(CommunityPost $post, ?User $editor): CommunityPostVersion
    {
        $nextVersion = ((int) $post->versions()->max('version_number')) + 1;

        return $post->versions()->create([
            'edited_by' => $editor?->id,
            'version_number' => $nextVersion,
            'title' => $post->title,
            'body' => $post->body,
            'media_path' => $post->media_path,
            'media_type' => $post->media_type,
            'media_mime_type' => $post->media_mime_type,
            'media_original_name' => $post->media_original_name,
            'resource_url' => $post->resource_url,
            'post_type' => $post->post_type?->value ?? $post->post_type,
            'topic' => $post->topic,
            'prescreen_decision' => $post->prescreen_decision,
            'prescreen_flags' => $post->prescreen_flags,
        ]);
    }

    private function resolveTopic(array $payload): string
    {
        $choice = trim((string) ($payload['topic_choice'] ?? $payload['topic'] ?? ''));

        if ($choice === 'Other') {
            $choice = trim((string) ($payload['custom_topic'] ?? ''));
        }

        if ($choice === '') {
            $choice = trim((string) ($payload['title'] ?? 'Community post'));
        }

        return str($choice)->squish()->limit(100, '')->toString();
    }

    /**
     * @param  array<int, string>  $storedPaths
     */
    private function syncMedia(User $actor, CommunityPost $post, array $payload, array &$storedPaths): void
    {
        $activeMedia = $post->media()
            ->whereNull('removed_at')
            ->lockForUpdate()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $removeIds = collect($payload['remove_media_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($removeIds->diff($activeMedia->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'remove_media_ids' => 'The selected existing media item is not available for this post.',
            ]);
        }

        $retainedMedia = $activeMedia->reject(fn ($item) => $removeIds->contains($item->id))->values();
        $incomingImages = collect($payload['images'] ?? [])
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values();
        $incomingVideo = ($payload['video'] ?? null) instanceof UploadedFile
            ? $payload['video']
            : null;
        $legacyMedia = ($payload['media'] ?? null) instanceof UploadedFile
            ? $payload['media']
            : null;

        if ($legacyMedia) {
            $legacyMime = $legacyMedia->getMimeType() ?: $legacyMedia->getClientMimeType();

            if (str_starts_with($legacyMime, 'image/')) {
                $incomingImages->push($legacyMedia);
            } else {
                $incomingVideo = $legacyMedia;
            }
        }

        $finalTypes = $retainedMedia->pluck('media_type')
            ->merge($incomingImages->map(fn (): string => 'image'))
            ->when($incomingVideo, fn ($types) => $types->push('video'))
            ->unique()
            ->values();

        if ($finalTypes->count() > 1) {
            throw ValidationException::withMessages([
                $incomingVideo ? 'video' : 'images' => 'Choose images or one video, not both.',
            ]);
        }

        $finalCount = $retainedMedia->count() + $incomingImages->count() + ($incomingVideo ? 1 : 0);
        $finalType = $finalTypes->first();

        if ($finalType === 'image' && $finalCount > 6) {
            throw ValidationException::withMessages([
                'images' => 'A post can contain no more than six images.',
            ]);
        }

        if ($finalType === 'video' && $finalCount > 1) {
            throw ValidationException::withMessages([
                'video' => 'A post can contain only one video.',
            ]);
        }

        if ($removeIds->isNotEmpty()) {
            $post->media()
                ->whereNull('removed_at')
                ->whereKey($removeIds->all())
                ->update([
                    'removed_at' => now(),
                    'removed_by' => $actor->id,
                    'updated_at' => now(),
                ]);
        }

        foreach ($retainedMedia as $displayOrder => $item) {
            if ((int) $item->display_order !== $displayOrder) {
                $item->forceFill(['display_order' => $displayOrder])->save();
            }
        }

        $incoming = $incomingImages
            ->map(fn (UploadedFile $file): array => ['file' => $file, 'type' => 'image'])
            ->when($incomingVideo, fn ($items) => $items->push(['file' => $incomingVideo, 'type' => 'video']));

        foreach ($incoming as $offset => $item) {
            /** @var UploadedFile $file */
            $file = $item['file'];
            $path = $file->store('community-post-media/'.$post->connector_id.'/'.$post->id, 'local');

            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    $item['type'] === 'image' ? 'images' : 'video' => 'The media could not be saved. Please try again.',
                ]);
            }

            $storedPaths[] = $path;
            $post->media()->create([
                'uploaded_by' => $actor->id,
                'media_type' => $item['type'],
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'original_name' => str($file->getClientOriginalName())->squish()->limit(255, '')->toString(),
                'size_bytes' => $file->getSize(),
                'display_order' => $retainedMedia->count() + $offset,
            ]);
        }

        $firstMedia = $post->activeMedia()->first();
        $post->forceFill([
            'media_path' => $firstMedia?->path,
            'media_type' => $firstMedia?->media_type,
            'media_mime_type' => $firstMedia?->mime_type,
            'media_original_name' => $firstMedia?->original_name,
        ])->save();
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
