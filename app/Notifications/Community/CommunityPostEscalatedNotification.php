<?php

namespace App\Notifications\Community;

use App\Models\CommunityPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunityPostEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CommunityPost $post,
        private readonly ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'community_post_escalated',
            'status' => 'escalated',
            'title' => 'Community Post Escalated',
            'message' => 'A community post was escalated for platform moderation.',
            'community_post_id' => $this->post->id,
            'connector_id' => $this->post->connector_id,
            'community_space_id' => $this->post->community_space_id,
            'reason' => $this->reason,
            'severity' => 'danger',
        ];
    }
}
