<?php

namespace App\Notifications\Learner;

use App\Models\ModuleEnrollment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParentEnrollmentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ModuleEnrollment $enrollment,
        private readonly User $parent,
        private readonly ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $module = $this->enrollment->module;
        $reason = $this->reason ? trim($this->reason) : null;

        $message = 'Your guardian rejected your enrollment request for "' . $module->title . '".';

        if ($reason) {
            $message .= ' Reason: ' . $reason;
        }

        return [
            'type' => 'parent_enrollment_rejected',
            'title' => 'Enrollment Rejected by Guardian',
            'message' => $message,
            'module_id' => $module->id,
            'module_title' => $module->title,
            'rejected_by' => $this->parent->name,
            'reason' => $reason,
            'status' => 'rejected',
            'action_url' => route('learner.notifications.index'),
            'severity' => 'error',
        ];
    }
}
