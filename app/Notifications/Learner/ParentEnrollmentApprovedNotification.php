<?php

namespace App\Notifications\Learner;

use App\Models\ModuleEnrollment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParentEnrollmentApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ModuleEnrollment $enrollment,
        private readonly User $parent,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $module = $this->enrollment->module;

        return [
            'type' => 'parent_enrollment_approved',
            'title' => 'Enrollment Approved by Guardian',
            'message' => 'Your guardian approved your enrollment request for "' . $module->title . '".',
            'module_id' => $module->id,
            'module_title' => $module->title,
            'approved_by' => $this->parent->name,
            'status' => 'approved',
            'action_url' => route('learner.modules.show', $module),
            'severity' => 'success',
        ];
    }
}
