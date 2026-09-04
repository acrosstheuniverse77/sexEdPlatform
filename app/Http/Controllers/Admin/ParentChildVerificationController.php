<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParentChildModerationReason;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewGuardianRelationshipVerificationRequest;
use App\Http\Requests\Admin\RejectChildVerificationRequest;
use App\Http\Requests\Admin\RejectParentVerificationRequest;
use App\Models\GuardianRelationshipVerificationDocument;
use App\Models\ParentChildAccount;
use App\Models\User;
use App\Services\GuardianRelationshipVerificationService;
use App\Services\ParentChildVerificationService;
use App\Support\GuardianRelationshipTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ParentChildVerificationController extends Controller
{
    public function __construct(
        private readonly ParentChildVerificationService $service,
        private readonly GuardianRelationshipVerificationService $relationshipVerificationService,
    ) {
    }

    public function index(Request $request): View
    {
        $type = $request->string('type')->toString() ?: 'children';
        if (! in_array($type, ['parents', 'children', 'relationships'], true)) {
            $type = 'children';
        }

        $status = $request->string('status')->toString() ?: VerificationStatus::Pending->value;
        if (! in_array($status, VerificationStatus::values(), true)) {
            $status = VerificationStatus::Pending->value;
        }

        $parentApplications = $this->parentApplications($status);
        $childApplications = $this->childApplications($status);
        $relationshipApplications = $this->relationshipApplications($status);

        return view('admin.parent-verifications.index', [
            'type' => $type,
            'status' => $status,
            'parentApplications' => $parentApplications,
            'childApplications' => $childApplications,
            'relationshipApplications' => $relationshipApplications,
            'pendingParentCount' => User::query()
                ->where('is_parent_registration', true)
                ->where('parent_verification_status', VerificationStatus::Pending->value)
                ->count(),
            'approvedParentCount' => User::query()
                ->where('is_parent_registration', true)
                ->where('parent_verification_status', VerificationStatus::Approved->value)
                ->count(),
            'rejectedParentCount' => User::query()
                ->where('is_parent_registration', true)
                ->where('parent_verification_status', VerificationStatus::Rejected->value)
                ->count(),
            'pendingChildCount' => ParentChildAccount::query()
                ->whereNotNull('verification_document_path')
                ->where(function ($query): void {
                    $query->where('verification_status', VerificationStatus::Pending->value)
                        ->orWhereNull('verification_status');
                })
                ->count(),
            'approvedChildCount' => ParentChildAccount::query()
                ->whereNotNull('verification_document_path')
                ->where('verification_status', VerificationStatus::Approved->value)
                ->count(),
            'rejectedChildCount' => ParentChildAccount::query()
                ->whereNotNull('verification_document_path')
                ->where('verification_status', VerificationStatus::Rejected->value)
                ->count(),
            'pendingRelationshipCount' => $this->relationshipCountForStatus('pending'),
            'approvedRelationshipCount' => $this->relationshipCountForStatus('approved'),
            'rejectedRelationshipCount' => $this->relationshipCountForStatus('rejected'),
        ]);
    }

    public function approveParent(Request $request, User $user): RedirectResponse|JsonResponse
    {
        if (! $user->isParentRegistration()) {
            return $this->respondError($request, 'Selected account is not a guardian verification application.', 422);
        }

        if (! $this->isPendingStatus($user->parent_verification_status)) {
            return $this->respondError($request, 'Decision already finalized. Only pending records can be moderated.', 409);
        }

        $this->service->approveParent($user);
        $user->refresh();

        return $this->respondSuccess(
            request: $request,
            message: 'Guardian verification approved successfully.',
            status: $this->normalizedStatus($user->parent_verification_status),
            rejectionReason: $user->parent_verification_rejection_reason,
        );
    }

    public function rejectParent(RejectParentVerificationRequest $request, User $user): RedirectResponse|JsonResponse
    {
        if (! $user->isParentRegistration()) {
            return $this->respondError($request, 'Selected account is not a guardian verification application.', 422);
        }

        if (! $this->isPendingStatus($user->parent_verification_status)) {
            return $this->respondError($request, 'Decision already finalized. Only pending records can be moderated.', 409);
        }

        $reason = $this->composeRejectionReason(
            (string) $request->string('reason_code'),
            $request->filled('custom_reason') ? (string) $request->string('custom_reason') : null,
        );

        $this->service->rejectParent($user, $reason);
        $user->refresh();

        return $this->respondSuccess(
            request: $request,
            message: 'Guardian verification rejected successfully.',
            status: $this->normalizedStatus($user->parent_verification_status),
            rejectionReason: $user->parent_verification_rejection_reason,
        );
    }

    public function showParent(User $user): View
    {
        abort_unless($user->isParentRegistration(), 404);

        return view('admin.parent-verifications.show-parent', [
            'guardian' => $user->load('learnerProfile'),
            'idTypeLabel' => $this->idTypeLabel($user),
        ]);
    }

    public function parentDocument(User $user, string $side): BinaryFileResponse
    {
        abort_unless($user->isParentRegistration(), 404);

        $path = $side === 'back'
            ? $user->parent_id_document_back_path
            : $user->parent_id_document_path;

        abort_if(empty($path) || ! Storage::disk('local')->exists((string) $path), 404);

        return response()->file(
            Storage::disk('local')->path((string) $path),
            ['Content-Disposition' => 'inline; filename="guardian-id-'.$side.'"']
        );
    }

    public function resetGuardianOnboarding(User $user): RedirectResponse
    {
        abort_unless($user->isParentRegistration(), 404);

        $user->forceFill([
            'guardian_onboarding_status' => 'not_started',
            'guardian_onboarding_started_at' => null,
            'guardian_onboarding_completed_at' => null,
        ])->save();

        return back()->with('success', 'Guardian onboarding reset successfully.');
    }

    public function showRelationship(ParentChildAccount $parentChildAccount): View
    {
        abort_unless($this->isRelationshipReviewRecord($parentChildAccount), 404);

        return view('admin.parent-verifications.show-relationship', [
            'relationship' => $parentChildAccount->load([
                'parent.learnerProfile',
                'child.learnerProfile',
                'verificationDocuments.uploadedBy',
                'verificationAudits.actor',
            ]),
            'rejectionReasons' => GuardianRelationshipTypes::rejectionReasons(),
        ]);
    }

    public function approveRelationship(Request $request, ParentChildAccount $parentChildAccount): RedirectResponse|JsonResponse
    {
        if (! in_array((string) $parentChildAccount->relationship_verified_status, ['pending', 'under_review', 'resubmission_required'], true)) {
            return $this->respondError($request, 'Decision already finalized. Only pending relationship records can be approved.', 409);
        }

        $this->relationshipVerificationService->approve($parentChildAccount, $request->user());

        return $this->respondSuccess(
            request: $request,
            message: 'Relationship verification approved successfully.',
            status: 'approved',
        );
    }

    public function rejectRelationship(ReviewGuardianRelationshipVerificationRequest $request, ParentChildAccount $parentChildAccount): RedirectResponse|JsonResponse
    {
        $this->relationshipVerificationService->reject(
            $parentChildAccount,
            $request->user(),
            (string) $request->string('reason_code'),
            $request->filled('note') ? (string) $request->string('note') : null,
            (bool) $request->boolean('allow_resubmission', true),
        );

        return $this->respondSuccess(
            request: $request,
            message: $request->boolean('allow_resubmission', true)
                ? 'Relationship verification resubmission requested.'
                : 'Relationship verification rejected successfully.',
            status: $request->boolean('allow_resubmission', true) ? 'pending' : 'rejected',
        );
    }

    public function revokeRelationship(ReviewGuardianRelationshipVerificationRequest $request, ParentChildAccount $parentChildAccount): RedirectResponse|JsonResponse
    {
        $this->relationshipVerificationService->revoke(
            $parentChildAccount,
            $request->user(),
            (string) $request->string('reason_code'),
            $request->filled('note') ? (string) $request->string('note') : null,
        );

        return $this->respondSuccess(
            request: $request,
            message: 'Relationship verification revoked successfully.',
            status: 'rejected',
        );
    }

    public function relationshipDocument(ParentChildAccount $parentChildAccount, GuardianRelationshipVerificationDocument $document): BinaryFileResponse
    {
        abort_unless((int) $document->parent_child_account_id === (int) $parentChildAccount->id, 404);
        abort_if(empty($document->path) || ! Storage::disk($document->disk ?: 'local')->exists((string) $document->path), 404);

        return response()->file(
            Storage::disk($document->disk ?: 'local')->path((string) $document->path),
            ['Content-Disposition' => 'inline; filename="'.basename((string) $document->original_name).'"']
        );
    }

    public function approveChild(Request $request, ParentChildAccount $parentChildAccount): RedirectResponse|JsonResponse
    {
        if (! $this->isChildRegistrationVerification($parentChildAccount)) {
            return $this->respondError($request, 'Invitation relationships must be reviewed through the relationship verification queue.', 409);
        }

        if (! $this->isPendingStatus($parentChildAccount->verification_status)) {
            return $this->respondError($request, 'Decision already finalized. Only pending records can be moderated.', 409);
        }

        if ($parentChildAccount->requiresRelationshipVerification() && ! $parentChildAccount->hasVerifiedRelationshipRequirement()) {
            return $this->respondError($request, 'Approve the required relationship verification before approving this dependent relationship.', 409);
        }

        $this->service->approveChild($parentChildAccount);
        $parentChildAccount->refresh();

        return $this->respondSuccess(
            request: $request,
            message: 'Child verification approved successfully.',
            status: $this->normalizedStatus($parentChildAccount->verification_status),
            rejectionReason: $parentChildAccount->verification_rejection_reason,
        );
    }

    public function rejectChild(RejectChildVerificationRequest $request, ParentChildAccount $parentChildAccount): RedirectResponse|JsonResponse
    {
        if (! $this->isChildRegistrationVerification($parentChildAccount)) {
            return $this->respondError($request, 'Invitation relationships must be reviewed through the relationship verification queue.', 409);
        }

        if (! $this->isPendingStatus($parentChildAccount->verification_status)) {
            return $this->respondError($request, 'Decision already finalized. Only pending records can be moderated.', 409);
        }

        $reason = $this->composeRejectionReason(
            (string) $request->string('reason_code'),
            $request->filled('custom_reason') ? (string) $request->string('custom_reason') : null,
        );

        $this->service->rejectChild($parentChildAccount, $reason);
        $parentChildAccount->refresh();

        return $this->respondSuccess(
            request: $request,
            message: 'Child verification rejected successfully.',
            status: $this->normalizedStatus($parentChildAccount->verification_status),
            rejectionReason: $parentChildAccount->verification_rejection_reason,
        );
    }

    public function archiveParent(User $user): RedirectResponse
    {
        if (! $user->isParentRegistration()) {
            return back()->with('error', 'Selected account is not a guardian verification application.');
        }

        if ($user->trashed()) {
            return back()->with('info', 'Guardian verification application is already archived.');
        }

        $user->delete();

        return back()->with('success', 'Guardian verification application archived successfully.');
    }

    public function destroyParent(User $user): RedirectResponse
    {
        if (! $user->isParentRegistration()) {
            return back()->with('error', 'Selected account is not a guardian verification application.');
        }

        if (! in_array($this->normalizedStatus($user->parent_verification_status), [
            VerificationStatus::Approved->value,
            VerificationStatus::Rejected->value,
        ], true)) {
            return back()->with('error', 'Only reviewed guardian verification applications can be permanently deleted.');
        }

        $user->forceDelete();

        return back()->with('success', 'Guardian verification application permanently deleted.');
    }

    public function archiveChild(ParentChildAccount $parentChildAccount): RedirectResponse
    {
        if (! $this->isChildRegistrationVerification($parentChildAccount)) {
            return back()->with('error', 'Invitation relationships must be reviewed through the relationship verification queue.');
        }

        if ($parentChildAccount->trashed()) {
            return back()->with('info', 'Child verification application is already archived.');
        }

        $parentChildAccount->delete();

        return back()->with('success', 'Child verification application archived successfully.');
    }

    public function destroyChild(ParentChildAccount $parentChildAccount): RedirectResponse
    {
        if (! $this->isChildRegistrationVerification($parentChildAccount)) {
            return back()->with('error', 'Invitation relationships must be reviewed through the relationship verification queue.');
        }

        if (! in_array($this->normalizedStatus($parentChildAccount->verification_status), [
            VerificationStatus::Approved->value,
            VerificationStatus::Rejected->value,
        ], true)) {
            return back()->with('error', 'Only reviewed child verification applications can be permanently deleted.');
        }

        $parentChildAccount->forceDelete();

        return back()->with('success', 'Child verification application permanently deleted.');
    }

    private function composeRejectionReason(string $reasonCode, ?string $customReason = null): string
    {
        $reason = ParentChildModerationReason::tryFrom($reasonCode);

        $baseReason = $reason?->label() ?? str($reasonCode)->replace('_', ' ')->title()->toString();

        if ($reason === ParentChildModerationReason::Others && trim((string) $customReason) !== '') {
            $sanitizedCustomReason = $this->sanitizeReasonHtml((string) $customReason);

            if ($this->hasMeaningfulReasonText($sanitizedCustomReason)) {
                $baseReason = $sanitizedCustomReason;
            }
        }

        return $baseReason;
    }

    private function sanitizeReasonHtml(string $reason): string
    {
        $decoded = html_entity_decode($reason, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalizedSpacing = str_replace("\xC2\xA0", ' ', $decoded);
        $plainText = strip_tags($normalizedSpacing);
        $collapsedWhitespace = preg_replace('/\s+/u', ' ', $plainText);

        return trim((string) $collapsedWhitespace);
    }

    private function hasMeaningfulReasonText(string $reasonText): bool
    {
        return trim($reasonText) !== '';
    }

    private function parentApplications(string $status)
    {
        $query = User::query()
            ->where('is_parent_registration', true)
            ->with([
                'learnerProfile',
                'children.learnerProfile',
            ])
            ->latest();

        if ($status === VerificationStatus::Pending->value) {
            $query->where('parent_verification_status', VerificationStatus::Pending->value);
        } else {
            $query->where('parent_verification_status', $status);
        }

        return $query->get();
    }

    private function childApplications(string $status)
    {
        $query = ParentChildAccount::query()
            ->whereNotNull('verification_document_path')
            ->with([
                'parent.learnerProfile',
                'child.learnerProfile',
            ])
            ->latest();

        if ($status === VerificationStatus::Pending->value) {
            $query->where(function ($pendingQuery): void {
                $pendingQuery->where('verification_status', VerificationStatus::Pending->value)
                    ->orWhereNull('verification_status');
            });
        } else {
            $query->where('verification_status', $status);
        }

        return $query->get();
    }

    private function relationshipApplications(string $status)
    {
        return ParentChildAccount::query()
            ->with([
                'parent.learnerProfile',
                'child.learnerProfile',
                'verificationDocuments',
            ])
            ->where(function ($query): void {
                $query->whereIn('relationship_type', array_filter(
                    GuardianRelationshipTypes::values(),
                    fn (string $type): bool => GuardianRelationshipTypes::requiresVerification($type),
                ))
                    ->orWhereIn('relationship_verified_status', [
                        'pending',
                        'under_review',
                        'resubmission_required',
                        'verified',
                        'rejected',
                        'revoked',
                    ]);
            })
            ->when($status === VerificationStatus::Approved->value, fn ($query) => $query->where('relationship_verified_status', 'verified'))
            ->when($status === VerificationStatus::Rejected->value, fn ($query) => $query->whereIn('relationship_verified_status', ['rejected', 'revoked']))
            ->when($status === VerificationStatus::Pending->value, fn ($query) => $query->whereIn('relationship_verified_status', ['pending', 'under_review', 'resubmission_required']))
            ->latest('relationship_verification_submitted_at')
            ->get();
    }

    private function relationshipCountForStatus(string $status): int
    {
        return $this->relationshipApplications($status)->count();
    }

    private function isPendingStatus(?string $status): bool
    {
        return $this->normalizedStatus($status) === VerificationStatus::Pending->value;
    }

    private function normalizedStatus(?string $status): string
    {
        return $status ?: VerificationStatus::Pending->value;
    }

    private function isChildRegistrationVerification(ParentChildAccount $verification): bool
    {
        return filled($verification->verification_document_path);
    }

    private function isRelationshipReviewRecord(ParentChildAccount $relationship): bool
    {
        return $relationship->requiresRelationshipVerification()
            || in_array((string) $relationship->relationship_verified_status, [
                'pending',
                'under_review',
                'resubmission_required',
                'verified',
                'rejected',
                'revoked',
            ], true);
    }

    private function respondSuccess(
        Request $request,
        string $message,
        string $status,
        ?string $rejectionReason = null,
    ): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'status' => $status,
                'rejection_reason' => $rejectionReason,
            ]);
        }

        return back()->with('success', $message);
    }

    private function respondError(Request $request, string $message, int $httpStatus = 422): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
            ], $httpStatus);
        }

        return back()->with('error', $message);
    }

    private function idTypeLabel(User $user): string
    {
        if ($user->parent_id_type === 'other') {
            return $user->parent_id_type_other ?: 'Other';
        }

        return (string) data_get(config('guardian_identity.id_types', []), $user->parent_id_type.'.label', 'Not submitted');
    }
}
