<?php

namespace App\Http\Controllers\Instructor;

use App\Helpers\VideoEmbedHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LessonRequest;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Services\Content\ContentOwnershipGuard;
use App\Support\ContentPanelContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lesson::class);

        $moduleId = (int) $request->integer('module_id', 0);
        $lessonStatus = (string) $request->get('lesson_status', 'all');
        $search = trim((string) $request->get('search', ''));

        $lessonFilter = function ($query) use ($lessonStatus, $search): void {
            $query->orderBy('order');

            if ($lessonStatus === 'active') {
                $query->where('is_published', true);
            } elseif ($lessonStatus === 'inactive') {
                $query->where('is_published', false);
            }

            if ($search !== '') {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }
        };

        $baseModuleQuery = Module::query()
            ->when($this->panelContext()->isInstructor(), fn ($query) => $query->where('created_by', Auth::id()));

        $availableModules = (clone $baseModuleQuery)
            ->orderBy('title')
            ->get(['id', 'title']);

        $moduleGroupsQuery = (clone $baseModuleQuery)
            ->when($moduleId > 0, fn ($query) => $query->whereKey($moduleId))
            ->when(
                $lessonStatus !== 'all' || $search !== '',
                fn ($query) => $query->whereHas('lessons', $lessonFilter)
            )
            ->with(['lessons' => $lessonFilter])
            ->withCount(['lessons' => $lessonFilter])
            ->orderByDesc('created_at')
            ->get();

        return view('instructor.lessons.index', [
            'moduleGroups' => $moduleGroupsQuery,
            'availableModules' => $availableModules,
            'moduleId' => $moduleId,
            'lessonStatus' => $lessonStatus,
            'search' => $search,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Lesson::class);

        $modules = Module::query()
            ->when($this->panelContext()->isInstructor(), fn ($query) => $query->where('created_by', Auth::id()))
            ->orderBy('title')
            ->get();
        return view('instructor.lessons.create', compact('modules'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Lesson::class);

        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        $this->ensureModuleAccessible((int) $validated['module_id']);

        // Auto-increment order
        $validated['order'] = Lesson::where('module_id', $validated['module_id'])->max('order') + 1;
        
        // Duration will be auto-calculated from topics (start with 0)
        $validated['duration'] = 0;
        
        // Default create flow to active unless explicitly set.
        $validated['is_published'] = $request->has('is_published')
            ? $request->boolean('is_published')
            : true;
        
        // Default content type (container lesson)
        $validated['content_type'] = 'text';

        $lesson = Lesson::create($validated);

        return redirect()->route($this->routeName('lessons.show'), $lesson)

        
            ->with('success', 'Lesson created successfully! Now add topics to this lesson.');
    }

    public function show(Lesson $lesson)
    {
        $this->authorize('view', $lesson);

        $lesson->load([
            'module.creator',
            'topics' => fn ($query) => $query->orderBy('order')->with('checkpointQuestions'),
            'quizzes.questions',
        ]);

        $modules = Module::query()
            ->when($this->panelContext()->isInstructor(), fn ($query) => $query->where('created_by', Auth::id()))
            ->with(['lessons:id,module_id,title'])
            ->orderBy('title')
            ->get(['id', 'title']);

        $enrolledCount = $lesson->module
            ? $lesson->module->enrollments()->where('status', 'approved')->count()
            : 0;

        $completedCount = \App\Models\UserProgress::where('lesson_id', $lesson->id)
            ->where('completed', true)
            ->count();

        $completionRate = $enrolledCount > 0
            ? round(($completedCount / $enrolledCount) * 100)
            : 0;

        return view('instructor.lessons.show', compact('lesson', 'modules', 'enrolledCount', 'completedCount', 'completionRate'));
    }

    public function edit(Lesson $lesson)
    {
        $this->authorize('update', $lesson);
        $this->ensureAdminCanMutateLesson($lesson);

        return redirect()->route($this->routeName('lessons.index'), [
            'edit_lesson' => $lesson->id,
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorize('update', $lesson);
        $this->ensureAdminCanMutateLesson($lesson);

        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        $this->ensureModuleAccessible((int) $validated['module_id']);

        if ($request->has('is_published')) {
            $validated['is_published'] = $request->boolean('is_published');
        } else {
            unset($validated['is_published']);
        }

        // Duration is auto-calculated from topics (recalculate it)
        $lesson->duration = $lesson->topics()->sum('duration');
        
        $lesson->update($validated);

        // Update module duration as well
        $module = $lesson->module;
        $module->duration_minutes = $module->lessons()->sum('duration');
        $module->save();

        return redirect()->route($this->routeName('lessons.show'), $lesson)
            ->with('success', 'Lesson updated successfully!');
    }

    public function destroy(Lesson $lesson)
    {
        $this->authorize('delete', $lesson);
        $this->ensureAdminCanMutateLesson($lesson);

        $moduleId = $lesson->module_id;
        $lesson->delete();

        return redirect()->route($this->routeName('modules.show'), $moduleId)
            ->with('success', 'Lesson deleted successfully!');
    }

    public function move(Request $request, Lesson $lesson)
    {
        $this->authorize('update', $lesson);
        $this->ensureAdminCanMutateLesson($lesson);

        $direction = $request->input('direction');
        $currentOrder = $lesson->order;

        if ($direction === 'up' && $currentOrder > 1) {
            $swapLesson = Lesson::where('module_id', $lesson->module_id)
                ->where('order', $currentOrder - 1)
                ->first();

            if ($swapLesson) {
                $lesson->update(['order' => $currentOrder - 1]);
                $swapLesson->update(['order' => $currentOrder]);
            }
        } elseif ($direction === 'down') {
            $maxOrder = Lesson::where('module_id', $lesson->module_id)->max('order');

            if ($currentOrder < $maxOrder) {
                $swapLesson = Lesson::where('module_id', $lesson->module_id)
                    ->where('order', $currentOrder + 1)
                    ->first();

                if ($swapLesson) {
                    $lesson->update(['order' => $currentOrder + 1]);
                    $swapLesson->update(['order' => $currentOrder]);
                }
            }
        }

        return redirect()->back()->with('success', 'Lesson order updated!');
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer|exists:lessons,id']);

        foreach ($request->order as $index => $id) {
            $lesson = Lesson::query()->findOrFail($id);
            $this->authorize('update', $lesson);
            $this->ensureAdminCanMutateLesson($lesson);
            Lesson::where('id', $id)->update(['order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    private function panelContext(): ContentPanelContext
    {
        return app(ContentPanelContext::class);
    }

    private function routeName(string $suffix): string
    {
        return $this->panelContext()->name($suffix);
    }

    private function ensureModuleAccessible(int $moduleId): void
    {
        $module = Module::query()->findOrFail($moduleId);

        if ($this->panelContext()->isAdmin()) {
            $ownerType = $this->ownershipGuard()->ownerTypeForModule($module);

            abort_unless(
                $this->ownershipGuard()->canAdminMutateOwnerType($ownerType),
                403,
                'Admins can only modify platform-owned learning content.',
            );

            return;
        }

        $ownsModule = (int) ($module->created_by ?? 0) === (int) Auth::id();

        abort_unless($ownsModule, 403);
    }

    private function ensureAdminCanMutateLesson(Lesson $lesson): void
    {
        if (!$this->panelContext()->isAdmin()) {
            return;
        }

        $ownerType = $this->ownershipGuard()->ownerTypeForLesson($lesson);

        abort_unless(
            $this->ownershipGuard()->canAdminMutateOwnerType($ownerType),
            403,
            'Admins can only modify platform-owned learning content.',
        );
    }

    private function ownershipGuard(): ContentOwnershipGuard
    {
        return app(ContentOwnershipGuard::class);
    }
}
