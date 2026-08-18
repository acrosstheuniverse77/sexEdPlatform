<?php

namespace App\Services;

use App\Models\GuardianRelationshipVerificationAudit;
use App\Models\GuardianRelationshipVerificationDocument;
use App\Models\ParentChildAccount;
use App\Models\User;
use App\Notifications\Admin\RelationshipVerificationSubmittedNotification;
use App\Notifications\RelationshipVerificationStatusNotification;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class GuardianRelationshipVerificationService
{
    public function initialStatus(string $relationshipType): string
    {
        return \App\Support\GuardianRelationshipTypes::initialVerificationStatus($relationshipType);
    }

    public function submit(ParentChildAccount $relationship, User $guardian, array $payload): ParentChildAccount
    {
        return DB::transaction(function () use ($relationship, $guardian, $payload): ParentChildAccount {
            $documentType = (string) $payload['document_type'];

            $this->storeDocument($relationship, $guardian, $documentType, $payload['document']);

            if (($payload['supporting_document'] ?? null) instanceof UploadedFile) {
                $this->storeDocument($relationship, $guardian, 'supporting_legal_document', $payload['supporting_document']);
            }

            return $this->transitionToUnderReview($relationship, $guardian);
        });
    }

    public function submitStaged(ParentChildAccount $relationship, User $guardian, array $documents, ?Closure $onSubmitted = null, ?array &$movedDocuments = null): ParentChildAccount
    {
        $movedDocuments ??= [];

        return DB::transaction(function () use ($relationship, $guardian, $documents, $onSubmitted, &$movedDocuments): ParentChildAccount {
            $this->assertStagedDocumentsExist($documents);

            try {
                foreach ($documents as $document) {
                    $destination = $this->moveStagedDocument($relationship, $document);
                    $movedDocuments[] = ['source' => $document['path'], 'destination' => $destination];

                    $this->createStagedDocument($relationship, $guardian, $document, $destination);
                }

                $submittedRelationship = $this->transitionToUnderReview($relationship, $guardian);
                $onSubmitted?->__invoke($submittedRelationship);

                return $submittedRelationship;
            } catch (\Throwable $exception) {
                $this->restoreStagedDocuments($movedDocuments);

                throw $exception;
            }
        });
    }

    public function approve(ParentChildAccount $relationship, User $admin): ParentChildAccount
    {
        return $this->transition($relationship, $admin, 'approved', 'verified');
    }

    public function reject(ParentChildAccount $relationship, User $admin, string $reasonCode, ?string $note, bool $allowResubmission = true): ParentChildAccount
    {
        return $this->transition(
            $relationship,
            $admin,
            $allowResubmission ? 'resubmission_required' : 'rejected',
            $allowResubmission ? 'resubmission_required' : 'rejected',
            $reasonCode,
            $note,
        );
    }

    public function revoke(ParentChildAccount $relationship, User $admin, string $reasonCode, ?string $note): ParentChildAccount
    {
        return $this->transition($relationship, $admin, 'revoked', 'revoked', $reasonCode, $note);
    }

    private function transition(ParentChildAccount $relationship, User $actor, string $action, string $newStatus, ?string $reasonCode = null, ?string $note = null): ParentChildAccount
    {
        return DB::transaction(function () use ($relationship, $actor, $action, $newStatus, $reasonCode, $note): ParentChildAccount {
            $previous = (string) $relationship->relationship_verified_status;

            $relationship->update([
                'relationship_verified_status' => $newStatus,
                'relationship_status' => $newStatus === 'verified' ? 'active' : $relationship->relationship_status,
                'verification_status' => match ($newStatus) {
                    'verified' => 'approved',
                    'rejected', 'revoked' => 'rejected',
                    default => $relationship->verification_status ?: 'pending',
                },
                'relationship_verification_reviewed_by' => $actor->id,
                'relationship_verification_reviewed_at' => now(),
                'relationship_verification_rejection_reason' => $reasonCode,
                'relationship_verification_rejection_note' => $note,
                'relationship_verification_revoked_at' => $newStatus === 'revoked' ? now() : null,
                'relationship_verified_at' => $newStatus === 'verified' ? now() : $relationship->relationship_verified_at,
            ]);

            $this->audit($relationship, $actor, $action, $previous, $newStatus, $reasonCode, $note);
            $freshRelationship = $relationship->fresh(['parent', 'child', 'verificationDocuments']);
            $freshRelationship->parent?->notify(new RelationshipVerificationStatusNotification($freshRelationship, $action));
            $freshRelationship->child?->notify(new RelationshipVerificationStatusNotification($freshRelationship, $action));

            return $freshRelationship;
        });
    }

    private function storeDocument(ParentChildAccount $relationship, User $guardian, string $documentType, UploadedFile $file): void
    {
        $path = $file->store('guardian-relationship-verifications/'.$relationship->id, 'local');

        GuardianRelationshipVerificationDocument::query()->create([
            'parent_child_account_id' => $relationship->id,
            'uploaded_by_user_id' => $guardian->id,
            'document_type' => $documentType,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }

    private function assertStagedDocumentsExist(array $documents): void
    {
        if ($documents === []) {
            throw new InvalidArgumentException('A staged verification document is missing.');
        }

        foreach ($documents as $document) {
            $path = is_array($document) ? (string) ($document['path'] ?? '') : '';

            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                throw new InvalidArgumentException('A staged verification document is missing.');
            }
        }
    }

    private function moveStagedDocument(ParentChildAccount $relationship, array $document): string
    {
        $path = (string) $document['path'];
        $destination = 'guardian-relationship-verifications/'.$relationship->id.'/'.basename($path);

        if (! Storage::disk('local')->move($path, $destination)) {
            throw new InvalidArgumentException('A staged verification document is missing.');
        }

        return $destination;
    }

    private function createStagedDocument(ParentChildAccount $relationship, User $guardian, array $document, string $destination): void
    {
        GuardianRelationshipVerificationDocument::query()->create([
            'parent_child_account_id' => $relationship->id,
            'uploaded_by_user_id' => $guardian->id,
            'document_type' => (string) $document['document_type'],
            'disk' => 'local',
            'path' => $destination,
            'original_name' => (string) $document['original_name'],
            'mime_type' => (string) $document['mime_type'],
            'size_bytes' => (int) $document['size_bytes'],
        ]);
    }

    public function restoreStagedDocuments(array &$movedDocuments): void
    {
        $disk = Storage::disk('local');

        foreach (array_reverse($movedDocuments) as $document) {
            try {
                if (! $disk->exists($document['destination'])) {
                    continue;
                }

                if ($disk->exists($document['source'])) {
                    $disk->delete($document['destination']);

                    continue;
                }

                if ($disk->move($document['destination'], $document['source'])) {
                    continue;
                }

                Log::error('Unable to restore staged verification document after submission failure.', [
                    'source' => $document['source'],
                    'destination' => $document['destination'],
                ]);
            } catch (\Throwable $exception) {
                Log::error('Unable to restore staged verification document after submission failure.', [
                    'source' => $document['source'],
                    'destination' => $document['destination'],
                    'exception' => $exception,
                ]);
            }
        }

        $movedDocuments = [];
    }

    private function transitionToUnderReview(ParentChildAccount $relationship, User $guardian): ParentChildAccount
    {
        $previous = (string) ($relationship->relationship_verified_status ?: 'pending');

        $relationship->update([
            'relationship_verified_status' => 'under_review',
            'relationship_verification_submitted_at' => now(),
            'relationship_verification_reviewed_by' => null,
            'relationship_verification_reviewed_at' => null,
            'relationship_verification_rejection_reason' => null,
            'relationship_verification_rejection_note' => null,
            'relationship_verification_revoked_at' => null,
        ]);

        $this->audit($relationship, $guardian, $previous === 'rejected' || $previous === 'resubmission_required' ? 'resubmitted' : 'submitted', $previous, 'under_review');
        $guardian->notify(new RelationshipVerificationStatusNotification($relationship->fresh(['child']), 'submitted'));
        $this->notifyAdminsSafely(new RelationshipVerificationSubmittedNotification($relationship->fresh(['parent', 'child'])));

        return $relationship->fresh(['parent', 'child', 'verificationDocuments']);
    }

    private function audit(ParentChildAccount $relationship, User $actor, string $action, ?string $previous, ?string $new, ?string $reasonCode = null, ?string $notes = null): void
    {
        GuardianRelationshipVerificationAudit::query()->create([
            'parent_child_account_id' => $relationship->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'previous_status' => $previous,
            'new_status' => $new,
            'reason_code' => $reasonCode,
            'notes' => $notes,
        ]);
    }

    private function notifyAdminsSafely(Notification $notification): void
    {
        User::query()
            ->where('role', 'admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->get()
            ->each(fn (User $admin) => $admin->notify($notification));
    }
}
