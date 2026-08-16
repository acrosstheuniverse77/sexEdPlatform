<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreModuleRequest;
use App\Http\Requests\Instructor\UpdateModuleRequest;
use App\Models\Module;
use App\Models\User;
use App\Services\Content\ContentAccessService;
use App\Services\Content\ContentOwnershipGuard;
use App\Services\Content\ContentAuthoringService;
use App\Services\ContentGovernanceService;
use App\Services\Instructor\InstructorPlanCapabilityService;
use App\Services\Monetization\CommissionPolicyResolver;
use App\Support\ContentPanelContext;
use App\Support\InstructorRestrictionGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class ModuleController extends Controller
{
    public function __construct(
        private readonly InstructorRestrictionGate $instructorRestrictionGate,
        private readonly CommissionPolicyResolver $commissionPolicyResolver,
        private readonly ContentAccessService $contentAccessService,
        private readonly ContentOwnershipGuard $contentOwnershipGuard,
        private readonly ContentAuthoringService $contentAuthoringService,
        private readonly ContentGovernanceService $contentGovernanceService,
        private readonly InstructorPlanCapabilityService $instructorPlanCapabilityService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Module::class);

        $status = $request->get('status', 'all');
        $scope = $request->get('scope', 'all');
        $search = trim((string) $request->get('search', ''));
        $ownerType = (string) $request->get('owner_type', 'all');
        $user = Auth::user();
        $context = $this->panelContext();

        if ($context->isAdmin()) {
            $modules = $this->contentAccessService->paginateAdminModules(
                (string) $scope,
                (string) $status,
                $search,
                $ownerType,
                12,
            );
            $pendingCount = $this->contentAccessService->pendingEnrollmentCountForAdmin();

            return view('admin.modules.index', [
                'modules' => $modules,
                'pendingCount' => $pendingCount,
                'status' => (string) $status,
                'scope' => (string) $scope,
                'search' => $search,
                'ownerType' => $ownerType,
                'isRestricted' => false,
                'restrictionProfile' => null,
                'restrictionMessage' => null,
                'effectiveCommissionPolicy' => null,
            ]);
        }

        $modules = $this->contentAccessService->paginateInstructorModules((int) Auth::id(), (string) $status, 12);
        $pendingCount = $this->contentAccessService->pendingEnrollmentCountForInstructor((int) Auth::id());

        $restrictionProfile = $user ? $this->instructorRestrictionGate->activeRestrictionProfile($user) : null;
        $isRestricted = $restrictionProfile !== null;
        $restrictionMessage = $isRestricted
            ? $this->instructorRestrictionGate->restrictionMessage($user)
            : null;

        $effectiveCommissionPolicy = $this->resolveEffectiveCommissionPolicyPayload($user);
        $instructorCapabilitySnapshot = $user ? $this->instructorPlanCapabilityService->getUsageSnapshot($user) : null;

        return view('instructor.modules.index', compact(
            'modules',
            'pendingCount',
            'status',
            'scope',
            'isRestricted',
            'restrictionProfile',
            'restrictionMessage',
            'effectiveCommissionPolicy',
            'instructorCapabilitySnapshot',
        ));
    }

    public function create()
    {
        $this->authorize('create', Module::class);

        if ($this->panelContext()->isAdmin()) {
            return redirect()->route($this->routeName('modules.index'), [
                'create_module' => 1,
            ]);
        }

        $user = Auth::user();
        if ($this->panelContext()->isInstructor() && $user && $this->instructorRestrictionGate->isRestricted($user)) {
            return redirect()->route($this->routeName('modules.index'))
                ->with('error', $this->instructorRestrictionGate->restrictionMessage($user));
        }

        $createWithinPlanLimit = Gate::inspect('createWithinPlanLimit', Module::class);
        if ($createWithinPlanLimit->denied()) {
            return redirect()->route($this->routeName('modules.index'))
                ->with('error', $createWithinPlanLimit->message() ?: 'You have reached your plan limit.');
        }

        return view('instructor.modules.create', [
            'isRestricted' => false,
            'restrictionProfile' => null,
            'restrictionMessage' => null,
            'effectiveCommissionPolicy' => $this->resolveEffectiveCommissionPolicyPayload($user),
            'instructorCapabilitySnapshot' => $user ? $this->instructorPlanCapabilityService->getUsageSnapshot($user) : null,
        ]);
    }

    public function store(StoreModuleRequest $request)
    {
        $this->authorize('create', Module::class);

        if ($this->panelContext()->isInstructor() && $this->instructorRestrictionGate->isRestricted($request->user())) {
            return redirect()->route($this->routeName('modules.index'))
                ->with('error', $this->instructorRestrictionGate->restrictionMessage($request->user()));
        }

        $createWithinPlanLimit = Gate::inspect('createWithinPlanLimit', Module::class);
        if ($createWithinPlanLimit->denied()) {
            return back()
                ->withInput()
                ->withErrors([
                    'title' => $createWithinPlanLimit->message() ?: 'You have reached your plan limit.',
                ]);
        }

        $validated = $request->validated();

        $validated['access_type'] = $validated['access_type'] ?? 'free';
        if ((string) $validated['access_type'] === 'paid') {
            $validated['price_currency'] = 'PHP';
        } else {
            $validated['price_currency'] = null;
            $validated['price_amount'] = null;
        }

        if ($this->panelContext()->isInstructor() && (string) $validated['access_type'] === 'paid') {
            try {
                $this->authorize('publishPaid', Module::class);
            } catch (AuthorizationException) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'access_type' => 'Your current instructor plan does not allow publishing paid modules. Upgrade your instructor subscription to continue.',
                    ]);
            }
        }

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('modules', 'public');
        }

        if ($this->panelContext()->isAdmin()) {
            $action = (string) ($validated['action'] ?? 'publish');
            $module = $this->storeAdminModule($validated, $request, $action);

            $message = match ($action) {
                'draft' => 'Platform module saved as draft.',
                'archive' => 'Platform module archived.',
                default => 'Platform module published.',
            };

            if ($action === 'archive') {
                return redirect()->route($this->routeName('modules.index'), ['status' => 'archived'])
                    ->with('success', $message);
            }

            return redirect()->route($this->routeName('modules.show'), $module)
                ->with('success', $message);
        }

        try {
            $payload = $this->contentAuthoringService->toInstructorDraftPayload(
                $validated,
                (int) Auth::id(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['access_type' => $exception->getMessage()]);
        }

        if (isset($validated['thumbnail'])) {
            $payload['thumbnail'] = $validated['thumbnail'];
        }

        $module = Module::create($payload);
        $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

        $message = 'Module saved as draft. Submit it for admin review when it is ready.';

        return redirect()->route($this->routeName('modules.show'), $module)
            ->with('success', $message);
    }

    public function show(Module $module)
    {
        $this->authorize('view', $module);
        if ($this->panelContext()->isInstructor()) {
            abort_unless((int) $module->created_by === (int) Auth::id(), 403);
        }

        $module->load([
            'creator:id,role',
            'lessons' => fn ($q) => $q->orderBy('order'),
            'quizzes',
            'feedback' => fn ($query) => $query->latest()->with('learner:id,name,first_name,last_name'),
            'reviewRequests' => fn ($query) => $query->latest(),
            'enrollments' => fn ($query) => $query
                ->latest()
                ->with('user:id,name,first_name,last_name,email'),
        ]);

        $pendingEnrollmentsCount = $module->enrollments->where('status', 'pending')->count();

        return view('instructor.modules.show', compact('module', 'pendingEnrollmentsCount'));
    }

    public function edit(Module $module)
    {
        $this->authorize('update', $module);
        $this->ensureAdminCanMutateModule($module);

        if ($this->panelContext()->isAdmin()) {
            return redirect()->route($this->routeName('modules.index'), [
                'edit_module' => $module->id,
            ]);
        }

        $user = Auth::user();
        $restrictionProfile = $user ? $this->instructorRestrictionGate->activeRestrictionProfile($user) : null;

        return view('instructor.modules.edit', [
            'module' => $module,
            'isRestricted' => $restrictionProfile !== null,
            'restrictionProfile' => $restrictionProfile,
            'restrictionMessage' => $restrictionProfile ? $this->instructorRestrictionGate->restrictionMessage($user) : null,
            'effectiveCommissionPolicy' => $this->resolveEffectiveCommissionPolicyPayload($user),
        ]);
    }

    private function resolveEffectiveCommissionPolicyPayload(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        try {
            $policy = $this->commissionPolicyResolver->resolveForInstructor((int) $user->id);

            return [
                'commission_percent' => (float) $policy->commission_percent,
                'tax_basis' => (string) $policy->tax_basis,
                'refund_policy' => (string) $policy->refund_policy,
            ];
        } catch (RuntimeException) {
            return null;
        }
    }

    public function update(UpdateModuleRequest $request, Module $module)
    {
        $this->authorize('update', $module);
        $this->ensureAdminCanMutateModule($module);

        $validated = $request->validated();

        $validated['access_type'] = $validated['access_type'] ?? ($module->access_type ?? 'free');
        if ((string) $validated['access_type'] === 'paid') {
            $validated['price_currency'] = 'PHP';
        } else {
            $validated['price_currency'] = null;
            $validated['price_amount'] = null;
        }

        if ($this->panelContext()->isInstructor() && (string) $validated['access_type'] === 'paid') {
            try {
                $this->authorize('publishPaid', Module::class);
            } catch (AuthorizationException) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'access_type' => 'Your current instructor plan does not allow publishing paid modules. Upgrade your instructor subscription to continue.',
                    ]);
            }
        }

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('modules', 'public');
        }

        if ($this->panelContext()->isAdmin()) {
            $action = (string) ($validated['action'] ?? 'publish');
            $this->updateAdminModule($module, $validated, $request, $action);

            $message = match ($action) {
                'draft' => 'Platform module updated as draft.',
                'archive' => 'Platform module archived.',
                default => 'Platform module published.',
            };

            if ($action === 'archive') {
                return redirect()->route($this->routeName('modules.index'), ['status' => 'archived'])
                    ->with('success', $message);
            }

            return redirect()->route($this->routeName('modules.show'), $module)
                ->with('success', $message);
        }

        try {
            $payload = $this->contentAuthoringService->toInstructorDraftPayload(
                $validated,
                (int) ($module->created_by ?? Auth::id()),
                $module,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['access_type' => $exception->getMessage()]);
        }

        if (isset($validated['thumbnail'])) {
            $payload['thumbnail'] = $validated['thumbnail'];
        }
        
        // Duration is auto-calculated, but update it now
        $module->duration_minutes = $module->lessons()->sum('duration');
        $module->save();

        $module->update($payload);
        $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

        return redirect()->route($this->routeName('modules.index'))
            ->with('success', 'Module updated successfully!');
    }

    public function destroy(Module $module)
    {
        $this->authorize('delete', $module);
        $this->ensureAdminCanMutateModule($module);

        $module->delete();

        return redirect()->route($this->routeName('modules.index'))
            ->with('success', 'Module deleted successfully!');
    }

    public function forceDelete(int $id)
    {
        $module = Module::withTrashed()->findOrFail($id);
        $this->authorize('delete', $module);
        $this->ensureAdminCanMutateModule($module);

        if ($this->panelContext()->isInstructor()) {
            abort_unless((int) $module->created_by === (int) Auth::id(), 403);
        }

        if (!$module->trashed()) {
            return back()->with('error', 'Only archived modules can be permanently deleted.');
        }

        $module->forceDelete();

        return back()->with('success', 'Module permanently deleted.');
    }

    public function activate(Module $module)
    {
        $this->authorize('update', $module);
        $this->ensureAdminCanMutateModule($module);
        if ($this->panelContext()->isInstructor()) {
            abort_unless((int) $module->created_by === (int) Auth::id(), 403);
        }
        $module->update([
            'is_published' => false,
            'current_review_status' => $module->current_review_status ?? 'draft',
        ]);

        return back()->with('info', 'Instructor modules now require admin approval before publication.');
    }

    public function deactivate(Module $module)
    {
        $this->authorize('update', $module);
        $this->ensureAdminCanMutateModule($module);
        if ($this->panelContext()->isInstructor()) {
            abort_unless((int) $module->created_by === (int) Auth::id(), 403);
        }
        $module->update(['is_published' => false]);
        return back()->with('success', 'Module deactivated successfully.');
    }

    public function restore($id)
    {
        $module = Module::withTrashed()->findOrFail($id);
        $this->authorize('update', $module);
        $this->ensureAdminCanMutateModule($module);
        if ($this->panelContext()->isInstructor()) {
            abort_unless((int) $module->created_by === (int) Auth::id(), 403);
        }
        $module->restore();
        return back()->with('success', 'Module restored successfully.');
    }

    private function panelContext(): ContentPanelContext
    {
        return app(ContentPanelContext::class);
    }

    private function routeName(string $suffix): string
    {
        return $this->panelContext()->name($suffix);
    }

    private function ensureAdminCanMutateModule(Module $module): void
    {
        if (!$this->panelContext()->isAdmin()) {
            return;
        }

        $ownerType = $this->contentOwnershipGuard->ownerTypeForModule($module);

        abort_unless(
            $this->contentOwnershipGuard->canAdminMutateOwnerType($ownerType),
            403,
            'Admins can only modify platform-owned learning content.',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeAdminModule(array $validated, StoreModuleRequest $request, string $action): Module
    {
        $payload = $this->contentAuthoringService->toAdminPayload($validated);

        if (isset($validated['thumbnail'])) {
            $payload['thumbnail'] = $validated['thumbnail'];
        }

        if ($action === 'publish') {
            $module = $this->contentGovernanceService->createAdminOwnedModule($payload, $request->user());
            $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

            return $module;
        }

        $module = Module::query()->create($payload + [
            'created_by' => (int) $request->user()->id,
            'content_owner_type' => 'admin',
            'current_review_status' => 'draft',
            'is_published' => false,
            'published_by_admin_id' => null,
            'published_revision_id' => null,
        ]);

        if ($action === 'archive') {
            $module->delete();
        }

        $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

        return $module->fresh(['creator']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateAdminModule(Module $module, array $validated, UpdateModuleRequest $request, string $action): void
    {
        $payload = $this->contentAuthoringService->toAdminPayload($validated, $module);

        if (isset($validated['thumbnail'])) {
            $payload['thumbnail'] = $validated['thumbnail'];
        }

        if ($action === 'publish') {
            $module->update($payload + [
                'content_owner_type' => 'admin',
                'current_review_status' => 'approved',
                'is_published' => true,
                'published_by_admin_id' => (int) $request->user()->id,
            ]);
            $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

            return;
        }

        $module->update($payload + [
            'content_owner_type' => 'admin',
            'current_review_status' => 'draft',
            'is_published' => false,
            'published_by_admin_id' => null,
        ]);

        if ($action === 'archive') {
            $module->delete();
        }

        $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<int, string>
     */
    private function learnerCategoriesFromValidated(array $validated): array
    {
        return Module::normalizeLearnerCategories(
            (array) ($validated['age_brackets'] ?? [($validated['age_bracket'] ?? 'teens')])
        );
    }
}
