<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreGuardianIdentityVerificationRequest;
use App\Services\ParentChildVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianIdentityVerificationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isParentRegistration()) {
            return redirect()->route('learner.dashboard');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->isParentVerificationPending() || $user->isParentVerificationApproved()) {
            return redirect()->route('guardian.verification.status');
        }

        return view('auth.guardian-verification', [
            'idTypes' => $this->idTypes(),
            'idTypeRequirements' => config('guardian_identity.id_types', []),
        ]);
    }

    public function store(
        StoreGuardianIdentityVerificationRequest $request,
        ParentChildVerificationService $verificationService,
    ): RedirectResponse {
        $frontPath = $request->file('government_id_front')->store(
            'guardian-verifications/'.$request->user()->id,
            'local'
        );

        $backPath = $request->hasFile('government_id_back')
            ? $request->file('government_id_back')->store('guardian-verifications/'.$request->user()->id, 'local')
            : null;

        $verificationService->submitParentIdentity(
            parent: $request->user(),
            idType: (string) $request->string('government_id_type'),
            idTypeOther: $request->filled('government_id_type_other') ? (string) $request->string('government_id_type_other') : null,
            frontPath: (string) $frontPath,
            backPath: $backPath,
        );

        return redirect()->route('guardian.verification.status')
            ->with('success', 'Your Guardian verification has been submitted successfully.');
    }

    public function status(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isParentRegistration()) {
            return redirect()->route('learner.dashboard');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->parent_verification_status) {
            return redirect()->route('guardian.verification.create');
        }

        if ($user->isParentVerificationApproved() && ! $user->hasCompletedGuardianOnboarding()) {
            return redirect()->route('guardian.onboarding.show');
        }

        return view('auth.parent-verification-status', [
            'user' => $user,
            'isApproved' => $user->isParentVerificationApproved(),
        ]);
    }

    private function idTypes(): array
    {
        return collect(config('guardian_identity.id_types', []))
            ->mapWithKeys(fn (array $meta, string $value): array => [$value => (string) ($meta['label'] ?? $value)])
            ->all();
    }
}
