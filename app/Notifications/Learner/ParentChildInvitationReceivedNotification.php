<?php

namespace App\Notifications\Learner;

use App\Models\ParentChildInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParentChildInvitationReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ParentChildInvitation $invitation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $parentName = $this->invitation->inviterParent?->name ?? 'A guardian';
        $relationship = $this->invitation->relationshipLabel();

        return [
            'type' => 'parent_child_invitation_received',
            'title' => 'Guardian Invitation Received',
            'message' => $parentName . ' invited you to link accounts as ' . $relationship . '.',
            'invitation_id' => $this->invitation->id,
            'parent_user_id' => $this->invitation->inviter_parent_user_id,
            'parent_name' => $parentName,
            'relationship' => $relationship,
            'status' => 'pending',
            'action_url' => route('parent.invitations.show', $this->invitation),
            'severity' => 'info',
        ];
    }
}
