<?php

namespace App\Services;

use App\Enums\ParentChildInvitationStatus;
use App\Models\ParentChildAccount;
use App\Models\ParentChildInvitation;
use App\Models\User;
use App\Notifications\Learner\ParentChildInvitationReceivedNotification;
use App\Notifications\Parent\ParentChildInvitationRespondedNotification;
use App\Services\GuardianRelationshipVerificationService;
use App\Support\GuardianRelationshipTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ParentChildInvitationService
{
    public function __construct(private readonly GuardianRelationshipVerificationService $relationshipVerificationService)
    {
    }

    public function sendInvitation(
        User $parent,
        string $identifier,
        string $relationshipType,
        ?string $relationshipCustom = null,
        ?string $message = null,
        ?array $verificationPayload = null,
    ): ParentChildInvitation
    {
        $child = $this->resolveChildFromIdentifier($identifier);

        if (! $child || ! $child->isLearner()) {
            throw new InvalidArgumentException('No learner account matches that username or email.');
        }

        if ((int) $parent->id === (int) $child->id) {
            throw new InvalidArgumentException('You cannot invite your own account.');
        }

        $existingRelationship = ParentChildAccount::withTrashed()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->first();

        if ($existingRelationship && $existingRelationship->deleted_at === null && $existingRelationship->verification_status === 'approved') {
            throw new InvalidArgumentException('This learner is already linked to your guardian account.');
        }

        if ($existingRelationship && $existingRelationship->deleted_at === null && $existingRelationship->verification_status === 'pending') {
            throw new InvalidArgumentException('A relationship verification request is already pending for this learner.');
        }

        $existingPending = ParentChildInvitation::query()
            ->where('inviter_parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->where('status', ParentChildInvitationStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($existingPending !== null) {
            if ($existingPending->isExpired()) {
                $existingPending->update(['status' => ParentChildInvitationStatus::Expired->value]);
            } else {
                throw new InvalidArgumentException('An invitation is already pending for this learner.');
            }
        }

        $requiresVerification = GuardianRelationshipTypes::requiresVerification($relationshipType);
        if ($requiresVerification && ! $verificationPayload) {
            throw new InvalidArgumentException('Supporting documentation is required for this relationship.');
        }

        $stagedPaths = [];

        try {
            $invitation = DB::transaction(function () use ($parent, $child, $relationshipType, $relationshipCustom, $message, $requiresVerification, $verificationPayload, &$stagedPaths): ParentChildInvitation {
                $invitation = ParentChildInvitation::query()->create([
                    'inviter_parent_user_id' => $parent->id,
                    'child_user_id' => $child->id,
                    'relationship_type' => $relationshipType,
                    'relationship_custom' => $relationshipType === GuardianRelationshipTypes::OTHER ? trim((string) $relationshipCustom) : null,
                    'invite_token' => (string) Str::uuid(),
                    'status' => ParentChildInvitationStatus::Pending->value,
                    'message' => $message ? trim($message) : null,
                    'expires_at' => now()->addDays(14),
                ]);

                if ($requiresVerification) {
                    $document = $this->stagedDocument($verificationPayload['document_type'], $verificationPayload['document'], $invitation);
                    $documents = [$document];
                    $stagedPaths[] = $document['path'];

                    if (($verificationPayload['supporting_document'] ?? null) instanceof UploadedFile) {
                        $supportingDocument = $this->stagedDocument('supporting_legal_document', $verificationPayload['supporting_document'], $invitation);
                        $documents[] = $supportingDocument;
                        $stagedPaths[] = $supportingDocument['path'];
                    }

                    $invitation->update(['relationship_verification_documents' => $documents]);
                }

                return $invitation;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stagedPaths);

            throw $exception;
        }

        $invitation->load(['inviterParent:id,name', 'child:id,name']);
        $child->notify(new ParentChildInvitationReceivedNotification($invitation));

        return $invitation;
    }

    public function respondToInvitation(User $child, ParentChildInvitation $invitation, string $decision, ?string $note = null): ParentChildInvitation
    {
        if ((int) $invitation->child_user_id !== (int) $child->id) {
            throw new InvalidArgumentException('You are not allowed to respond to this invitation.');
        }

        $normalizedDecision = trim(strtolower($decision));
        if (! in_array($normalizedDecision, ['accept', 'reject'], true)) {
            throw new InvalidArgumentException('Invalid invitation decision.');
        }

        $decisionNote = $note ? trim($note) : null;

        [$updatedInvitation, $wasExpired] = DB::transaction(function () use ($child, $invitation, $normalizedDecision, $decisionNote): array {
            $invitation = ParentChildInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if (($invitation->status instanceof ParentChildInvitationStatus ? $invitation->status->value : (string) $invitation->status) !== ParentChildInvitationStatus::Pending->value) {
                throw new InvalidArgumentException('This invitation is no longer pending.');
            }

            if ($invitation->isExpired()) {
                $invitation->update(['status' => ParentChildInvitationStatus::Expired->value]);

                return [$invitation->fresh(), true];
            }

            $lockedChild = User::query()->lockForUpdate()->findOrFail($child->id);

            if ((int) $invitation->child_user_id !== (int) $lockedChild->id) {
                throw new InvalidArgumentException('You are not allowed to respond to this invitation.');
            }

            if ($normalizedDecision === 'accept') {
                $link = ParentChildAccount::withTrashed()
                    ->lockForUpdate()
                    ->where('parent_user_id', $invitation->inviter_parent_user_id)
                    ->where('child_user_id', $invitation->child_user_id)
                    ->first();
                $relationshipType = $invitation->relationship_type ?: GuardianRelationshipTypes::LEGACY_PARENT;
                $requiresVerification = GuardianRelationshipTypes::requiresVerification($relationshipType);
                $documents = $requiresVerification ? $invitation->relationship_verification_documents : null;

                if ($requiresVerification && (! is_array($documents) || $documents === [])) {
                    throw new InvalidArgumentException('A staged verification document is missing.');
                }

                $relationshipStatus = $requiresVerification
                    ? ($link?->relationship_verified_status ?: $this->relationshipVerificationService->initialStatus($relationshipType))
                    : $this->relationshipVerificationService->initialStatus($relationshipType);

                $payload = [
                    'can_view_progress' => true,
                    'can_view_quiz_answers' => true,
                    'can_approve_content' => true,
                    'relationship_type' => $relationshipType,
                    'relationship_custom' => $invitation->relationship_custom,
                    'relationship_status' => 'pending',
                    'relationship_verified_status' => $relationshipStatus,
                    'is_legacy_relationship' => false,
                    'verification_status' => 'pending',
                    'verification_rejection_reason' => null,
                    'verification_reviewed_by' => null,
                    'verification_reviewed_at' => null,
                    'verification_approved_at' => null,
                    'verification_document_path' => null,
                    'relationship_verified_at' => null,
                ];

                if ($link) {
                    if ($link->trashed()) {
                        $link->restore();
                    }

                    $link->update($payload);
                    $relationship = $link;
                } else {
                    $relationship = ParentChildAccount::query()->create([
                        'parent_user_id' => $invitation->inviter_parent_user_id,
                        'child_user_id' => $invitation->child_user_id,
                        ...$payload,
                    ]);
                }

                if ($requiresVerification) {
                    $this->relationshipVerificationService->submitStaged(
                        $relationship,
                        $invitation->inviterParent()->firstOrFail(),
                        $documents,
                        function () use ($invitation, $decisionNote): void {
                            $invitation->update([
                                'status' => ParentChildInvitationStatus::Accepted->value,
                                'decision_note' => $decisionNote,
                                'responded_at' => now(),
                                'relationship_verification_documents' => null,
                            ]);
                        },
                    );
                } else {
                    $invitation->update([
                        'status' => ParentChildInvitationStatus::Accepted->value,
                        'decision_note' => $decisionNote,
                        'responded_at' => now(),
                        'relationship_verification_documents' => null,
                    ]);
                }

            } else {
                $invitation->update([
                    'status' => ParentChildInvitationStatus::Rejected->value,
                    'decision_note' => $decisionNote,
                    'responded_at' => now(),
                    'relationship_verification_documents' => $invitation->relationship_verification_documents,
                ]);
            }

            return [$invitation->fresh(['inviterParent:id,name', 'child:id,name']), false];
        });

        if ($wasExpired) {
            throw new InvalidArgumentException('This invitation has already expired.');
        }

        $updatedInvitation->inviterParent?->notify(new ParentChildInvitationRespondedNotification($updatedInvitation));

        return $updatedInvitation;
    }

    public function cancelInvitation(User $parent, ParentChildInvitation $invitation): ParentChildInvitation
    {
        if ((int) $invitation->inviter_parent_user_id !== (int) $parent->id) {
            throw new InvalidArgumentException('You are not allowed to cancel this invitation.');
        }

        if (($invitation->status instanceof ParentChildInvitationStatus ? $invitation->status->value : (string) $invitation->status) !== ParentChildInvitationStatus::Pending->value) {
            throw new InvalidArgumentException('Only pending invitations can be cancelled.');
        }

        $invitation->update([
            'status' => ParentChildInvitationStatus::Cancelled->value,
            'responded_at' => now(),
        ]);

        return $invitation->fresh();
    }

    public function getOutgoingInvitations(User $parent): Collection
    {
        $invitations = ParentChildInvitation::query()
            ->where('inviter_parent_user_id', $parent->id)
            ->with([
                'inviterParent:id,name,email',
                'inviterParent.learnerProfile:id,user_id,avatar_path',
                'child:id,name,email,first_name,last_name',
                'child.learnerProfile:id,user_id,username,birthdate,avatar_path',
            ])
            ->latest('id')
            ->get();

        $this->expirePendingInvitations($invitations);

        return $invitations;
    }

    public function getIncomingInvitations(User $child): Collection
    {
        $invitations = ParentChildInvitation::query()
            ->where('child_user_id', $child->id)
            ->with(['inviterParent:id,name,email'])
            ->latest('id')
            ->get();

        $this->expirePendingInvitations($invitations);

        return $invitations;
    }

    private function resolveChildFromIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $normalized = strtolower($identifier);

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orWhereHas('learnerProfile', function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(username) = ?', [$normalized]);
            })
            ->first();
    }

    private function stagedDocument(string $documentType, UploadedFile $file, ParentChildInvitation $invitation): array
    {
        return [
            'document_type' => $documentType,
            'disk' => 'local',
            'path' => $file->store("guardian-relationship-invitations/{$invitation->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ];
    }

    private function expirePendingInvitations(Collection $invitations): void
    {
        $invitations
            ->filter(fn (ParentChildInvitation $invitation) => $invitation->isPending() && $invitation->isExpired())
            ->each(function (ParentChildInvitation $invitation): void {
                $invitation->update(['status' => ParentChildInvitationStatus::Expired->value]);
                $invitation->status = ParentChildInvitationStatus::Expired;
            });
    }
}
