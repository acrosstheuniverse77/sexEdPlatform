<?php

namespace App\Models;

use App\Support\GuardianRelationshipTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentChildAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'relationship_type',
        'relationship_custom',
        'relationship_status',
        'relationship_verified_status',
        'relationship_verification_submitted_at',
        'relationship_verification_reviewed_by',
        'relationship_verification_reviewed_at',
        'relationship_verification_rejection_reason',
        'relationship_verification_rejection_note',
        'relationship_verification_revoked_at',
        'relationship_notes',
        'is_legacy_relationship',
        'can_view_progress',
        'can_view_quiz_answers',
        'can_approve_content',
        'verification_status',
        'verification_document_path',
        'verification_rejection_reason',
        'verification_reviewed_by',
        'verification_reviewed_at',
        'verification_approved_at',
        'relationship_verified_at',
    ];

    protected $casts = [
        'can_view_progress' => 'boolean',
        'can_view_quiz_answers' => 'boolean',
        'can_approve_content' => 'boolean',
        'is_legacy_relationship' => 'boolean',
        'verification_reviewed_at' => 'datetime',
        'verification_approved_at' => 'datetime',
        'relationship_verified_at' => 'datetime',
        'relationship_verification_submitted_at' => 'datetime',
        'relationship_verification_reviewed_at' => 'datetime',
        'relationship_verification_revoked_at' => 'datetime',
    ];

    /**
     * Get the parent user
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    /**
     * Get the child user
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function relationshipLabel(): string
    {
        return GuardianRelationshipTypes::label($this->relationship_type, $this->relationship_custom);
    }

    public function relationshipVerificationLabel(): string
    {
        return GuardianRelationshipTypes::statusLabel($this->relationship_verified_status);
    }

    public function requiresRelationshipVerification(): bool
    {
        return GuardianRelationshipTypes::requiresVerification($this->relationship_type);
    }

    public function hasVerifiedRelationshipRequirement(): bool
    {
        return ! $this->requiresRelationshipVerification()
            || in_array($this->relationship_verified_status, ['verified', 'reserved'], true);
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(GuardianRelationshipVerificationDocument::class, 'parent_child_account_id');
    }

    public function verificationAudits(): HasMany
    {
        return $this->hasMany(GuardianRelationshipVerificationAudit::class, 'parent_child_account_id');
    }

    /**
     * Verify the parent-child relationship
     */
    public function verify(): void
    {
        $this->update(['relationship_verified_at' => now()]);
    }

    /**
     * Check if relationship is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'approved' && $this->relationship_verified_at !== null;
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }
}
