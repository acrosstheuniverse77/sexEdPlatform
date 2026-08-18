<?php

namespace App\Notifications\Community;

use App\Models\CommunityPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunityPostPendingReviewNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CommunityPost $post)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'community_post_pending_review',
            'status' => 'pending_review',
            'title' => 'Community Post Needs Review',
            'message' => 'A community post is waiting for connector moderation.',
            'community_post_id' => $this->post->id,
            'connector_id' => $this->post->connector_id,
            'community_space_id' => $this->post->community_space_id,
            'severity' => 'warning',
        ];
    }
}
