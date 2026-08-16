<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            if ($user->isParentRegistration() && ! $user->parent_verification_status) {
                return redirect()->route('guardian.verification.create');
            }

            if ($user->isParentRegistration() && ! $user->isParentVerificationApproved()) {
                return redirect()->route('guardian.verification.status');
            }

            if ($user->isParentRegistration() && $user->isParentVerificationApproved() && ! $user->hasCompletedGuardianOnboarding()) {
                return redirect()->route('guardian.onboarding.show');
            }

            if ($user->isParentRegistration() && $user->isParentVerificationApproved() && $user->hasCompletedGuardianOnboarding()) {
                return redirect()->route('learner.dashboard')
                    ->with('success', 'Guardian verification approved.')
                    ->with('show_parent_approved_dashboard_modal', true);
            }

            if ($user->hasCompletedProfile()) {
                if ($user->can('access instructor panel')) {
                    return redirect()->route('instructor.dashboard');
                }

                return redirect()->route('learner.dashboard');
            }

            return redirect()->route('profile.complete');
        }

        return view('auth.verify-email', [
            'showSuccess'    => false,
            'learnerProfile' => null,
            'cities'         => collect(),
            'barangays'      => collect(),
        ]);
    }
}
