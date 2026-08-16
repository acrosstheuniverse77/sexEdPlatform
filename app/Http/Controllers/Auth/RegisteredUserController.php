<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountInfoRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Step 1: Show personal information form.
     */
    public function create(): View
    {
        // Clear parent-flow contamination only. Do NOT clear pending_personal_info:
        // it is preserved so the user can navigate back from step 2 and see their data.
        session()->forget(['is_parent_registration', 'pending_parent_info']);

        return view('auth.register', [
            'personalInfo' => session('pending_personal_info', []),
        ]);
    }

    /**
     * Step 1 POST: Validate personal info, detect age, branch to correct flow.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $birthdate = Carbon::parse($validated['birthdate']);
        $age = $birthdate->age;

        if ($age < 13) {
            session([
                'pending_child_registration'   => $validated,
                'child_registration_timestamp' => now()->timestamp,
                'is_parent_registration'       => true,
            ]);

            return redirect()->route('parent.registration.required')
                ->with('info', 'Children under 13 require a guardian to create their account.');
        }

        // Store personal info in session and advance to account info step
        session(['pending_personal_info' => array_merge($validated, ['age' => $age])]);

        return redirect()->route('register.account');
    }

    /**
     * Step 2: Show account information form (email + password).
     */
    public function showAccount(): View|RedirectResponse
    {
        if (! session('pending_personal_info')) {
            return redirect()->route('register');
        }

        return view('auth.register-account');
    }

    /**
     * Step 2 POST: Create the user account from session + submitted data.
     */
    public function storeAccount(AccountInfoRequest $request): RedirectResponse
    {
        $personal = session('pending_personal_info');

        if (! $personal) {
            return redirect()->route('register');
        }

        $account = $request->validated();

        $user = User::create([
            'name'           => trim($personal['first_name'] . ' ' . $personal['last_name']),
            'first_name'     => $personal['first_name'],
            'middle_initial' => $personal['middle_initial'] ?? null,
            'last_name'      => $personal['last_name'],
            'suffix'         => $personal['suffix'] ?? null,
            'email'          => $account['email'],
            'birthdate'      => $personal['birthdate'],
            'age'            => $personal['age'],
            'password'       => Hash::make($account['password']),
        ]);

        Role::findOrCreate('learner', 'web');
        $user->assignRole('learner');

        $verificationDispatchFailed = false;

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            $verificationDispatchFailed = true;

            Log::warning('Verification email dispatch failed during registration.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        session()->forget('pending_personal_info');

        Auth::login($user);

        if ($verificationDispatchFailed) {
            return redirect()->route('verification.notice')
                ->with('warning', 'Account created, but we could not send the verification email yet. Please click "Resend verification email".');
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Registration successful! Please verify your email address.');
    }
}
