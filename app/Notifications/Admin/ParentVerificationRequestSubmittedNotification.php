<?php

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParentVerificationRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $parent)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $parentName = trim((string) ($this->parent->full_name ?? $this->parent->name ?? 'Guardian applicant'));

        return [
            'type' => 'parent_verification_request_submitted',
            'status' => 'pending',
            'title' => 'New Guardian Verification Request',
            'message' => $parentName . ' submitted a guardian verification request.',
            'parent_user_id' => $this->parent->id,
            'action_url' => route('admin.parent-verifications.index', [
                'type' => 'parents',
                'status' => 'pending',
                'focus' => $this->parent->id,
            ]),
            'severity' => 'info',
        ];
    }
}
