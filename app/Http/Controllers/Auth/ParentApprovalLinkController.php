<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParentApprovalLinkController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        if (! $currentUser) {
            return redirect()->route('login');
        }

        $user = User::findOrFail((int) $request->route('id'));

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (! $user->is_parent_registration || $user->parent_verification_status !== 'approved') {
            return redirect()->route('login')
                ->with('error', 'This approval link is no longer valid.');
        }

        if ($currentUser->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            return redirect()->route('parent.verification.status')->with(
                'error',
                'Please sign in with the account associated with this approval link.'
            );
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('info', 'Please verify your email first before continuing.');
        }

        if (! $user->hasCompletedGuardianOnboarding()) {
            return redirect()->route('guardian.onboarding.show')
                ->with('success', 'Your guardian verification was approved. Complete onboarding to continue.');
        }

        return redirect()->route('learner.dashboard')
            ->with('success', 'Your guardian verification is approved.')
            ->with('show_parent_approved_dashboard_modal', true);
    }
}
