<?php

namespace App\Notifications;

use App\Models\ParentChildAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RelationshipVerificationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ParentChildAccount $relationship,
        private readonly string $action,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $childName = $this->relationship->child?->name ?? 'Dependent';

        return [
            'type' => 'guardian_relationship_verification_'.$this->action,
            'title' => 'Relationship verification '.str_replace('_', ' ', $this->action),
            'message' => $childName.' relationship verification is now '.$this->relationship->relationshipVerificationLabel().'.',
            'parent_child_account_id' => $this->relationship->id,
            'child_user_id' => $this->relationship->child_user_id,
            'relationship' => $this->relationship->relationshipLabel(),
            'status' => $this->relationship->relationship_verified_status,
            'action_url' => (int) $notifiable->id === (int) $this->relationship->child_user_id
                ? route('learner.parent.index')
                : route('parent.relationship-verifications.show', $this->relationship),
            'severity' => in_array($this->relationship->relationship_verified_status, ['verified', 'not_required'], true) ? 'success' : 'warning',
        ];
    }
}
