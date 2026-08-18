<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Services\SubscriptionService;
use App\Support\SubscriptionFeatureKeys;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Barangay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileCompletionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    /**
     * Show the profile completion form.
     */
    public function show()
    {
        $user = Auth::user();

        if ($user->isParentRegistration() && $user->isParentVerificationApproved() && ! $user->hasCompletedGuardianOnboarding()) {
            return redirect()->route('guardian.onboarding.show');
        }

        // If profile already completed, redirect to dashboard
        if ($user->hasCompletedProfile()) {
            if ($user->isParentRegistration() && $user->isParentVerificationApproved()) {
                return redirect()->route('learner.dashboard');
            }

            if ($user->can('access instructor panel')) {
                return redirect()->route('instructor.dashboard');
            }
            return redirect()->route('learner.dashboard');
        }

        $learnerProfile = $user->learnerProfile;
        
        // Get Cavite cities/municipalities only
        // Cavite province code: 402100000 (PSGC format)
        $cities = City::where('province_code', '402100000')
            ->orderBy('name')
            ->get();
        
        // If editing and has city, load barangays
        $barangays = [];
        if ($learnerProfile && $learnerProfile->city_code) {
            $barangays = Barangay::where('city_code', $learnerProfile->city_code)
                ->orderBy('name')
                ->get();
        }

        return view('profile.complete', compact('learnerProfile', 'cities', 'barangays'));
    }

    /**
     * Live username validator for profile completion and profile edits.
     */
    public function checkUsername(Request $request)
    {
        $user = Auth::user();

        if ($user->isParentRegistration() && $user->isParentVerificationApproved() && ! $user->hasCompletedGuardianOnboarding()) {
            return redirect()->route('guardian.onboarding.show');
        }
        $rawUsername = (string) $request->query('username', '');
        $username = strtolower(trim($rawUsername));

        if ($username === '') {
            return response()->json([
                'available' => false,
                'valid_format' => false,
                'message' => 'Enter a username to continue.',
            ], 422);
        }

        if (strlen($username) < 3 || strlen($username) > 30) {
            return response()->json([
                'available' => false,
                'valid_format' => false,
                'message' => 'Username must be between 3 and 30 characters.',
            ], 422);
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $username)) {
            return response()->json([
                'available' => false,
                'valid_format' => false,
                'message' => 'Use only lowercase letters, numbers, underscores, and hyphens.',
            ], 422);
        }

        $currentProfileId = $user->learnerProfile?->id;

        $isTaken = LearnerProfile::query()
            ->whereRaw('LOWER(username) = ?', [$username])
            ->when($currentProfileId, fn ($query) => $query->where('id', '!=', $currentProfileId))
            ->exists();

        if ($isTaken) {
            return response()->json([
                'available' => false,
                'valid_format' => true,
                'message' => 'That username is already taken.',
            ]);
        }

        return response()->json([
            'available' => true,
            'valid_format' => true,
            'message' => 'Username is available.',
        ]);
    }

    /**
     * Store the completed profile.
     * Note: Birthdate is already set during registration, so we don't collect it here.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Validate the request (removed birthdate and grade_level - using age brackets instead)
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('learner_profiles', 'username')->ignore($user->learnerProfile?->id),
            ],
            'gender' => 'nullable|in:male,female,prefer_not_to_say',
            'city_code' => 'required|string|exists:cities,code',
            'barangay_code' => 'required|string|exists:barangays,code',
            'bio' => 'nullable|string|max:500',
        ]);
        
        // Automatically set Cavite province code
        $validated['province_code'] = '402100000';
        
        // Copy birthdate from User model (stored during registration)
        $validated['birthdate'] = $user->birthdate;
        
        // Parent accounts are marked at registration and require admin approval.
        $isParentRegistration = $user->isParentRegistration();
        if ($isParentRegistration) {
            $validated['is_parent_account'] = true;

            if (! $user->isParentVerificationApproved()) {
                return redirect()->route('parent.verification.status')
                    ->with('warning', 'Your guardian account is pending verification.');
            }
        }
        
        // Get city and barangay names for display purposes
        $city = City::where('code', $validated['city_code'])->first();
        $barangay = Barangay::where('code', $validated['barangay_code'])->first();
        
        $validated['barangay'] = $barangay->name;

        // Create or update learner profile
        $learnerProfile = $user->learnerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // If parent account, continue to dashboard and show approved-parent modal
        if ($learnerProfile->is_parent_account) {
            return redirect()->route('learner.dashboard')
                ->with('success', 'Profile completed! Your guardian account is approved.')
                ->with('show_parent_approved_dashboard_modal', true);
        }

        // Redirect based on user role
        if ($user->can('access instructor panel')) {
            return redirect()->route('instructor.dashboard')
                ->with('success', 'Profile completed successfully! Welcome to the instructor dashboard.');
        }

        $redirect = redirect()->route('learner.dashboard')
            ->with('success', 'Profile completed successfully! Welcome to the learning platform.');

        if ($learnerProfile->isAdult()) {
            $redirect->with('force_light_mode_once', true);
        }

        return $redirect;
    }

    /**
     * Show the profile edit form for learners.
     * Profile editing is now managed via modal on the dashboard.
     */
    public function edit()
    {
        return redirect()->route('learner.dashboard', [
            'open_edit_profile' => 1,
        ]);
    }

    /**
     * Update the learner profile.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $learnerProfile = $user->learnerProfile;

        if (!$learnerProfile) {
            return redirect()->route('profile.complete');
        }

        // Validate (excluding grade_level and gender from editing)
        $validated = $request->validate([
            'username' => 'nullable|string|min:3|max:30|unique:learner_profiles,username,' . $learnerProfile->id,
            'school' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 2MB max
        ]);

        // Backward compatibility for clients still sending "about"
        if ($request->filled('about') && !isset($validated['bio'])) {
            $validated['bio'] = $request->input('about');
        }

        // Handle username change with premium/free logic
        if ($request->filled('username') && $request->username !== $learnerProfile->username) {
            $hasUnlimitedUsernameChanges = $this->subscriptionService->hasFeature(
                $user,
                SubscriptionFeatureKeys::UNLIMITED_USERNAME_CHANGE
            );
            
            if (!$hasUnlimitedUsernameChanges) {
                // Free users: Check 7-day limit
                if ($learnerProfile->username_changed_at) {
                    $daysSinceChange = now()->diffInDays($learnerProfile->username_changed_at);
                    if ($daysSinceChange < 7) {
                        $daysRemaining = 7 - $daysSinceChange;
                        $nextChangeDate = $learnerProfile->username_changed_at->addDays(7)->format('M d, Y');
                        $message = "You can change your username again in {$daysRemaining} day(s) (on {$nextChangeDate}). Upgrade to a plan with unlimited username changes to remove this cooldown.";
                        if ($request->expectsJson()) {
                            return response()->json(['success' => false, 'errors' => ['username' => [$message]]], 422);
                        }
                        return back()->with('error', $message);
                    }
                }
            }
            
            // Update username and timestamp
            $validated['username_changed_at'] = now();
        } else {
            // Remove username from validated if not changing
            unset($validated['username']);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($learnerProfile->avatar_path && \Storage::disk('public')->exists($learnerProfile->avatar_path)) {
                \Storage::disk('public')->delete($learnerProfile->avatar_path);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_path'] = $avatarPath;
        }

        $learnerProfile->update($validated);

        if ($request->expectsJson()) {
            $fresh = $learnerProfile->fresh();
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'data' => [
                    'username'   => $fresh->username,
                    'bio'        => $fresh->bio,
                    'avatar_url' => $fresh->avatar_path
                        ? asset('storage/' . $fresh->avatar_path) : null,
                ],
            ]);
        }

        return back()->with('success', 'Profile updated successfully!');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        $user = Auth::user();
        $user->password = bcrypt($request->password);
        $user->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Password updated successfully!']);
        }

        return back()->with('success', 'Password updated successfully!');
    }

    /**
     * Delete account
     */
    public function deleteAccount(Request $request)
    {
        $message = 'Account deletion is disabled for learner self-service. Please contact support or an administrator for account removal assistance.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'general' => [$message],
                ],
            ], 403);
        }

        return back()->with('error', $message);
    }
}
