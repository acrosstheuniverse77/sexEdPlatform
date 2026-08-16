<?php

namespace App\Notifications\Admin;

use App\Models\ParentChildAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RelationshipVerificationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ParentChildAccount $relationship)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'guardian_relationship_verification_submitted',
            'title' => 'New Relationship Verification',
            'message' => ($this->relationship->parent?->name ?? 'Guardian').' submitted '.$this->relationship->relationshipLabel().' verification.',
            'parent_child_account_id' => $this->relationship->id,
            'parent_user_id' => $this->relationship->parent_user_id,
            'child_user_id' => $this->relationship->child_user_id,
            'status' => $this->relationship->relationship_verified_status,
            'action_url' => route('admin.parent-verifications.relationships.show', $this->relationship),
            'severity' => 'info',
        ];
    }
}
