<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Schoolees\Psgc\Models\Barangay;
use Schoolees\Psgc\Models\City;

class GuardianOnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $guardian = $request->user();

        if (! $guardian->isParentRegistration()) {
            return redirect()->route('learner.dashboard');
        }

        if (! $guardian->isParentVerificationApproved()) {
            return redirect()->route('guardian.verification.status');
        }

        if ($guardian->hasCompletedGuardianOnboarding()) {
            return redirect()->route('learner.dashboard');
        }

        if ($guardian->guardian_onboarding_status !== 'in_progress') {
            $guardian->forceFill([
                'guardian_onboarding_status' => 'in_progress',
                'guardian_onboarding_started_at' => $guardian->guardian_onboarding_started_at ?? now(),
            ])->save();
        }

        $learnerProfile = $guardian->learnerProfile;
        $cities = City::where('province_code', '402100000')->orderBy('name')->get();
        $barangays = $learnerProfile?->city_code
            ? Barangay::where('city_code', $learnerProfile->city_code)->orderBy('name')->get()
            : collect();

        return view('auth.guardian-onboarding', compact('learnerProfile', 'cities', 'barangays'));
    }

    public function complete(Request $request): RedirectResponse
    {
        $guardian = $request->user();

        if (! $guardian->isParentRegistration()) {
            return redirect()->route('learner.dashboard');
        }

        if (! $guardian->isParentVerificationApproved()) {
            return redirect()->route('guardian.verification.status');
        }

        $request->merge([
            'username' => strtolower(trim((string) $request->input('username'))),
        ]);

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('learner_profiles', 'username')->ignore($guardian->learnerProfile?->id),
            ],
            'city_code' => ['required', 'string', 'exists:cities,code'],
            'barangay_code' => ['required', 'string', 'exists:barangays,code'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'next_action' => ['required', Rule::in(['dependent', 'dashboard'])],
        ], [
            'username.regex' => 'Use only lowercase letters, numbers, underscores, and hyphens.',
            'username.unique' => 'That username is already taken.',
            'city_code.required' => 'Select your city or municipality.',
            'barangay_code.required' => 'Select your barangay.',
        ]);

        DB::transaction(function () use ($guardian, $request, $validated): void {
            $barangay = Barangay::where('code', $validated['barangay_code'])->firstOrFail();
            $profile = $guardian->learnerProfile;

            if ($request->boolean('remove_avatar') && $profile?->avatar_path) {
                Storage::disk('public')->delete((string) $profile->avatar_path);
                $validated['avatar_path'] = null;
            }

            if ($request->hasFile('avatar')) {
                if ($profile?->avatar_path) {
                    Storage::disk('public')->delete((string) $profile->avatar_path);
                }

                $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
            }

            $guardian->learnerProfile()->updateOrCreate(
                ['user_id' => $guardian->id],
                [
                    'username' => $validated['username'],
                    'birthdate' => $guardian->birthdate,
                    'city_code' => $validated['city_code'],
                    'barangay_code' => $validated['barangay_code'],
                    'barangay' => $barangay->name,
                    'province_code' => '402100000',
                    'bio' => $validated['bio'] ?? null,
                    'avatar_path' => array_key_exists('avatar_path', $validated) ? $validated['avatar_path'] : $profile?->avatar_path,
                    'is_parent_account' => true,
                    'requires_parental_consent' => false,
                ]
            );

            $guardian->forceFill([
                'guardian_onboarding_status' => 'completed',
                'guardian_onboarding_completed_at' => now(),
            ])->save();
        });

        $redirectRoute = $validated['next_action'] === 'dependent'
            ? 'parent.create-child'
            : 'learner.dashboard';

        return redirect()->route($redirectRoute)
            ->with('success', 'Welcome to Conscious Connections! Your Guardian account is now fully configured.');
    }
}
