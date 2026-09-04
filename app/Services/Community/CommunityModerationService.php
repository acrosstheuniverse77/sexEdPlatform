<?php

namespace App\Services\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityModerationActionType;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityComment;
use App\Models\CommunityModerationAction;
use App\Models\CommunityPost;
use App\Models\User;
use App\Notifications\Community\CommunityPostDecisionNotification;
use App\Notifications\Community\CommunityPostEscalatedNotification;
use App\Services\Connectors\ConnectorAccessService;
use Illuminate\Support\Facades\DB;

class CommunityModerationService
{
    public function __construct(
        private readonly CommunityAccessService $access,
        private readonly ConnectorAccessService $connectorAccess,
    ) {}

    public function approvePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Approve, CommunityPostStatus::Published, $reason);
    }

    public function rejectPost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Reject, CommunityPostStatus::Removed, $reason);
    }

    public function hidePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Hide, CommunityPostStatus::Hidden, $reason);
    }

    public function lockPost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Lock, CommunityPostStatus::Locked, $reason);
    }

    public function unlockPost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Unlock, CommunityPostStatus::Published, $reason);
    }

    public function restorePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Restore, CommunityPostStatus::Published, $reason);
    }

    public function removePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Remove, CommunityPostStatus::Removed, $reason);
    }

    public function escalatePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        return $this->transitionPost($actor, $post, CommunityModerationActionType::Escalate, CommunityPostStatus::Escalated, $reason);
    }

    public function featurePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        $post->loadMissing(['connector', 'space']);
        $this->authorizePostModeration($actor, $post, CommunityModerationActionType::Feature);
        abort_unless(in_array($post->status?->value ?? $post->status, [CommunityPostStatus::Published->value, CommunityPostStatus::Locked->value], true), 422, 'Only published or locked posts can be pinned.');

        $previousStatus = $post->status?->value ?? (string) $post->status;

        $post->forceFill([
            'featured_at' => now(),
            'featured_by' => $actor->id,
        ])->save();

        $this->logAction($actor, $post, CommunityModerationActionType::Feature, $previousStatus, $previousStatus, $reason);

        return $post->fresh(['connector', 'space']);
    }

    public function unfeaturePost(User $actor, CommunityPost $post, string $reason): CommunityPost
    {
        $post->loadMissing(['connector', 'space']);
        $this->authorizePostModeration($actor, $post, CommunityModerationActionType::Unfeature);

        $previousStatus = $post->status?->value ?? (string) $post->status;

        $post->forceFill([
            'featured_at' => null,
            'featured_by' => null,
        ])->save();

        $this->logAction($actor, $post, CommunityModerationActionType::Unfeature, $previousStatus, $previousStatus, $reason);

        return $post->fresh(['connector', 'space']);
    }

    public function markOfficialAnswer(User $actor, CommunityPost $post, CommunityComment $comment, string $reason): CommunityPost
    {
        $post->loadMissing(['connector', 'space']);
        $this->authorizePostModeration($actor, $post, CommunityModerationActionType::MarkOfficialAnswer);

        abort_unless($post->isQuestion(), 422);
        abort_unless((int) $comment->community_post_id === (int) $post->id, 404);
        abort_unless($comment->parent_id === null, 422, 'Only top-level comments can be marked as the official answer.');
        abort_unless($comment->status === CommunityCommentStatus::Visible, 422, 'Only visible comments can be marked as the official answer.');

        $previousStatus = $post->status?->value ?? (string) $post->status;

        $post->forceFill([
            'official_answer_comment_id' => $comment->id,
        ])->save();

        $this->logAction($actor, $post, CommunityModerationActionType::MarkOfficialAnswer, $previousStatus, $previousStatus, $reason);

        return $post->fresh(['connector', 'space', 'officialAnswerComment']);
    }

    public function hideComment(User $actor, CommunityComment $comment, string $reason): CommunityComment
    {
        return $this->transitionComment($actor, $comment, CommunityModerationActionType::Hide, CommunityCommentStatus::Hidden, $reason);
    }

    public function removeComment(User $actor, CommunityComment $comment, string $reason): CommunityComment
    {
        return $this->transitionComment($actor, $comment, CommunityModerationActionType::Remove, CommunityCommentStatus::Removed, $reason);
    }

    private function transitionPost(
        User $actor,
        CommunityPost $post,
        CommunityModerationActionType $action,
        CommunityPostStatus $nextStatus,
        string $reason,
    ): CommunityPost {
        $post->loadMissing(['connector', 'space']);
        $this->authorizePostModeration($actor, $post, $action);

        $previousStatus = $post->status?->value ?? (string) $post->status;

        $post->forceFill([
            'status' => $nextStatus,
            'published_at' => $nextStatus === CommunityPostStatus::Published ? ($post->published_at ?? now()) : $post->published_at,
            'published_by' => $nextStatus === CommunityPostStatus::Published ? ($post->published_by ?? $actor->id) : $post->published_by,
            'locked_at' => $nextStatus === CommunityPostStatus::Locked ? now() : ($action === CommunityModerationActionType::Unlock ? null : $post->locked_at),
            'locked_by' => $nextStatus === CommunityPostStatus::Locked ? $actor->id : ($action === CommunityModerationActionType::Unlock ? null : $post->locked_by),
            'lock_reason' => $nextStatus === CommunityPostStatus::Locked ? $reason : ($action === CommunityModerationActionType::Unlock ? null : $post->lock_reason),
            'hidden_at' => $nextStatus === CommunityPostStatus::Hidden ? now() : ($action === CommunityModerationActionType::Restore ? null : $post->hidden_at),
            'hidden_by' => $nextStatus === CommunityPostStatus::Hidden ? $actor->id : ($action === CommunityModerationActionType::Restore ? null : $post->hidden_by),
            'hidden_reason' => $nextStatus === CommunityPostStatus::Hidden ? $reason : ($action === CommunityModerationActionType::Restore ? null : $post->hidden_reason),
            'removed_at' => $nextStatus === CommunityPostStatus::Removed ? now() : ($action === CommunityModerationActionType::Restore ? null : $post->removed_at),
            'removed_by' => $nextStatus === CommunityPostStatus::Removed ? $actor->id : ($action === CommunityModerationActionType::Restore ? null : $post->removed_by),
            'removed_reason' => $nextStatus === CommunityPostStatus::Removed ? $reason : ($action === CommunityModerationActionType::Restore ? null : $post->removed_reason),
            'escalated_at' => $nextStatus === CommunityPostStatus::Escalated ? now() : $post->escalated_at,
            'escalated_by' => $nextStatus === CommunityPostStatus::Escalated ? $actor->id : $post->escalated_by,
        ])->save();

        $this->logAction($actor, $post, $action, $previousStatus, $nextStatus->value, $reason);
        $this->notifyForPostTransition($post->fresh(), $action, $nextStatus, $reason);

        return $post->fresh(['connector', 'space']);
    }

    private function transitionComment(
        User $actor,
        CommunityComment $comment,
        CommunityModerationActionType $action,
        CommunityCommentStatus $nextStatus,
        string $reason,
    ): CommunityComment {
        $comment->loadMissing('post.connector', 'post.space');
        abort_unless($this->access->canManageComments($actor, $comment->post->connector), 403);

        $previousStatus = $comment->status?->value ?? (string) $comment->status;

        return DB::transaction(function () use ($actor, $comment, $action, $previousStatus, $nextStatus, $reason): CommunityComment {
            $comment->forceFill([
                'status' => $nextStatus,
                'hidden_at' => $nextStatus === CommunityCommentStatus::Hidden ? now() : $comment->hidden_at,
                'hidden_by' => $nextStatus === CommunityCommentStatus::Hidden ? $actor->id : $comment->hidden_by,
                'hidden_reason' => $nextStatus === CommunityCommentStatus::Hidden ? $reason : $comment->hidden_reason,
                'removed_at' => $nextStatus === CommunityCommentStatus::Removed ? now() : $comment->removed_at,
                'removed_by' => $nextStatus === CommunityCommentStatus::Removed ? $actor->id : $comment->removed_by,
                'removed_reason' => $nextStatus === CommunityCommentStatus::Removed ? $reason : $comment->removed_reason,
            ])->save();

            CommunityPost::query()
                ->whereKey($comment->community_post_id)
                ->where('official_answer_comment_id', $comment->id)
                ->update(['official_answer_comment_id' => null]);

            $this->logAction($actor, $comment, $action, $previousStatus, $nextStatus->value, $reason);

            return $comment->fresh();
        });
    }

    private function authorizePostModeration(User $actor, CommunityPost $post, CommunityModerationActionType $action): void
    {
        if ($actor->hasRole('admin') && $actor->can('community.moderate_any')) {
            return;
        }

        $permission = match ($action) {
            CommunityModerationActionType::Approve => 'community.approve_posts',
            CommunityModerationActionType::Lock, CommunityModerationActionType::Unlock => 'community.lock_threads',
            CommunityModerationActionType::Escalate => 'community.escalate_to_platform',
            default => 'community.manage_posts',
        };

        abort_unless($post->connector?->status === 'verified', 403);
        abort_unless($this->connectorAccess->hasPermission($actor, $post->connector, $permission), 403);
    }

    private function logAction(
        User $actor,
        CommunityPost|CommunityComment $target,
        CommunityModerationActionType $action,
        ?string $previousStatus,
        ?string $nextStatus,
        string $reason,
    ): void {
        $post = $target instanceof CommunityPost ? $target : $target->post;

        CommunityModerationAction::query()->create([
            'connector_id' => $post->connector_id,
            'community_space_id' => $post->community_space_id,
            'actor_id' => $actor->id,
            'target_type' => $target::class,
            'target_id' => $target->id,
            'action_type' => $action->value,
            'previous_status' => $previousStatus,
            'new_status' => $nextStatus,
            'reason' => $reason,
            'metadata' => [
                'actor_role' => $actor->role,
                'platform_admin' => $actor->hasRole('admin'),
            ],
        ]);
    }

    private function notifyForPostTransition(
        CommunityPost $post,
        CommunityModerationActionType $action,
        CommunityPostStatus $nextStatus,
        string $reason,
    ): void {
        if ($post->author) {
            $post->author->notify(new CommunityPostDecisionNotification($post, $nextStatus->value, $reason));
        }

        if ($action === CommunityModerationActionType::Escalate) {
            User::role('admin')->get()->each(
                fn (User $admin) => $admin->notify(new CommunityPostEscalatedNotification($post, $reason))
            );
        }
    }
}
