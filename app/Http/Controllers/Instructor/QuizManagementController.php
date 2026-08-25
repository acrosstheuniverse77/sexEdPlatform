<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreQuizRequest;
use App\Http\Requests\Instructor\UpdateQuizRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizOption;
use App\Models\Module;
use App\Models\Lesson;
use App\Services\Content\ContentOwnershipGuard;
use App\Services\Learning\QuestionAuthoringService;
use App\Support\ContentPanelContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class QuizManagementController extends Controller
{
    public function __construct(private QuestionAuthoringService $questionAuthoring) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Quiz::class);

        $moduleIds = Module::query()
            ->when($this->panelContext()->isInstructor(), fn ($query) => $query->where('created_by', Auth::id()))
            ->pluck('id');

        $quizzes = Quiz::with([
            'module:id,title,content_owner_type,created_by',
            'module.creator:id,role',
            'lesson:id,title,module_id',
            'lesson.module:id,content_owner_type,created_by',
            'lesson.module.creator:id,role',
        ])
            ->withCount('questions')
            ->where(function ($q) use ($moduleIds) {
                $q->whereIn('module_id', $moduleIds)
                  ->orWhereHas('lesson', fn($lq) => $lq->whereIn('module_id', $moduleIds));
            })
            ->orderByDesc('created_at')
            ->get()
            ->each(function (Quiz $quiz): void {
                $quiz->setAttribute('owner_type', $this->ownershipGuard()->ownerTypeForQuiz($quiz));
            });

        $modules = Module::query()
            ->when($this->panelContext()->isInstructor(), fn ($query) => $query->where('created_by', Auth::id()))
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        return view('instructor.quizzes.index', compact('quizzes', 'modules'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Quiz::class);

        $modules = Module::query()
            ->when($this->panelContext()->isInstructor(), fn ($query) => $query->where('created_by', Auth::id()))
            ->when($this->panelContext()->isAdmin(), fn ($query) => $query->where('content_owner_type', 'admin'))
            ->with('lessons')
            ->get();
        $lessonId = $request->query('lesson_id');
        
        return view('instructor.quizzes.create', compact('modules', 'lessonId'));
    }

    public function store(StoreQuizRequest $request)
    {
        $this->authorize('create', Quiz::class);

        $validated = $request->validated();

        // Set null defaults for optional fields
        $validated['module_id'] = $validated['module_id'] ?? null;
        $validated['lesson_id'] = $validated['lesson_id'] ?? null;

        if ($validated['lesson_id']) {
            $lesson = Lesson::query()->select('id', 'module_id')->find($validated['lesson_id']);
            $validated['module_id'] = $lesson?->module_id;
        }

        // Ensure at least one of module_id or lesson_id is provided
        if (!$validated['module_id'] && !$validated['lesson_id']) {
            return back()->withErrors(['module_id' => 'Please select either a module or a lesson.'])->withInput();
        }

        $this->ensureAdminCanMutateModuleById((int) ($validated['module_id'] ?? 0));

        $validated['slug'] = Str::slug($validated['title']);
        $validated['time_limit'] = $this->normalizeTimeLimit(
            $request->integer('time_limit_hours', 0),
            $request->integer('time_limit_minutes', 0),
            $request->integer('time_limit_seconds', 0),
        );
        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        $quiz = Quiz::create($validated);

        return redirect()->route($this->routeName('quizzes.show'), $quiz->id)
            ->with('success', 'Quiz created! Now add questions below.');
    }

    public function show(Quiz $quiz)
    {
        $this->authorize('view', $quiz);

        $quiz->load(['questions.options', 'module', 'lesson']);
        $openModal = request()->boolean('open_modal');
        return view('instructor.quizzes.show', compact('quiz', 'openModal'));
    }

    public function edit(Quiz $quiz)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        return redirect()->route($this->routeName('quizzes.index'), [
            'edit_quiz' => $quiz->id,
        ]);
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        $validated = $request->validated();

        // Set null defaults for optional fields
        $validated['module_id'] = $validated['module_id'] ?? null;
        $validated['lesson_id'] = $validated['lesson_id'] ?? null;

        if ($validated['lesson_id']) {
            $lesson = Lesson::query()->select('id', 'module_id')->find($validated['lesson_id']);
            $validated['module_id'] = $lesson?->module_id;
        }

        if (!$validated['module_id'] && !$validated['lesson_id']) {
            return back()->withErrors(['module_id' => 'Please select either a module or a lesson.'])->withInput();
        }

        $this->ensureAdminCanMutateModuleById((int) ($validated['module_id'] ?? 0));

        $validated['slug'] = Str::slug($validated['title']);
        $validated['time_limit'] = $this->normalizeTimeLimit(
            $request->integer('time_limit_hours', 0),
            $request->integer('time_limit_minutes', 0),
            $request->integer('time_limit_seconds', 0),
        );

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        } else {
            unset($validated['is_active']);
        }

        $quiz->update($validated);

        return redirect()->route($this->routeName('quizzes.index'))
            ->with('success', 'Quiz updated successfully!');
    }

    private function normalizeTimeLimit(int $hours, int $minutes, int $seconds): ?int
    {
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;

        return $totalSeconds > 0 ? $totalSeconds : null;
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorize('delete', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        $quiz->delete();

        return redirect()->route($this->routeName('quizzes.index'))
            ->with('success', 'Quiz deleted successfully!');
    }

    // Question management
    public function addQuestion(Quiz $quiz)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        $preselectedType = request()->query('type');
        $validTypes = ['multiple_choice', 'true_false', 'multiple_select', 'fill_blank_text', 'fill_blank_select', 'identification'];
        $selectedType = in_array($preselectedType, $validTypes) ? $preselectedType : null;
        return view('instructor.quizzes.add-question', compact('quiz', 'selectedType'));
    }

    public function storeQuestion(Request $request, Quiz $quiz)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        $this->questionAuthoring->normalizeRequest($request);

        $validated = $request->validate($this->questionAuthoring->rules());

        if ($request->question_type === 'fill_blank_select' && $request->word_bank) {
            $words = array_map('trim', explode(',', $request->word_bank));
            if (count($words) > 10) {
                return back()->withErrors(['word_bank' => 'Word bank cannot exceed 10 words.'])->withInput();
            }
        }

        try {
            $this->questionAuthoring->createQuestion($validated + ['image' => $request->file('image')], [
                'quiz_id' => $quiz->id,
                'order' => $quiz->questions()->max('order') + 1,
            ]);

            $afterSave = $request->input('after_save', 'return');

            if ($afterSave === 'another') {
                return redirect()
                    ->route($this->routeName('quizzes.add-question'), ['quiz' => $quiz, 'type' => $validated['question_type']])
                    ->with('success', 'Question added! Add another one below.');
            }

            return redirect()
                ->route($this->routeName('quizzes.show'), ['quiz' => $quiz, 'open_modal' => 1])
                ->with('success', 'Question added successfully!');

        } catch (\Exception $e) {
            \Log::error('Failed to add question: ' . $e->getMessage());
            return back()->with('error', 'Failed to add question: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show edit question form
     */
    public function editQuestion(Quiz $quiz, QuizQuestion $question)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        // Verify question belongs to this quiz
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }

        return view('instructor.quizzes.edit-question', compact('quiz', 'question'));
    }

    /**
     * Update existing question
     */
    public function updateQuestion(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        // Verify question belongs to this quiz
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }

        $this->questionAuthoring->normalizeRequest($request);

        $validated = $request->validate($this->questionAuthoring->rules());

        if ($request->question_type === 'fill_blank_select' && $request->word_bank) {
            $words = array_map('trim', explode(',', $request->word_bank));
            if (count($words) > 10) {
                return back()->withErrors(['word_bank' => 'Word bank cannot exceed 10 words.'])->withInput();
            }
        }

        try {
            $this->questionAuthoring->updateQuestion($question, $validated + ['image' => $request->file('image')]);

            return redirect()->route($this->routeName('quizzes.show'), $quiz)
                ->with('success', 'Question updated successfully!');

        } catch (\Exception $e) {
            \Log::error('Failed to update question: ' . $e->getMessage());
            return back()->with('error', 'Failed to update question: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete a question
     */
    public function deleteQuestion(Quiz $quiz, QuizQuestion $question)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        // Verify question belongs to this quiz
        if ($question->quiz_id !== $quiz->id) {
            abort(404);
        }

        DB::beginTransaction();
        try {
            // Delete image if exists
            if ($question->image_path) {
                \Storage::disk('public')->delete($question->image_path);
            }

            // Delete options
            $question->options()->delete();

            // Delete question
            $question->delete();

            DB::commit();

            return redirect()->route($this->routeName('quizzes.show'), $quiz)
                ->with('success', 'Question deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete question: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete question: ' . $e->getMessage());
        }
    }

    public function downloadTemplate(Quiz $quiz)
    {
        $this->authorize('view', $quiz);

        $filename = 'quiz_import_template.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $columns = [
            'question_text',
            'question_type',
            'points',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_answer',
            'acceptable_answers',
            'word_bank',
            'case_sensitive',
            'image_filename'
        ];

        $samples = [
            [
                'What is 2 + 2?',
                'multiple_choice',
                '1',
                'Two',
                'Three',
                'Four',
                'Five',
                '2', // Index of correct option (0=A, 1=B, 2=C, 3=D)
                '',
                '',
                '0',
                ''
            ],
            [
                'The sky is _____ during the day.',
                'fill_blank_text',
                '1',
                '',
                '',
                '',
                '',
                '',
                'blue|Blue', // Pipe-separated alternatives for single blank
                '',
                '0', // 0=case insensitive, 1=case sensitive
                ''
            ],
            [
                'Identify this plant part.',
                'identification',
                '2',
                '',
                '',
                '',
                '',
                '',
                'leaf|leaves', // Acceptable answers (pipe-separated alternatives)
                '',
                '0',
                'plant_leaf.jpg' // Simple filename - system finds it with timestamp
            ],
            [
                'The _____ is green and the _____ is blue.',
                'fill_blank_select',
                '2',
                '',
                '',
                '',
                '',
                '',
                'grass;sky', // Semicolon-separated for multiple blanks
                'grass,sky,sun,moon,cloud', // Word bank (comma-separated)
                '0',
                ''
            ],
            [
                'Earth orbits around the Sun.',
                'true_false',
                '1',
                '', // Leave empty - options are auto-generated as "True" and "False"
                '',
                '',
                '',
                '0', // 0=True, 1=False
                '',
                '',
                '0',
                ''
            ],
            [
                'Which are fruits? (Select all that apply)',
                'multiple_select',
                '2',
                'Apple',
                'Carrot',
                'Banana',
                'Potato',
                '0,2', // Comma-separated indices of correct options
                '',
                '',
                '0',
                ''
            ]
        ];

        $callback = function() use ($columns, $samples) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($samples as $sample) {
                fputcsv($file, $sample);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function previewImport(Request $request, Quiz $quiz)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120', // Max 5MB
        ]);

        try {
            $file = $request->file('csv_file');
            $handle = fopen($file->getRealPath(), 'r');
            
            // Read header row
            $headers = fgetcsv($handle);
            
            if (!$headers) {
                return back()->with('error', 'CSV file is empty or invalid.');
            }

            $validRows = [];
            $invalidRows = [];
            $rowNumber = 1; // Start from 1 (header is 0)

            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;
                
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                $row = array_combine($headers, $data);
                
                // Resolve image filename if present
                if (!empty($row['image_filename'])) {
                    $resolvedImage = $this->resolveImageFilename($row['image_filename']);
                    if ($resolvedImage) {
                        $row['_resolved_image'] = $resolvedImage;
                    }
                }
                
                $errors = $this->validateCsvRow($row, $quiz->id);

                if (empty($errors)) {
                    $validRows[] = [
                        'row_number' => $rowNumber,
                        'data' => $row
                    ];
                } else {
                    $invalidRows[] = [
                        'row_number' => $rowNumber,
                        'data' => $row,
                        'errors' => $errors
                    ];
                }
            }

            fclose($handle);

            // Store valid rows in session for confirmation
            session(['csv_import_data' => [
                'quiz_id' => $quiz->id,
                'valid_rows' => $validRows
            ]]);

            return view('instructor.quizzes.import-preview', compact('quiz', 'validRows', 'invalidRows'));

        } catch (\Exception $e) {
            \Log::error('CSV Import Preview Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to parse CSV: ' . $e->getMessage());
        }
    }

    private function validateCsvRow($row, $quizId)
    {
        $errors = [];

        // Required fields
        if (empty($row['question_text'])) {
            $errors[] = 'Question text is required.';
        }

        if (empty($row['question_type'])) {
            $errors[] = 'Question type is required.';
        } else {
            $validTypes = ['multiple_choice', 'true_false', 'multiple_select', 'fill_blank_text', 'fill_blank_select', 'identification'];
            if (!in_array($row['question_type'], $validTypes)) {
                $errors[] = 'Invalid question type. Must be one of: ' . implode(', ', $validTypes);
            }
        }

        if (empty($row['points']) || !is_numeric($row['points']) || $row['points'] < 1) {
            $errors[] = 'Points must be a positive integer.';
        }

        // Type-specific validation
        if (!empty($row['question_type'])) {
            switch ($row['question_type']) {
                case 'multiple_choice':
                case 'multiple_select':
                    if (empty($row['option_a']) || empty($row['option_b'])) {
                        $errors[] = 'Options A and B are required for option-based questions.';
                    }
                    if (empty($row['correct_answer']) && $row['correct_answer'] !== '0') {
                        $errors[] = 'Correct answer is required.';
                    }
                    break;

                case 'true_false':
                    // True/False doesn't require options in CSV - they're auto-generated as "True" and "False"
                    if (empty($row['correct_answer']) && $row['correct_answer'] !== '0') {
                        $errors[] = 'Correct answer is required (0=True, 1=False).';
                    } elseif (!in_array($row['correct_answer'], ['0', '1'])) {
                        $errors[] = 'Correct answer must be 0 (True) or 1 (False).';
                    }
                    break;

                case 'fill_blank_text':
                    if (empty($row['acceptable_answers'])) {
                        $errors[] = 'Acceptable answers are required for fill_blank_text.';
                    }
                    // Count blanks in question text
                    $blankCount = substr_count($row['question_text'], '_____');
                    if ($blankCount === 0) {
                        $errors[] = 'Fill in the blank questions must contain at least one blank (_____)';
                    }
                    // For fill_blank_text: if multiple blanks, answers should be separated by semicolons
                    // Each blank can have multiple acceptable alternatives separated by pipes
                    if ($blankCount > 1 && !empty($row['acceptable_answers'])) {
                        $blanksAnswers = explode(';', $row['acceptable_answers']);
                        if (count($blanksAnswers) !== $blankCount) {
                            $errors[] = "For $blankCount blanks, provide $blankCount answer groups separated by semicolons (e.g., 'ans1a|ans1b;ans2a|ans2b').";
                        }
                    }
                    break;

                case 'identification':
                    if (empty($row['acceptable_answers'])) {
                        $errors[] = 'Acceptable answers are required for identification.';
                    }
                    break;

                case 'fill_blank_select':
                    if (empty($row['acceptable_answers'])) {
                        $errors[] = 'Acceptable answers are required.';
                    }
                    if (empty($row['word_bank'])) {
                        $errors[] = 'Word bank is required for fill_blank_select.';
                    }
                    $blankCount = substr_count($row['question_text'], '_____');
                    if ($blankCount === 0) {
                        $errors[] = 'Question must contain at least one blank (_____)';
                    }
                    // For fill_blank_select: answers must match blank count (semicolon-separated for multiple blanks)
                    if ($blankCount > 0 && !empty($row['acceptable_answers'])) {
                        $answers = explode(';', $row['acceptable_answers']);
                        if (count($answers) !== $blankCount) {
                            $errors[] = "For $blankCount blanks, provide $blankCount answers separated by semicolons.";
                        }
                    }
                    break;
            }
        }

        // Image validation
        if (!empty($row['image_filename'])) {
            // Check if image exists (either exact match or with timestamp prefix)
            if (empty($row['_resolved_image'])) {
                $errors[] = 'Image file "' . $row['image_filename'] . '" not found in image library. Upload it first in the Image Library page.';
            }
        }

        return $errors;
    }

    private function resolveImageFilename($filename)
    {
        $safeFilename = basename((string) $filename);

        if (str_contains((string) $filename, '/')) {
            if (\Storage::disk('public')->exists((string) $filename)) {
                return (string) $filename;
            }
        }

        $scopedPath = $this->imageLibraryDirectory() . '/' . $safeFilename;
        if (\Storage::disk('public')->exists($scopedPath)) {
            return $scopedPath;
        }

        // Check exact match first
        if (\Storage::disk('public')->exists('quiz-images/' . $safeFilename)) {
            return 'quiz-images/' . $safeFilename;
        }
        
        // Try to find image with timestamp prefix (e.g., 1234567890_plant_leaf.jpg)
        $allImages = array_merge(
            \Storage::disk('public')->files($this->imageLibraryDirectory()),
            \Storage::disk('public')->files('quiz-images')
        );

        foreach ($allImages as $imagePath) {
            $imageFilename = basename($imagePath);
            // Check if filename ends with the provided name (ignoring timestamp prefix)
            if (str_ends_with($imageFilename, $safeFilename) || 
                preg_match('/^\d+_' . preg_quote($safeFilename, '/') . '$/', $imageFilename)) {
                return $imagePath;
            }
        }
        
        return null;
    }

    public function confirmImport(Request $request, Quiz $quiz)
    {
        $this->authorize('update', $quiz);
        $this->ensureAdminCanMutateQuiz($quiz);

        $importData = session('csv_import_data');

        if (!$importData || $importData['quiz_id'] !== $quiz->id) {
            return redirect()->route($this->routeName('quizzes.show'), $quiz)
                ->with('error', 'Import session expired. Please upload the CSV again.');
        }

        $validRows = $importData['valid_rows'];
        $imported = 0;
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($validRows as $rowData) {
                $row = $rowData['data'];

                // Check if question already exists (by question_text)
                $existingQuestion = QuizQuestion::where('quiz_id', $quiz->id)
                    ->where('question_text', $row['question_text'])
                    ->first();

                $questionData = [
                    'quiz_id' => $quiz->id,
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'],
                    'points' => (int) $row['points'],
                    'case_sensitive' => !empty($row['case_sensitive']) && $row['case_sensitive'] === '1',
                ];

                // Type-specific fields
                if (in_array($row['question_type'], ['fill_blank_text', 'fill_blank_select', 'identification'])) {
                    // Convert semicolon-separated answers to pipe-separated for storage
                    // Semicolons separate multiple blanks, pipes separate alternatives for same blank
                    $questionData['acceptable_answers'] = $row['acceptable_answers'];
                }

                if ($row['question_type'] === 'fill_blank_select') {
                    $wordBank = array_map('trim', explode(',', $row['word_bank']));
                    $questionData['word_bank'] = $wordBank;
                }

                // Image handling - use resolved filename if available
                if (!empty($row['_resolved_image'])) {
                    $questionData['image_path'] = $row['_resolved_image'];
                } elseif (!empty($row['image_filename'])) {
                    // Fallback to direct filename
                    $questionData['image_path'] = $this->imageLibraryDirectory() . '/' . basename((string) $row['image_filename']);
                }

                if ($existingQuestion) {
                    // Update existing question
                    $existingQuestion->update($questionData);
                    // Delete old options
                    $existingQuestion->options()->delete();
                    $question = $existingQuestion;
                    $updated++;
                } else {
                    // Create new question
                    $question = QuizQuestion::create($questionData);
                    $imported++;
                }

                // Create options for option-based questions
                if (in_array($row['question_type'], ['multiple_choice', 'true_false', 'multiple_select'])) {
                    $options = [];
                    
                    if ($row['question_type'] === 'true_false') {
                        // Auto-generate True/False options
                        $options = ['True', 'False'];
                    } else {
                        // Collect options from CSV
                        foreach (['option_a', 'option_b', 'option_c', 'option_d'] as $index => $key) {
                            if (!empty($row[$key])) {
                                $options[] = $row[$key];
                            }
                        }
                    }

                    foreach ($options as $index => $optionText) {
                        $isCorrect = false;

                        if ($row['question_type'] === 'multiple_choice' || $row['question_type'] === 'true_false') {
                            $isCorrect = (int) $row['correct_answer'] === $index;
                        } elseif ($row['question_type'] === 'multiple_select') {
                            $correctIndices = array_map('intval', explode(',', $row['correct_answer']));
                            $isCorrect = in_array($index, $correctIndices);
                        }

                        QuizOption::create([
                            'quiz_question_id' => $question->id,
                            'option_text' => $optionText,
                            'is_correct' => $isCorrect,
                        ]);
                    }
                }
            }

            DB::commit();
            session()->forget('csv_import_data');

            $message = "Successfully imported $imported new question(s)";
            if ($updated > 0) {
                $message .= " and updated $updated existing question(s)";
            }
            $message .= ".";

            return redirect()->route($this->routeName('quizzes.show'), $quiz)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('CSV Import Confirm Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to import questions: ' . $e->getMessage());
        }
    }

    private function panelContext(): ContentPanelContext
    {
        return app(ContentPanelContext::class);
    }

    private function routeName(string $suffix): string
    {
        return $this->panelContext()->name($suffix);
    }

    private function ensureAdminCanMutateModuleById(int $moduleId): void
    {
        if (!$this->panelContext()->isAdmin()) {
            return;
        }

        $module = Module::query()->findOrFail($moduleId);
        $ownerType = $this->ownershipGuard()->ownerTypeForModule($module);

        abort_unless(
            $this->ownershipGuard()->canAdminMutateOwnerType($ownerType),
            403,
            'Admins can only modify platform-owned learning content.',
        );
    }

    private function ensureAdminCanMutateQuiz(Quiz $quiz): void
    {
        if (!$this->panelContext()->isAdmin()) {
            return;
        }

        $ownerType = $this->ownershipGuard()->ownerTypeForQuiz($quiz);

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

    private function imageLibraryDirectory(): string
    {
        return 'quiz-images/user-' . (int) Auth::id();
    }
}
