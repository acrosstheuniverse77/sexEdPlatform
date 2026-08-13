<?php

namespace App\Http\Controllers\Auth;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResubmitChildVerificationRequest;
use App\Http\Requests\Auth\RemoveTempUploadRequest;
use App\Http\Requests\Auth\UploadChildTempDocumentRequest;
use App\Http\Requests\Auth\UploadParentTempDocumentRequest;
use App\Notifications\Admin\ChildVerificationRequestSubmittedNotification;
use App\Notifications\Admin\ParentVerificationRequestSubmittedNotification;
use App\Models\User;
use App\Models\ParentChildAccount;
use App\Services\Auth\RegistrationTempUploadService;
use App\Services\GuardianRelationshipVerificationService;
use App\Services\ParentChildInvitationService;
use App\Services\ParentChildVerificationService;
use App\Support\GuardianRelationshipTypes;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class ParentRegistrationController extends Controller
{
    /**
     * Show the parent registration required page
     */
    public function requiredPage(): View
    {
        return view('auth.parent-registration-required');
    }

    /**
     * Show the parent registration form (Step 1 — personal info)
     */
    public function create(): View
    {
        return view('auth.parent-register', [
            'parentInfo' => session('pending_parent_info', []),
            'hasGovernmentIdUpload' => false,
            'tempGovernmentIdUpload' => null,
        ]);
    }

    public function uploadParentTempDocument(
        UploadParentTempDocumentRequest $request,
        RegistrationTempUploadService $tempUploadService
    ): JsonResponse {
        $upload = $tempUploadService->store('parent', 'government_id', $request->file('government_id'));
        $upload['preview_url'] = asset('storage/'.$upload['path']);

        return response()->json([
            'message' => 'Temporary upload saved.',
            'upload' => $upload,
        ]);
    }

    public function removeParentTempDocument(
        RemoveTempUploadRequest $request,
        RegistrationTempUploadService $tempUploadService
    ): JsonResponse {
        $tempUploadService->remove('parent', 'government_id');

        $pendingInfo = session('pending_parent_info', []);
        if (is_array($pendingInfo) && array_key_exists('government_id_path', $pendingInfo)) {
            unset($pendingInfo['government_id_path']);
            session(['pending_parent_info' => $pendingInfo]);
        }

        return response()->json([
            'message' => 'Temporary upload removed.',
        ]);
    }

    public function uploadChildTempDocument(
        UploadChildTempDocumentRequest $request,
        RegistrationTempUploadService $tempUploadService
    ): JsonResponse {
        if ($errorResponse = $this->ensureApprovedParentForJson()) {
            return $errorResponse;
        }

        $upload = $tempUploadService->store('child', 'verification_document', $request->file('verification_document'));
        $upload['preview_url'] = asset('storage/'.$upload['path']);

        return response()->json([
            'message' => 'Temporary upload saved.',
            'upload' => $upload,
        ]);
    }

    public function removeChildTempDocument(
        RemoveTempUploadRequest $request,
        RegistrationTempUploadService $tempUploadService
    ): JsonResponse {
        if ($errorResponse = $this->ensureApprovedParentForJson()) {
            return $errorResponse;
        }

        $tempUploadService->remove('child', 'verification_document');

        return response()->json([
            'message' => 'Temporary upload removed.',
        ]);
    }

    /**
     * Store personal info in session and redirect to step 2 (credentials)
     */
    public function storePersonal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name'   => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'middle_initial' => ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z.\s]+$/'],
            'last_name'    => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'suffix'       => ['nullable', 'string', 'in:Jr.,Sr.,II,III,IV,V'],
            'birthdate'    => [
                'required',
                'date',
                'before:' . now()->subYears(18)->format('Y-m-d'),
            ],
        ]);

        session(['pending_parent_info' => $validated]);

        return redirect()->route('parent.register.account');
    }

    /**
     * Show step 2 — account credentials
     */
    public function createAccount(): View|RedirectResponse
    {
        if (!session('pending_parent_info')) {
            return redirect()->route('parent.register');
        }

        return view('auth.parent-register-account');
    }

    /**
     * Create the parent account from session + credentials
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $personalInfo = session('pending_parent_info');
        if (!$personalInfo) {
            return redirect()->route('parent.register')
                ->with('error', 'Session expired. Please start over.');
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:users,email',
                'ends_with:@gmail.com',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        $birthdate = Carbon::parse($personalInfo['birthdate']);

        if ($birthdate->age < 18) {
            session()->forget('pending_parent_info');
            return redirect()->route('parent.register')
                ->with('error', 'You must be at least 18 years old to register as a guardian.');
        }

        $parent = User::create([
            'name'           => trim($personalInfo['first_name'] . ' ' . $personalInfo['last_name']),
            'first_name'     => $personalInfo['first_name'],
            'middle_initial' => $personalInfo['middle_initial'] ?? null,
            'last_name'      => $personalInfo['last_name'],
            'suffix'         => $personalInfo['suffix'] ?? null,
            'email'          => strtolower($validated['email']),
            'birthdate'      => $personalInfo['birthdate'],
            'age'            => $birthdate->age,
            'password'       => Hash::make($validated['password']),
            'is_parent_registration' => true,
        ]);

        Role::findOrCreate('learner', 'web');
        $parent->assignRole('learner');

        $verificationDispatchFailed = false;

        try {
            event(new Registered($parent));
        } catch (\Throwable $e) {
            $verificationDispatchFailed = true;

            Log::warning('Verification email dispatch failed during guardian registration.', [
                'user_id' => $parent->id,
                'email' => $parent->email,
                'error' => $e->getMessage(),
            ]);
        }

        session()->forget('pending_parent_info');
        session(['is_parent_registration' => true]);

        Auth::login($parent);

        if ($verificationDispatchFailed) {
            return redirect()->route('verification.notice')
                ->with('warning', 'Guardian account submitted and pending admin review, but verification email could not be sent yet. Please click "Resend verification email".');
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Guardian account created! Please verify your email, then submit identity verification.');
    }

    /**
     * Handle parent registration request (legacy — kept for backward compat)
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->storePersonal($request);
    }

    /**
     * Step 1: Show create child form (personal info)
     */
    public function createChildForm(): View|RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        // Clear stale in-progress data from an abandoned session, but keep
        // pending_child_registration — it holds the original child's info for pre-fill
        session()->forget(['child_step1', 'child_step2', 'child_created_name']);

        return view('auth.create-child-account', [
            'pendingChild' => session('pending_child_registration', []),
            'relationshipOptions' => GuardianRelationshipTypes::options(),
        ]);
    }

    /**
     * Step 1 POST: Save child personal info to session
     */
    public function storeChildInfo(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $validated = $request->validate([
            'first_name'    => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'middle_initial'=> ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z.\s]+$/'],
            'last_name'     => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'suffix'        => ['nullable', 'string', 'in:Jr.,Sr.,II,III,IV,V'],
            'birthdate'     => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'gender'        => ['required', 'in:male,female,prefer_not_to_say'],
            'relationship_type' => ['required', Rule::in(GuardianRelationshipTypes::values())],
            'relationship_custom' => ['nullable', 'required_if:relationship_type,other', 'string', 'max:120'],
        ]);

        $birthdate = Carbon::parse($validated['birthdate']);
        session(['child_step1' => array_merge($validated, ['age' => $birthdate->age])]);

        return redirect()->route('parent.create-child.location');
    }

    /**
     * Step 2: Show location form
     */
    public function childLocationForm(): View|RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        if (!session('child_step1')) {
            return redirect()->route('parent.create-child');
        }

        $cities = \Schoolees\Psgc\Models\City::where('province_code', '402100000')
            ->orderBy('name')->get();

        $parentProfile  = Auth::user()?->learnerProfile;
        $preFilledCity  = $parentProfile?->city_code;
        $preFilledBarangay = $parentProfile?->barangay_code;

        return view('auth.child.step2-location', compact('cities', 'preFilledCity', 'preFilledBarangay'));
    }

    /**
     * Step 2 POST: Save location to session
     */
    public function storeChildLocation(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        if (!session('child_step1')) {
            return redirect()->route('parent.create-child');
        }

        $validated = $request->validate([
            'city_code'     => ['required', 'string', 'exists:cities,code'],
            'barangay_code' => [
                'required',
                'string',
                'exists:barangays,code',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $cityCode = (string) $request->input('city_code');
                    $barangayCode = (string) $value;

                    $isBarangayInCity = \Schoolees\Psgc\Models\Barangay::query()
                        ->where('code', $barangayCode)
                        ->where('city_code', $cityCode)
                        ->exists();

                    if (! $isBarangayInCity) {
                        $fail('Selected barangay does not belong to the selected city.');
                    }
                },
            ],
        ]);

        session(['child_step2' => $validated]);

        return redirect()->route('parent.create-child.credentials');
    }

    /**
     * Step 3: Show credentials form
     */
    public function childCredentialsForm(): View|RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        if (!session('child_step1') || !session('child_step2')) {
            return redirect()->route('parent.create-child');
        }

        $step1 = session('child_step1');
        $parentEmail = (string) Auth::user()?->email;
        $suggestedEmail = null;
        if (preg_match('/^(.+)@gmail\.com$/i', $parentEmail, $matches)) {
            $childFirstName = strtolower(preg_replace('/[^a-z0-9]/', '', $step1['first_name'] ?? ''));
            if ($childFirstName) {
                $suggestedEmail = $matches[1] . '+' . $childFirstName . '@gmail.com';
            }
        }

        return view('auth.child.step3-credentials', [
            'step1' => $step1,
            'suggestedEmail' => $suggestedEmail,
        ]);
    }

    /**
     * Step 3 POST: Create the child account
     */
    public function storeChildCredentials(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $step1 = session('child_step1');
        $step2 = session('child_step2');

        if (!$step1 || !$step2) {
            return redirect()->route('parent.create-child');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'unique:learner_profiles,username', 'regex:/^[a-z0-9_-]+$/'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        session(['child_step3' => [
            'username' => $validated['username'],
            'password' => $validated['password'],
        ]]);

        return redirect()->route('parent.create-child.validation');
    }

    public function childValidationForm(): View|RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        if (!session('child_step1') || !session('child_step2') || !session('child_step3')) {
            return redirect()->route('parent.create-child');
        }

        $tempUpload = app(RegistrationTempUploadService::class)->get('child', 'verification_document');
        if (is_array($tempUpload) && !empty($tempUpload['path'])) {
            $tempUpload['preview_url'] = asset('storage/'.$tempUpload['path']);
        }

        return view('auth.child.step4-validation', [
            'tempChildVerificationUpload' => $tempUpload,
            'hasChildVerificationUpload' => !empty($tempUpload['path']),
        ]);
    }

    public function storeChildValidation(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $step1 = session('child_step1');
        if (! $step1 || ! session('child_step2') || ! session('child_step3')) {
            return redirect()->route('parent.create-child');
        }

        $tempUploadService = app(RegistrationTempUploadService::class);

        if ($request->hasFile('verification_document')) {
            $request->validate([
                'verification_document' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
            $tempUploadService->store('child', 'verification_document', $request->file('verification_document'));
        }

        $tempUpload = $tempUploadService->get('child', 'verification_document');
        if (!is_array($tempUpload) || empty($tempUpload['path'])) {
            return back()
                ->withErrors(['verification_document' => 'Please upload a PSA birth certificate before continuing.'])
                ->withInput();
        }

        if (GuardianRelationshipTypes::requiresVerification($step1['relationship_type'] ?? null)) {
            return redirect()->route('parent.create-child.relationship-verification');
        }

        return $this->createChildAccountFromSession($request, $tempUploadService);
    }

    public function childRelationshipVerificationForm(): View|RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $step1 = session('child_step1');
        if (! $step1 || ! session('child_step2') || ! session('child_step3')) {
            return redirect()->route('parent.create-child');
        }

        if (! GuardianRelationshipTypes::requiresVerification($step1['relationship_type'] ?? null)) {
            return redirect()->route('parent.create-child.validation');
        }

        return view('auth.child.step5-relationship-verification', [
            'step1' => $step1,
            'relationshipDocumentTypes' => GuardianRelationshipTypes::documentTypeOptions($step1['relationship_type'] ?? null),
        ]);
    }

    public function storeChildRelationshipVerification(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $step1 = session('child_step1');
        if (! $step1 || ! session('child_step2') || ! session('child_step3')) {
            return redirect()->route('parent.create-child');
        }

        abort_unless(GuardianRelationshipTypes::requiresVerification($step1['relationship_type'] ?? null), 404);

        $validated = $request->validate([
            'relationship_document_type' => ['required', Rule::in(GuardianRelationshipTypes::acceptedDocumentTypes($step1['relationship_type'] ?? null))],
            'relationship_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'relationship_supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'confirm_relationship_verification' => ['accepted'],
        ]);

        return $this->createChildAccountFromSession($request, app(RegistrationTempUploadService::class), [
            'document_type' => (string) $validated['relationship_document_type'],
            'document' => $request->file('relationship_document'),
            'supporting_document' => $request->file('relationship_supporting_document'),
        ]);
    }

    private function createChildAccountFromSession(
        Request $request,
        RegistrationTempUploadService $tempUploadService,
        ?array $relationshipVerificationPayload = null,
    ): RedirectResponse {
        $step1 = session('child_step1');
        $step2 = session('child_step2');
        $step3 = session('child_step3');

        if (!$step1 || !$step2 || !$step3) {
            return redirect()->route('parent.create-child');
        }

        $requiresRelationshipVerification = GuardianRelationshipTypes::requiresVerification($step1['relationship_type'] ?? null);

        $parent = Auth::user();
        $parentEmail = $parent->email;
        $childEmail = $step3['username'] . '@child.sexed-platform.local';

        if (preg_match('/^(.+)@gmail\.com$/i', $parentEmail, $matches)) {
            $childEmail = $matches[1] . '+' . $step3['username'] . '@gmail.com';
        }

        $barangay = \Schoolees\Psgc\Models\Barangay::query()
            ->where('code', $step2['barangay_code'])
            ->where('city_code', $step2['city_code'])
            ->first();

        if (! $barangay) {
            return redirect()->route('parent.create-child.location')
                ->withErrors(['barangay_code' => 'Selected barangay does not belong to the selected city.'])
                ->withInput();
        }
        $verificationDocumentPath = $tempUploadService->finalize(
            'child',
            'verification_document',
            'child-verifications/' . $parent->id,
            'verification-document'
        );

        if ($verificationDocumentPath === null) {
            return back()
                ->withErrors(['verification_document' => 'The uploaded verification document could not be processed. Please upload again.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        $child = User::create([
            'name'           => trim($step1['first_name'] . ' ' . $step1['last_name']),
            'first_name'     => $step1['first_name'],
            'middle_initial' => $step1['middle_initial'] ?? null,
            'last_name'      => $step1['last_name'],
            'suffix'         => $step1['suffix'] ?? null,
            'email'          => $childEmail,
            'birthdate'      => $step1['birthdate'],
            'age'            => $step1['age'],
            'password'       => Hash::make($step3['password']),
            'email_verified_at' => now(),
        ]);

        Role::findOrCreate('learner', 'web');
        $child->assignRole('learner');

        $child->learnerProfile()->create([
            'username'                 => $step3['username'],
            'birthdate'                => $child->birthdate,
            'gender'                   => $step1['gender'],
            'city_code'                => $step2['city_code'],
            'barangay_code'            => $step2['barangay_code'],
            'barangay'                 => $barangay?->name,
            'province_code'            => '402100000',
            'requires_parental_consent'=> true,
        ]);

        $verification = ParentChildAccount::create([
            'parent_user_id'          => $parent->id,
            'child_user_id'           => $child->id,
            'can_view_progress'       => true,
            'can_view_quiz_answers'   => true,
            'can_approve_content'     => true,
            'relationship_type'        => $step1['relationship_type'],
            'relationship_custom'      => ($step1['relationship_type'] ?? null) === GuardianRelationshipTypes::OTHER ? ($step1['relationship_custom'] ?? null) : null,
            'relationship_status'      => $requiresRelationshipVerification ? 'pending' : 'active',
            'relationship_verified_status' => app(GuardianRelationshipVerificationService::class)->initialStatus($step1['relationship_type']),
            'is_legacy_relationship'   => false,
            'verification_status'     => VerificationStatus::Pending->value,
            'verification_document_path' => $verificationDocumentPath,
            'relationship_verified_at'=> null,
        ]);

        if ($requiresRelationshipVerification) {
            app(GuardianRelationshipVerificationService::class)->submit($verification, $parent, [
                'document_type' => (string) $relationshipVerificationPayload['document_type'],
                'document' => $relationshipVerificationPayload['document'],
                'supporting_document' => $relationshipVerificationPayload['supporting_document'] ?? null,
            ]);
        }

        $this->notifyAdminsSafely(new ChildVerificationRequestSubmittedNotification($parent, $child, $verification));

        session()->forget(['child_step1', 'child_step2', 'child_step3', 'pending_child_registration']);
        session([
            'child_created_name' => $step1['first_name'],
            'child_registration_result' => [
                'status' => VerificationStatus::Pending->value,
            ],
        ]);

        return redirect()->route('parent.create-child.done');
    }

    /**
     * Step 4: Done page (monitoring info)
     */
    public function childDone(): View
    {
        $childName = session('child_created_name', 'your child');
        $registrationResult = session('child_registration_result', [
            'status' => VerificationStatus::Pending->value,
        ]);
        session()->forget(['child_created_name', 'child_registration_result']);

        return view('auth.child.done', [
            'childName' => $childName,
            'registrationResult' => $registrationResult,
        ]);
    }

    /**
     * Show parent's children list
     */
    public function childrenIndex(ParentChildInvitationService $invitationService): View|RedirectResponse
    {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $parent = Auth::user();
        if (! $parent instanceof User) {
            abort(403);
        }

        $children = $parent->children()
            ->with('learnerProfile')
            ->get();

        $pendingApprovalNotifications = $parent
            ->unreadNotifications()
            ->where('data->type', 'child_enrollment_approval_requested')
            ->latest()
            ->limit(5)
            ->get();

        $outgoingInvitations = $invitationService
            ->getOutgoingInvitations($parent)
            ->take(1)
            ->values();

        return view('parent.children.index', compact('children', 'pendingApprovalNotifications', 'outgoingInvitations'));
    }

    public function verificationStatus(): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->isParentRegistration()) {
            return redirect()->route('learner.dashboard');
        }

        if (!$user->hasVerifiedEmail()) {
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

    public function resubmitParentVerification(
        Request $request,
    ): RedirectResponse {
        $parent = $request->user();

        if (! $parent->isParentRegistration()) {
            return redirect()->route('learner.dashboard');
        }

        if (! $parent->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('error', 'Please verify your email first.');
        }

        if (! $parent->isParentVerificationRejected()) {
            return redirect()->route('parent.verification.status')
                ->with('error', 'Only rejected guardian verification records can be resubmitted.');
        }

        return redirect()->route('guardian.verification.create')
            ->with('error', 'Please resubmit through the Guardian verification form.');
    }

    public function childVerificationStatus(): View|RedirectResponse
    {
        $user = Auth::user();
        $verification = ParentChildAccount::query()
            ->where('child_user_id', $user->id)
            ->whereNotNull('verification_document_path')
            ->latest('id')
            ->with('parent')
            ->first();

        if (!$verification) {
            return redirect()->route('learner.dashboard');
        }

        if ($verification->verification_status === 'approved') {
            return redirect()->route('learner.dashboard');
        }

        return view('auth.child-verification-status', [
            'verification' => $verification,
        ]);
    }

    public function resubmitChildVerification(
        ResubmitChildVerificationRequest $request,
        User $child,
        RegistrationTempUploadService $tempUploadService,
        ParentChildVerificationService $verificationService,
    ): RedirectResponse {
        if ($redirect = $this->ensureApprovedParent()) {
            return $redirect;
        }

        $parent = $request->user();

        $verification = ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->first();

        abort_if(! $verification, 403, 'You are not allowed to resubmit this child verification record.');

        if ($verification->verification_status !== VerificationStatus::Rejected->value) {
            return redirect()->route('parent.children.index')
                ->with('error', 'Only rejected child verification records can be resubmitted.');
        }

        $tempUploadService->store('child', 'verification_document', $request->file('verification_document'));
        $finalizedPath = $tempUploadService->finalize(
            'child',
            'verification_document',
            'child-verifications/' . $parent->id,
            'verification-document'
        );

        if ($finalizedPath === null) {
            return back()->withErrors([
                'verification_document' => 'The uploaded verification document could not be processed. Please upload again.',
            ]);
        }

        try {
            $verificationService->resubmitChild($verification, $finalizedPath);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('parent.children.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('parent.children.index')
            ->with('success', 'Child verification resubmitted successfully. We will review the updated document.');
    }

    private function notifyAdminsSafely(Notification $notification): void
    {
        try {
            User::query()
                ->role('admin')
                ->get()
                ->each(fn (User $admin) => $admin->notify($notification));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send admin guardian-child verification submission notification.', [
                'notification' => $notification::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureApprovedParent(): ?RedirectResponse
    {
        $parent = Auth::user();

        if (! $parent->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('error', 'Please verify your email first.');
        }

        if (! $parent->canBeParent()) {
            abort(403, 'You must be 18 or older to create a child account.');
        }

        if (! $parent->isParentRegistration() || ! $parent->isParentVerificationApproved()) {
            return redirect()->route('parent.verification.status')
                ->with('warning', 'Your guardian account is still under admin review.');
        }

        if (! $parent->hasCompletedGuardianOnboarding()) {
            return redirect()->route('guardian.onboarding.show')
                ->with('warning', 'Please complete Guardian onboarding before creating a child account.');
        }

        return null;
    }

    private function ensureApprovedParentForJson(): ?JsonResponse
    {
        $parent = Auth::user();

        if (!$parent->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email first.',
            ], 403);
        }

        if (!$parent->canBeParent()) {
            return response()->json([
                'message' => 'You must be 18 or older to create a child account.',
            ], 403);
        }

        if (!$parent->isParentRegistration() || !$parent->isParentVerificationApproved()) {
            return response()->json([
                'message' => 'Guardian verification is required before child registration uploads.',
            ], 403);
        }

        if (!$parent->hasCompletedGuardianOnboarding()) {
            return response()->json([
                'message' => 'Please complete Guardian onboarding before creating a child account.',
            ], 403);
        }

        return null;
    }
}
