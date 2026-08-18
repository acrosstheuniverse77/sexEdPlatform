<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardianVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isParentRegistration()) {
            return $next($request);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->isParentVerificationApproved() && $user->hasCompletedGuardianOnboarding()) {
            return $next($request);
        }

        if ($user->isParentVerificationApproved()) {
            return $request->routeIs('guardian.onboarding.*')
                ? $next($request)
                : redirect()->route('guardian.onboarding.show');
        }

        return redirect()->route(
            $user->parent_verification_status ? 'guardian.verification.status' : 'guardian.verification.create'
        );
    }
}
