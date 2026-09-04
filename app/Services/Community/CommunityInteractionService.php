<?php

namespace App\Services\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPreScreenDecision;
use App\Enums\CommunityReactionType;
use App\Models\CommunityComment;
use App\Models\CommunityCommentUpvote;
use App\Models\CommunityPost;
use App\Models\CommunityPostUpvote;
use App\Models\CommunityReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityInteractionService
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly CommunityContentPreScreeningService $preScreening,
    ) {}

    public function comment(
        User $author,
        CommunityPost $post,
        string $body,
        ?CommunityComment $parent = null,
    ): CommunityComment {
        $post->loadMissing(['connector', 'space']);

        abort_unless($this->access->canCommentOnPost($author, $post), 403);

        return DB::transaction(function () use ($author, $post, $body, $parent): CommunityComment {
            if ($parent) {
                $parent = CommunityComment::query()->lockForUpdate()->findOrFail($parent->id);
                abort_unless((int) $parent->community_post_id === (int) $post->id, 404);
                abort_if($parent->parent_id !== null, 422);
                abort_unless(
                    ($parent->status?->value ?? $parent->status) === CommunityCommentStatus::Visible->value,
                    403
                );
            }

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
                'parent_id' => $parent?->id,
                'author_id' => $author->id,
                'body' => $body,
                'status' => $status,
                'prescreen_decision' => $result->decision->value,
                'prescreen_flags' => $result->flags,
                'escalated_at' => $status === CommunityCommentStatus::Escalated ? now() : null,
                'escalated_by' => $status === CommunityCommentStatus::Escalated ? $author->id : null,
            ]);
        });
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

    public function togglePostUpvote(User $user, CommunityPost $post): array
    {
        $post->loadMissing(['connector', 'space']);
        abort_unless($this->access->canUpvotePost($user, $post), 403);

        return DB::transaction(function () use ($user, $post): array {
            $upvote = CommunityPostUpvote::query()
                ->where('community_post_id', $post->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $active = $upvote === null;

            if ($upvote) {
                $upvote->delete();
            } else {
                CommunityPostUpvote::query()->firstOrCreate([
                    'community_post_id' => $post->id,
                    'user_id' => $user->id,
                ]);
            }

            return [
                'active' => $active,
                'count' => CommunityPostUpvote::query()->where('community_post_id', $post->id)->count(),
            ];
        });
    }

    public function toggleCommentUpvote(User $user, CommunityPost $post, CommunityComment $comment): array
    {
        $post->loadMissing(['connector', 'space']);
        $comment->loadMissing('post');
        abort_unless($this->access->canUpvoteComment($user, $post, $comment), 403);

        return DB::transaction(function () use ($user, $comment): array {
            $upvote = CommunityCommentUpvote::query()
                ->where('community_comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $active = $upvote === null;

            if ($upvote) {
                $upvote->delete();
            } else {
                CommunityCommentUpvote::query()->firstOrCreate([
                    'community_comment_id' => $comment->id,
                    'user_id' => $user->id,
                ]);
            }

            return [
                'active' => $active,
                'count' => CommunityCommentUpvote::query()->where('community_comment_id', $comment->id)->count(),
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
