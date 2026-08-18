<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\InstructorAuthController;
use App\Http\Controllers\Auth\ParentRegistrationController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ParentApprovalLinkController;
use App\Http\Controllers\Auth\GuardianIdentityVerificationController;
use App\Http\Controllers\Auth\GuardianOnboardingController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    // Step 2: Account info (email + password)
    Route::get('register/account', [RegisteredUserController::class, 'showAccount'])
        ->name('register.account');

    Route::post('register/account', [RegisteredUserController::class, 'storeAccount']);

    // Parent registration routes
    Route::get('parent-registration-required', [ParentRegistrationController::class, 'requiredPage'])
        ->name('parent.registration.required');
    
    Route::get('parent/register', [ParentRegistrationController::class, 'create'])
        ->name('parent.register');
    
    Route::post('parent/register', [ParentRegistrationController::class, 'storePersonal'])
        ->name('parent.register.store');

    Route::post('parent/register/temp-upload', [ParentRegistrationController::class, 'uploadParentTempDocument'])
        ->name('parent.register.temp-upload');

    Route::delete('parent/register/temp-upload', [ParentRegistrationController::class, 'removeParentTempDocument'])
        ->name('parent.register.temp-upload.remove');

    // Step 2: Parent account credentials
    Route::get('parent/register-account', [ParentRegistrationController::class, 'createAccount'])
        ->name('parent.register.account');

    Route::post('parent/register-account', [ParentRegistrationController::class, 'storeAccount'])
        ->name('parent.register.account.store');

    // Learner login
    Route::get('login', function () {
        return view('auth.learner-login');
    })->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('instructor/login', function () {
        return redirect()->route('login');
    })->name('instructor.login');

    Route::post('instructor/login', function () {
        return redirect()->route('login');
    })->name('instructor.login.submit');

    // Secure admin login (hidden route with hash for security)
    Route::get('secure-panel-access', [AdminAuthController::class, 'showLoginForm'])
        ->name('admin.login');
    
    Route::post('secure-panel-access', [AdminAuthController::class, 'login'])
        ->name('admin.login.submit');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('parent/approval/{id}/{hash}', ParentApprovalLinkController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('parent.verification.approval-link');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('email/verification-status', function (Request $request) {
        return response()->json([
            'verified' => (bool) $request->user()?->hasVerifiedEmail(),
            'profile_completed' => (bool) $request->user()?->hasCompletedProfile(),
        ]);
    })->name('verification.status');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
    
    // Instructor logout
    Route::post('instructor/logout', [InstructorAuthController::class, 'logout'])
        ->name('instructor.logout');
    
    // Admin logout
    Route::post('admin/logout', [AdminAuthController::class, 'logout'])
        ->name('admin.logout');

    Route::get('parent/verification-status', [ParentRegistrationController::class, 'verificationStatus'])
        ->name('parent.verification.status');

    Route::post('parent/verification-status/resubmit', [ParentRegistrationController::class, 'resubmitParentVerification'])
        ->name('parent.verification.resubmit');

    Route::get('guardian/verification', [GuardianIdentityVerificationController::class, 'create'])
        ->name('guardian.verification.create');

    Route::post('guardian/verification', [GuardianIdentityVerificationController::class, 'store'])
        ->name('guardian.verification.store');

    Route::get('guardian/verification/status', [GuardianIdentityVerificationController::class, 'status'])
        ->name('guardian.verification.status');

    Route::get('guardian/onboarding', [GuardianOnboardingController::class, 'show'])
        ->name('guardian.onboarding.show');

    Route::post('guardian/onboarding', [GuardianOnboardingController::class, 'complete'])
        ->name('guardian.onboarding.complete');

    Route::get('child/verification-status', [ParentRegistrationController::class, 'childVerificationStatus'])
        ->name('child.verification.status');

    // Parent routes (verified emails only)
    Route::middleware(['verified', 'guardian.verified'])->group(function () {
        Route::get('parent/create-child', [ParentRegistrationController::class, 'createChildForm'])
            ->name('parent.create-child');

        Route::post('parent/create-child', [ParentRegistrationController::class, 'storeChildInfo'])
            ->name('parent.create-child.store');

        Route::get('parent/create-child/location', [ParentRegistrationController::class, 'childLocationForm'])
            ->name('parent.create-child.location');

        Route::post('parent/create-child/location', [ParentRegistrationController::class, 'storeChildLocation'])
            ->name('parent.create-child.location.store');

        Route::get('parent/create-child/credentials', [ParentRegistrationController::class, 'childCredentialsForm'])
            ->name('parent.create-child.credentials');

        Route::post('parent/create-child/credentials/temp-upload', [ParentRegistrationController::class, 'uploadChildTempDocument'])
            ->name('parent.create-child.credentials.temp-upload');

        Route::delete('parent/create-child/credentials/temp-upload', [ParentRegistrationController::class, 'removeChildTempDocument'])
            ->name('parent.create-child.credentials.temp-upload.remove');

        Route::post('parent/create-child/credentials', [ParentRegistrationController::class, 'storeChildCredentials'])
            ->name('parent.create-child.credentials.store');

        Route::get('parent/create-child/validation', [ParentRegistrationController::class, 'childValidationForm'])
            ->name('parent.create-child.validation');

        Route::post('parent/create-child/validation', [ParentRegistrationController::class, 'storeChildValidation'])
            ->name('parent.create-child.validation.store');

        Route::get('parent/create-child/relationship-verification', [ParentRegistrationController::class, 'childRelationshipVerificationForm'])
            ->name('parent.create-child.relationship-verification');

        Route::post('parent/create-child/relationship-verification', [ParentRegistrationController::class, 'storeChildRelationshipVerification'])
            ->name('parent.create-child.relationship-verification.store');

        Route::get('parent/create-child/done', [ParentRegistrationController::class, 'childDone'])
            ->name('parent.create-child.done');

        Route::get('parent/children', [ParentRegistrationController::class, 'childrenIndex'])
            ->name('parent.children.index');

        Route::post('parent/children/{child}/verification/resubmit', [ParentRegistrationController::class, 'resubmitChildVerification'])
            ->name('parent.children.verification.resubmit');
    });
});
