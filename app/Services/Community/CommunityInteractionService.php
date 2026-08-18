<?php

namespace App\Services\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPreScreenDecision;
use App\Enums\CommunityReactionType;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityInteractionService
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityFeedSettingsService $settings,
        private readonly CommunityContentPreScreeningService $preScreening,
    ) {
    }

    public function comment(User $author, CommunityPost $post, string $body): CommunityComment
    {
        $post->loadMissing(['connector', 'space']);

        abort_unless($this->access->canViewSpace($author, $post->connector), 403);
        abort_unless($post->space !== null && ! $this->settings->isSpaceFrozen($post->space), 403);
        abort_unless($post->connector?->status === 'verified', 403);
        abort_unless($post->status === CommunityPostStatus::Published && ! $post->isLocked(), 403);

        $result = $this->preScreening->screenComment($body);

        if ($result->decision === CommunityPreScreenDecision::BlockWithFeedback) {
            throw ValidationException::withMessages([
                'body' => $result->message ?? 'Revise the content before commenting.',
            ]);
        }

        $status = $result->decision === CommunityPreScreenDecision::AutoHideAndEscalate
            ? CommunityCommentStatus::Escalated
            : CommunityCommentStatus::Visible;

        return $post->comments()->create([
            'author_id' => $author->id,
            'body' => $body,
            'status' => $status,
            'prescreen_decision' => $result->decision->value,
            'prescreen_flags' => $result->flags,
            'escalated_at' => $status === CommunityCommentStatus::Escalated ? now() : null,
            'escalated_by' => $status === CommunityCommentStatus::Escalated ? $author->id : null,
        ]);
    }

    public function react(User $user, CommunityPost $post, CommunityReactionType|string $reactionType): CommunityReaction
    {
        $reactionType = $reactionType instanceof CommunityReactionType ? $reactionType->value : (string) $reactionType;

        if (! in_array($reactionType, CommunityReactionType::values(), true)) {
            throw ValidationException::withMessages(['reaction_type' => 'Invalid community reaction.']);
        }

        $post->loadMissing(['connector', 'space']);
        abort_unless($this->access->canReact($user, $post), 403);

        return CommunityReaction::query()->updateOrCreate(
            [
                'community_post_id' => $post->id,
                'user_id' => $user->id,
                'reaction_type' => $reactionType,
            ],
            [],
        );
    }

    public function toggleReaction(User $user, CommunityPost $post, CommunityReactionType|string $reactionType): array
    {
        $reactionType = $reactionType instanceof CommunityReactionType ? $reactionType->value : (string) $reactionType;

        if (! in_array($reactionType, CommunityReactionType::values(), true)) {
            throw ValidationException::withMessages(['reaction_type' => 'Invalid community reaction.']);
        }

        $post->loadMissing(['connector', 'space']);
        abort_unless($this->access->canReact($user, $post), 403);

        return DB::transaction(function () use ($user, $post, $reactionType): array {
            $reaction = CommunityReaction::query()
                ->where('community_post_id', $post->id)
                ->where('user_id', $user->id)
                ->where('reaction_type', $reactionType)
                ->lockForUpdate()
                ->first();

            $active = $reaction === null;

            if ($reaction) {
                $reaction->delete();
            } else {
                CommunityReaction::query()->create([
                    'community_post_id' => $post->id,
                    'user_id' => $user->id,
                    'reaction_type' => $reactionType,
                ]);
            }

            return [
                'active' => $active,
                'count' => CommunityReaction::query()
                    ->where('community_post_id', $post->id)
                    ->where('reaction_type', $reactionType)
                    ->count(),
            ];
        });
    }

    public function removeReaction(User $user, CommunityPost $post, CommunityReactionType|string $reactionType): void
    {
        $reactionType = $reactionType instanceof CommunityReactionType ? $reactionType->value : (string) $reactionType;

        if (! in_array($reactionType, CommunityReactionType::values(), true)) {
            throw ValidationException::withMessages(['reaction_type' => 'Invalid community reaction.']);
        }

        $post->loadMissing(['connector', 'space']);
        abort_unless($this->access->canReact($user, $post), 403);

        CommunityReaction::query()
            ->where('community_post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('reaction_type', $reactionType)
            ->delete();
    }
}
