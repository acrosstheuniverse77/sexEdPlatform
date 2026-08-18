<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Content\ContentAuthoringService;
use App\Services\ContentGovernanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminModuleController extends Controller
{
    public function __construct(
        private readonly ContentGovernanceService $contentGovernanceService,
        private readonly ContentAuthoringService $contentAuthoringService,
    ) {
    }

    public function index()
    {
        $this->authorize('viewAny', Module::class);

        $modules = Module::query()
            ->where('content_owner_type', 'admin')
            ->latest()
            ->paginate(12);

        return view('admin.modules.index', compact('modules'));
    }

    public function create()
    {
        $this->authorize('create', Module::class);

        return view('admin.modules.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Module::class);

        $validated = $this->validateModule($request);

        $module = $this->contentGovernanceService->createAdminOwnedModuleFromValidated(
            $validated,
            $request->user(),
        );
        $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

        return redirect()->route('admin.modules.show', $module)
            ->with('success', 'Admin-owned module created and published.');
    }

    public function show(Module $module)
    {
        $this->authorize('view', $module);
        abort_unless($module->content_owner_type === 'admin', 404);

        return view('admin.modules.show', compact('module'));
    }

    public function edit(Module $module)
    {
        $this->authorize('update', $module);
        abort_unless($module->content_owner_type === 'admin', 404);

        return view('admin.modules.edit', compact('module'));
    }

    public function update(Request $request, Module $module)
    {
        $this->authorize('update', $module);
        abort_unless($module->content_owner_type === 'admin', 404);

        $validated = $this->validateModule($request);

        $module->update($this->contentAuthoringService->toAdminPayload($validated, $module) + [
            'current_review_status' => 'approved',
            'is_published' => true,
            'published_by_admin_id' => $request->user()->id,
        ]);
        $module->syncLearnerCategories($this->learnerCategoriesFromValidated($validated));

        return redirect()->route('admin.modules.show', $module)
            ->with('success', 'Admin-owned module updated.');
    }

    private function validateModule(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'age_bracket' => ['nullable', Rule::in(['kids', 'teens', 'adults'])],
            'age_brackets' => ['required_without:age_bracket', 'array', 'min:1'],
            'age_brackets.*' => ['required', Rule::in(['kids', 'teens', 'adults'])],
            'enrollment_mode' => 'required|in:auto,manual',
            'is_published' => 'nullable|boolean',
        ]);
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
