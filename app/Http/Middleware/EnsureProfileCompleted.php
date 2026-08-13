<?php

namespace App\Http\Middleware;

use App\Models\ParentChildAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isLearner() && $user->isParentRegistration()) {
            if (! $user->parent_verification_status) {
                return redirect()->route('guardian.verification.create');
            }

            if ($user->isParentVerificationPending() || $user->isParentVerificationRejected()) {
                return redirect()->route('guardian.verification.status');
            }

            if ($user->isParentVerificationApproved() && ! $user->hasCompletedGuardianOnboarding()) {
                if (! $request->routeIs('guardian.onboarding.*')) {
                    return redirect()->route('guardian.onboarding.show');
                }
            }
        }

        if ($user && $user->isLearner()) {
            $childVerification = ParentChildAccount::query()
                ->where('child_user_id', $user->id)
                ->whereNotNull('verification_document_path')
                ->latest('id')
                ->first();

            if ($childVerification && in_array($childVerification->verification_status, ['pending', 'rejected'], true)) {
                return redirect()->route('child.verification.status');
            }
        }

        // If user is a learner and hasn't completed their profile
        if ($user && $user->isLearner() && !$user->hasCompletedProfile()) {
            // Allow access to profile completion routes
            if (!$request->routeIs('profile.complete') && !$request->routeIs('profile.store')) {
                return redirect()->route('profile.complete')
                    ->with('warning', 'Please complete your profile to access learning modules.');
            }
        }

        return $next($request);
    }
}
