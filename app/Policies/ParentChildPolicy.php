<?php

namespace App\Policies;

use App\Models\User;

class ParentChildPolicy
{
    /**
     * Determine if the authenticated parent can view a child's monitoring page.
     * Checks that a parent_child_accounts row exists linking them.
     */
    public function view(User $parent, User $child): bool
    {
        if ($parent->isParentRegistration() && ! $parent->isParentVerificationApproved()) {
            return false;
        }

        $relationship = $parent->children()
            ->where('child_user_id', $child->id)
            ->wherePivot('verification_status', 'approved')
            ->first();

        if (! $relationship) {
            return false;
        }

        $status = (string) ($relationship->pivot->relationship_verified_status ?? 'not_required');

        return ! \App\Support\GuardianRelationshipTypes::requiresVerification($relationship->pivot->relationship_type ?? null)
            || in_array($status, ['verified', 'reserved'], true);
    }
}
