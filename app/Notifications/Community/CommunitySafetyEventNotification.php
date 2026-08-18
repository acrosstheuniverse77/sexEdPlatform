<?php

namespace App\Notifications\Community;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunitySafetyEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $event,
        private readonly string $reason,
        private readonly int $actorId,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'community_safety_event',
            'status' => $this->event,
            'title' => 'Community Feed Safety Control Changed',
            'message' => 'The community feed was marked as '.str_replace('_', ' ', $this->event).'.',
            'reason' => $this->reason,
            'actor_id' => $this->actorId,
            'severity' => $this->event === 'global_frozen' ? 'danger' : 'info',
        ];
    }
}
