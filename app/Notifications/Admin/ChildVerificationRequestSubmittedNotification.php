<?php

namespace App\Notifications\Admin;

use App\Models\ParentChildAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChildVerificationRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $parent,
        private readonly User $child,
        private readonly ParentChildAccount $verification
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $parentName = trim((string) ($this->parent->full_name ?? $this->parent->name ?? 'Guardian'));
        $childName = trim((string) ($this->child->full_name ?? $this->child->name ?? 'Child learner'));

        return [
            'type' => 'child_verification_request_submitted',
            'status' => 'pending',
            'title' => 'New Child Verification Request',
            'message' => $parentName . ' submitted child verification for ' . $childName . '.',
            'parent_user_id' => $this->parent->id,
            'child_user_id' => $this->child->id,
            'parent_child_account_id' => $this->verification->id,
            'action_url' => route('admin.parent-verifications.index', [
                'type' => 'children',
                'status' => 'pending',
                'focus' => $this->verification->id,
            ]),
            'severity' => 'info',
        ];
    }
}
