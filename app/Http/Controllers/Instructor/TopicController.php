<?php

namespace App\Http\Controllers\Instructor;

use App\Helpers\VideoEmbedHelper;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\Content\ContentOwnershipGuard;
use App\Services\Learning\QuestionAuthoringService;
use App\Support\ContentPanelContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    public function __construct(private QuestionAuthoringService $questionAuthoring) {}

    public function create(Request $request)
    {
        $lesson = Lesson::findOrFail($request->lesson);
        $this->authorize('update', $lesson);
        $this->ensureAdminCanMutateLesson($lesson);
        $quizzes = Quiz::select('id', 'title')->get();

        return view('instructor.topics.create', compact('lesson', 'quizzes'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', LessonTopic::class);

        $lessonForAuthorization = Lesson::query()->findOrFail((int) $request->input('lesson_id'));
        $this->authorize('update', $lessonForAuthorization);
        $this->ensureAdminCanMutateLesson($lessonForAuthorization);

        if ($request->input('type') === 'interactive_checkpoint') {
            return $this->storeCheckpoint($request, $lessonForAuthorization);
        }

        // Log the request for debugging
        \Log::info('Topic creation request START', [
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'has_images' => $request->hasFile('image_attachments'),
            'image_count' => $request->hasFile('image_attachments') ? count($request->file('image_attachments')) : 0,
            'has_text' => ! empty($request->input('text_content')),
            'is_prerequisite_in_request' => $request->has('is_prerequisite'),
            'is_prerequisite_value' => $request->input('is_prerequisite'),
            'all_inputs' => $request->except(['_token', 'text_content']),
        ]);

        try {
            $validated = $request->validate([
                'lesson_id' => 'required|exists:lessons,id',
                'title' => 'required|string|max:255',
                'type' => 'required|in:video,text,worksheet,interactive,interactive_checkpoint',
                'duration' => 'required|integer|min:1',
                'is_prerequisite' => 'nullable|boolean',

                // Video fields
                'video_source' => 'nullable|required_if:type,video|in:url,upload',
                'video_url' => 'nullable|required_if:video_source,url|string',
                'video_file' => 'nullable|required_if:video_source,upload|file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/webm|max:102400',
                'video_description' => 'nullable|string',

                // Text fields (text_content is optional if images are provided)
                'text_content' => 'nullable|string',
                'image_attachments.*' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,svg|max:2048',
                'excluded_image_indices' => 'nullable|array',
                'excluded_image_indices.*' => 'integer',

                // Worksheet fields
                'worksheet_files.*' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'worksheet_instructions' => 'nullable|string',

                // Interactive fields
                'interactive_type' => 'nullable|required_if:type,interactive|in:activity,simulation,exercise',
                'interactive_instructions' => 'nullable|string',
            ]);

            \Log::info('Validation passed', ['validated' => array_keys($validated)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except(['_token', 'text_content']),
            ]);

            // Return JSON for AJAX requests
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        // Validate that text topics have either text_content or image_attachments
        if ($validated['type'] === 'text') {
            if (empty($request->input('text_content')) && ! $request->hasFile('image_attachments')) {
                \Log::warning('Text topic validation failed: no content or images');

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['text_content' => ['Please provide either text content or image attachments.']],
                    ], 422);
                }

                return back()->withErrors(['text_content' => 'Please provide either text content or image attachments.'])->withInput();
            }
        }

        // Validate that worksheet topics have at least one file
        if ($validated['type'] === 'worksheet') {
            if (! $request->hasFile('worksheet_files')) {
                \Log::warning('Worksheet topic validation failed: no files');

                return back()->withErrors(['worksheet_files' => 'Please upload at least one worksheet file.'])->withInput();
            }
        }

        $lesson = Lesson::findOrFail($validated['lesson_id']);

        \Log::info('Processing topic creation', ['lesson_id' => $lesson->id, 'type' => $validated['type']]);

        // Handle video
        if ($validated['type'] === 'video') {
            if ($request->hasFile('video_file')) {
                $validated['video_file_path'] = $request->file('video_file')->store('videos', 'public');
                $validated['video_provider'] = 'local';
                $validated['video_id'] = null;
            } elseif (! empty($validated['video_url'])) {
                $videoData = VideoEmbedHelper::parseVideoUrl($validated['video_url']);
                $validated['video_provider'] = $videoData['provider'];
                $validated['video_id'] = $videoData['video_id'];
                $validated['video_file_path'] = null;
            }
            // Store video description in text_content
            $validated['text_content'] = $request->input('video_description');
        }

        // Handle image attachments for text topics
        if ($request->hasFile('image_attachments')) {
            $excludedIndices = $request->input('excluded_image_indices', []);
            $imageFiles = $request->file('image_attachments');

            \Log::info('Processing image attachments', [
                'total_count' => count($imageFiles),
                'excluded_count' => count($excludedIndices),
                'excluded_indices' => $excludedIndices,
            ]);

            $imagePaths = [];
            $captions = $request->all(); // Get all request data for caption fields

            foreach ($imageFiles as $index => $image) {
                // Skip excluded images
                if (in_array($index, $excludedIndices)) {
                    \Log::info('Skipping excluded image', ['index' => $index]);

                    continue;
                }

                try {
                    $path = $image->store('lesson-images', 'public');

                    // Look for caption with this index
                    $captionKey = 'image_captions_'.$index;
                    $caption = isset($captions[$captionKey]) ? $captions[$captionKey] : null;

                    $imagePaths[] = [
                        'path' => $path,
                        'caption' => $caption,
                        'original_name' => $image->getClientOriginalName(),
                    ];

                    \Log::info('Image stored', ['index' => $index, 'path' => $path, 'caption' => $caption]);
                } catch (\Exception $e) {
                    \Log::error('Failed to store image', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $validated['image_attachments'] = $imagePaths;

            \Log::info('All images processed', ['stored_count' => count($imagePaths)]);

            // Store data for both gallery and slideshow display (learner can toggle)
            $validated['slideshow_data'] = [
                'enabled' => true,
                'gallery_mode' => 'grid',
                'slideshow_mode' => 'slide',
                'auto_play' => false,
                'show_thumbnails' => true,
                'allow_toggle' => true, // Learner can switch between gallery/slideshow
            ];
        }

        // Handle worksheet file uploads (multiple files)
        if ($request->hasFile('worksheet_files')) {
            $worksheetPaths = [];

            foreach ($request->file('worksheet_files') as $index => $file) {
                $path = $file->store('worksheets', 'public');
                $worksheetPaths[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $validated['worksheet_files'] = $worksheetPaths;
            // Store first file path in file_path for backward compatibility
            $validated['file_path'] = $worksheetPaths[0]['path'] ?? null;
            $validated['text_content'] = $request->input('worksheet_instructions');
        }

        // Handle interactive configuration
        if ($validated['type'] === 'interactive') {
            $validated['interactive_config'] = [
                'type' => $request->input('interactive_type'),
                'instructions' => $request->input('interactive_instructions'),
            ];
        }

        // Auto-increment order
        $validated['order'] = $lesson->topics()->max('order') + 1;

        // Set prerequisite status - checkbox only sends value when checked
        // When unchecked, the field is not present in the request
        $validated['is_prerequisite'] = $request->has('is_prerequisite');

        \Log::info('Final prerequisite value', [
            'is_prerequisite' => $validated['is_prerequisite'],
            'has_in_request' => $request->has('is_prerequisite'),
            'input_value' => $request->input('is_prerequisite'),
        ]);

        // Clean up temporary fields that shouldn't be stored in database
        $temporaryFields = ['video_source', 'video_url', 'video_file', 'video_description', 'image_captions', 'worksheet_instructions', 'interactive_type', 'interactive_instructions'];
        foreach ($temporaryFields as $field) {
            unset($validated[$field]);
        }

        \Log::info('Creating topic', ['data_keys' => array_keys($validated)]);

        try {
            $topic = $lesson->topics()->create($validated);
            \Log::info('Topic created successfully', ['topic_id' => $topic->id]);
        } catch (\Exception $e) {
            \Log::error('Failed to create topic', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['error' => ['Failed to create topic: '.$e->getMessage()]],
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to create topic: '.$e->getMessage()])->withInput();
        }

        // Update lesson duration (sum of all topics)
        $lesson->duration = $lesson->topics()->sum('duration');
        $lesson->save();

        // Update module duration (sum of all lessons)
        $module = $lesson->module;
        $module->duration_minutes = $module->lessons()->sum('duration');
        $module->save();

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Topic created successfully!',
                'redirect' => route($this->routeName('lessons.show'), $lesson),
            ]);
        }

        return redirect()->route($this->routeName('lessons.show'), $lesson)
            ->with('success', 'Topic created successfully!');
    }

    public function edit(LessonTopic $topic)
    {
        $this->authorize('update', $topic);
        $this->ensureAdminCanMutateTopic($topic);
        $topic->load('lesson');

        if ($topic->type === 'interactive_checkpoint') {
            $question = $topic->checkpointQuestion()->with('options')->firstOrFail();

            return view('instructor.topics.edit-checkpoint', [
                'topic' => $topic,
                'question' => $question,
                'placement' => 'between_topics',
                'formAction' => route($this->routeName('topics.update'), $topic),
            ]);
        }

        $quizzes = Quiz::select('id', 'title')->get();

        return view('instructor.topics.edit', compact('topic', 'quizzes'));
    }

    public function update(Request $request, LessonTopic $topic)
    {
        $this->authorize('update', $topic);
        $this->ensureAdminCanMutateTopic($topic);

        if ($topic->type === 'interactive_checkpoint') {
            return $this->updateBetweenTopicCheckpoint($request, $topic);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:video,text,worksheet,interactive,interactive_checkpoint',
            'duration' => 'required|integer|min:1',
            'is_prerequisite' => 'nullable|boolean',
            'checkpoint_placement' => 'nullable|required_if:type,interactive_checkpoint|in:inside_topic,between_topics',
            'parent_topic_id' => 'nullable|required_if:checkpoint_placement,inside_topic|integer|exists:lesson_topics,id',
            'insert_after_block' => 'nullable|integer|min:0',

            // Video fields
            'video_source' => 'nullable|required_if:type,video|in:url,upload',
            'video_url' => 'nullable|string',
            'video_file' => 'nullable|file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/webm|max:102400',
            'video_description' => 'nullable|string',

            // Text fields
            'text_content' => 'nullable|string',
            'image_attachments.*' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,svg|max:2048',
            'image_captions.*' => 'nullable|string',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer',

            // Worksheet fields
            'worksheet_files.*' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'worksheet_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'worksheet_instructions' => 'nullable|string',

            // Interactive fields
            'interactive_type' => 'nullable|required_if:type,interactive|in:activity,simulation,exercise',
            'activity_type' => 'nullable|in:activity,simulation,exercise',
            'interactive_instructions' => 'nullable|string',
        ]);

        if ($validated['type'] === 'text') {
            $existingImages = is_array($topic->image_attachments) ? count($topic->image_attachments) : 0;
            $imagesMarkedForDelete = count($request->input('delete_images', []));
            $remainingExistingImages = max(0, $existingImages - $imagesMarkedForDelete);

            if (empty($request->input('text_content')) && ! $request->hasFile('image_attachments') && $remainingExistingImages === 0) {
                return back()->withErrors([
                    'text_content' => 'Please provide either text content or image attachments.',
                ])->withInput();
            }
        }

        if ($validated['type'] === 'worksheet' && ! $request->hasFile('worksheet_files') && ! $request->hasFile('worksheet_file') && empty($topic->worksheet_files) && empty($topic->file_path)) {
            return back()->withErrors([
                'worksheet_files' => 'Please upload at least one worksheet file.',
            ])->withInput();
        }

        // Handle video
        if ($validated['type'] === 'video') {
            if ($request->hasFile('video_file')) {
                // Delete old video file if exists
                if ($topic->video_file_path) {
                    Storage::disk('public')->delete($topic->video_file_path);
                }
                $validated['video_file_path'] = $request->file('video_file')->store('videos', 'public');
                $validated['video_provider'] = 'local';
                $validated['video_id'] = null;
            } elseif (! empty($validated['video_url'])) {
                $videoData = VideoEmbedHelper::parseVideoUrl($validated['video_url']);
                $validated['video_provider'] = $videoData['provider'];
                $validated['video_id'] = $videoData['video_id'];
                if ($topic->video_file_path) {
                    Storage::disk('public')->delete($topic->video_file_path);
                }
                $validated['video_file_path'] = null;
            }

            $validated['text_content'] = $request->input('video_description');
        }

        // Handle image attachments for text topics
        if ($validated['type'] === 'text') {
            $currentImages = is_array($topic->image_attachments) ? $topic->image_attachments : [];
            $deleteIndices = collect($request->input('delete_images', []))
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->values();

            $remainingImages = [];
            foreach ($currentImages as $index => $oldImage) {
                if ($deleteIndices->contains((int) $index)) {
                    if (isset($oldImage['path'])) {
                        Storage::disk('public')->delete($oldImage['path']);
                    }

                    continue;
                }

                $remainingImages[] = $oldImage;
            }

            if ($request->hasFile('image_attachments')) {
                $captions = $request->input('image_captions', []);

                foreach ($request->file('image_attachments') as $index => $image) {
                    $path = $image->store('lesson-images', 'public');
                    $remainingImages[] = [
                        'path' => $path,
                        'caption' => $captions[$index] ?? null,
                        'original_name' => $image->getClientOriginalName(),
                    ];
                }
            }

            $validated['image_attachments'] = $remainingImages;
            $validated['slideshow_data'] = [
                'enabled' => true,
                'gallery_mode' => 'grid',
                'slideshow_mode' => 'slide',
                'auto_play' => false,
                'show_thumbnails' => true,
                'allow_toggle' => true,
            ];
        }

        // Handle worksheet file upload(s)
        if ($request->hasFile('worksheet_files') || $request->hasFile('worksheet_file')) {
            $worksheetUploads = [];

            if ($request->hasFile('worksheet_files')) {
                $worksheetUploads = array_merge($worksheetUploads, $request->file('worksheet_files'));
            }

            if ($request->hasFile('worksheet_file')) {
                $worksheetUploads[] = $request->file('worksheet_file');
            }

            if ($topic->worksheet_files) {
                foreach ($topic->worksheet_files as $oldFile) {
                    if (isset($oldFile['path'])) {
                        Storage::disk('public')->delete($oldFile['path']);
                    }
                }
            }

            if ($topic->file_path) {
                Storage::disk('public')->delete($topic->file_path);
            }

            $worksheetPaths = [];
            foreach ($worksheetUploads as $file) {
                $path = $file->store('worksheets', 'public');
                $worksheetPaths[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            $validated['worksheet_files'] = $worksheetPaths;
            $validated['file_path'] = $worksheetPaths[0]['path'] ?? null;
            $validated['text_content'] = $request->input('worksheet_instructions');
        } elseif ($validated['type'] === 'worksheet') {
            $validated['text_content'] = $request->input('worksheet_instructions');
        }

        // Handle interactive configuration
        if ($validated['type'] === 'interactive') {
            $interactiveType = $request->input('interactive_type', $request->input('activity_type'));
            $validated['interactive_config'] = [
                'type' => $interactiveType,
                'instructions' => $request->input('interactive_instructions'),
            ];
        }

        // Set prerequisite status based on checkbox presence
        $validated['is_prerequisite'] = $request->has('is_prerequisite');

        \Log::info('UPDATE - Final prerequisite value', [
            'is_prerequisite' => $validated['is_prerequisite'],
            'has_in_request' => $request->has('is_prerequisite'),
            'input_value' => $request->input('is_prerequisite'),
            'previous_value' => $topic->is_prerequisite,
        ]);

        // Clean up temporary fields that shouldn't be stored in database
        $temporaryFields = [
            'video_source',
            'video_url',
            'video_file',
            'video_description',
            'image_captions',
            'worksheet_instructions',
            'worksheet_file',
            'worksheet_files',
            'interactive_type',
            'activity_type',
            'interactive_instructions',
            'delete_images',
        ];
        foreach ($temporaryFields as $field) {
            unset($validated[$field]);
        }

        $topic->update($validated);

        // Update lesson duration
        $lesson = $topic->lesson;
        $lesson->duration = $lesson->topics()->sum('duration');
        $lesson->save();

        // Update module duration
        $module = $lesson->module;
        $module->duration_minutes = $module->lessons()->sum('duration');
        $module->save();

        return redirect()->route($this->routeName('lessons.show'), $topic->lesson)
            ->with('success', 'Topic updated successfully!');
    }

    public function editCheckpoint(LessonTopic $topic, QuizQuestion $question)
    {
        $this->authorize('update', $topic);
        $this->ensureAdminCanMutateTopic($topic);
        $topic->load('lesson');
        $this->assertInsideCheckpointBelongsToTopic($topic, $question);

        return view('instructor.topics.edit-checkpoint', [
            'topic' => $topic,
            'question' => $question->load('options'),
            'placement' => 'inside_topic',
            'formAction' => route($this->routeName('topics.checkpoints.update'), [$topic, $question]),
        ]);
    }

    public function updateCheckpoint(Request $request, LessonTopic $topic, QuizQuestion $question)
    {
        $this->authorize('update', $topic);
        $this->ensureAdminCanMutateTopic($topic);
        $this->assertInsideCheckpointBelongsToTopic($topic, $question);
        $request->merge(['points' => 1]);
        $questionData = $this->questionAuthoring->validate($request);

        $this->questionAuthoring->updateQuestion($question, $questionData);

        return redirect()->route($this->routeName('lessons.show'), $topic->lesson)
            ->with('success', 'Interactive checkpoint updated successfully.');
    }

    private function updateBetweenTopicCheckpoint(Request $request, LessonTopic $topic)
    {
        $topicData = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);
        $request->merge(['points' => 1]);
        $questionData = $this->questionAuthoring->validate($request);
        $question = $topic->checkpointQuestion()->with('options')->firstOrFail();

        DB::transaction(function () use ($topic, $topicData, $question, $questionData) {
            $topic->update([
                'title' => $topicData['title'],
                'duration' => 0,
                'is_prerequisite' => false,
                'interactive_config' => ['placement' => 'between_topics'],
            ]);
            $topic->lesson->update(['duration' => $topic->lesson->topics()->instructional()->sum('duration')]);
            $topic->lesson->module->update([
                'duration_minutes' => $topic->lesson->module->lessons()->sum('duration'),
            ]);
            $this->questionAuthoring->updateQuestion($question, $questionData);
        });

        return redirect()->route($this->routeName('lessons.show'), $topic->lesson)
            ->with('success', 'Interactive checkpoint updated successfully.');
    }

    private function assertInsideCheckpointBelongsToTopic(LessonTopic $topic, QuizQuestion $question): void
    {
        abort_unless(
            (int) $question->checkpoint_topic_id === (int) $topic->id
            && $question->checkpoint_block_uuid !== null
            && collect($this->blocksForTopic($topic))->contains(fn ($block) => ($block['type'] ?? null) === 'checkpoint'
                && ($block['uuid'] ?? null) === $question->checkpoint_block_uuid
                && (int) ($block['question_id'] ?? 0) === (int) $question->id
            ),
            404,
        );
    }

    public function destroy(LessonTopic $topic)
    {
        $this->authorize('delete', $topic);
        $this->ensureAdminCanMutateTopic($topic);

        $lesson = $topic->lesson;

        // Delete associated files
        if ($topic->video_file_path) {
            Storage::disk('public')->delete($topic->video_file_path);
        }
        if ($topic->file_path) {
            Storage::disk('public')->delete($topic->file_path);
        }
        if ($topic->image_attachments) {
            foreach ($topic->image_attachments as $image) {
                if (isset($image['path'])) {
                    Storage::disk('public')->delete($image['path']);
                }
            }
        }

        $topic->delete();

        // Update lesson duration
        $lesson->duration = $lesson->topics()->instructional()->sum('duration');
        $lesson->save();

        // Update module duration
        $module = $lesson->module;
        $module->duration_minutes = $module->lessons()->sum('duration');
        $module->save();

        return redirect()->route($this->routeName('lessons.show'), $lesson)
            ->with('success', 'Topic deleted successfully!');
    }

    private function routeName(string $suffix): string
    {
        return app(ContentPanelContext::class)->name($suffix);
    }

    private function storeCheckpoint(Request $request, Lesson $lesson)
    {
        $placement = $request->validate([
            'lesson_id' => ['required', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'checkpoint_placement' => ['required', 'in:inside_topic,between_topics'],
            'parent_topic_id' => ['nullable', 'required_if:checkpoint_placement,inside_topic', 'integer', 'exists:lesson_topics,id'],
            'insert_after_block' => ['nullable', 'integer', 'min:0'],
        ]);
        $request->merge(['points' => 1]);
        $questionData = $this->questionAuthoring->validate($request);

        return DB::transaction(function () use ($placement, $questionData, $lesson) {
            if ($placement['checkpoint_placement'] === 'inside_topic') {
                $parentTopic = $lesson->topics()
                    ->where('type', '!=', 'interactive_checkpoint')
                    ->findOrFail($placement['parent_topic_id']);
                $this->authorize('update', $parentTopic);
                $blockUuid = (string) Str::uuid();
                $question = $this->questionAuthoring->createQuestion($questionData, [
                    'quiz_id' => null,
                    'checkpoint_topic_id' => $parentTopic->id,
                    'checkpoint_block_uuid' => $blockUuid,
                    'order' => $parentTopic->checkpointQuestions()->count() + 1,
                ]);

                $blocks = $this->blocksForTopic($parentTopic);
                $insertAfter = (int) ($placement['insert_after_block'] ?? 0);
                array_splice($blocks, min($insertAfter + 1, count($blocks)), 0, [[
                    'type' => 'checkpoint',
                    'uuid' => $blockUuid,
                    'question_id' => $question->id,
                ]]);
                $parentTopic->update(['content_blocks' => array_values($blocks)]);

                return redirect()->route($this->routeName('lessons.show'), $lesson)
                    ->with('success', 'Interactive checkpoint added to topic.');
            }

            $topic = $lesson->topics()->create([
                'title' => $placement['title'],
                'type' => 'interactive_checkpoint',
                'duration' => 0,
                'is_prerequisite' => false,
                'order' => $lesson->topics()->max('order') + 1,
                'interactive_config' => ['placement' => 'between_topics'],
            ]);
            $this->questionAuthoring->createQuestion($questionData, [
                'quiz_id' => null,
                'checkpoint_topic_id' => $topic->id,
                'checkpoint_block_uuid' => null,
                'order' => 1,
            ]);

            $lesson->update(['duration' => $lesson->topics()->instructional()->sum('duration')]);
            $lesson->module->update(['duration_minutes' => $lesson->module->lessons()->sum('duration')]);

            return redirect()->route($this->routeName('lessons.show'), $lesson)
                ->with('success', 'Interactive checkpoint created successfully.');
        });
    }

    private function blocksForTopic(LessonTopic $topic): array
    {
        if (is_array($topic->content_blocks) && count($topic->content_blocks) > 0) {
            return $topic->content_blocks;
        }

        return [[
            'type' => 'rich_text',
            'html' => $topic->text_content ?? '',
        ]];
    }

    /**
     * Get topic preview data
     */
    public function preview(LessonTopic $topic)
    {
        $this->authorize('view', $topic);

        $worksheetFiles = [];

        if (is_array($topic->worksheet_files) && count($topic->worksheet_files) > 0) {
            $worksheetFiles = collect($topic->worksheet_files)
                ->map(function ($file) {
                    $path = is_array($file) ? ($file['path'] ?? null) : null;

                    return [
                        'name' => is_array($file) ? ($file['original_name'] ?? basename((string) $path)) : null,
                        'url' => $path ? Storage::url($path) : null,
                        'mime_type' => is_array($file) ? ($file['mime_type'] ?? null) : null,
                    ];
                })
                ->filter(fn ($file) => ! empty($file['url']))
                ->values()
                ->all();
        }

        if (empty($worksheetFiles) && $topic->file_path) {
            $worksheetFiles[] = [
                'name' => basename($topic->file_path),
                'url' => Storage::url($topic->file_path),
                'mime_type' => null,
            ];
        }

        $imageAttachments = collect($topic->image_attachments ?? [])
            ->map(function ($image) {
                $path = is_array($image) ? ($image['path'] ?? null) : null;

                return [
                    'path' => $path,
                    'caption' => is_array($image) ? ($image['caption'] ?? null) : null,
                    'url' => $path ? Storage::url($path) : null,
                ];
            })
            ->filter(fn ($image) => ! empty($image['url']))
            ->values()
            ->all();

        return response()->json([
            'id' => $topic->id,
            'title' => $topic->title,
            'type' => $topic->type,
            'duration' => $topic->duration,
            'is_prerequisite' => $topic->is_prerequisite,
            'video_url' => $topic->video_embed_url,
            'video_provider' => $topic->video_provider,
            'video_id' => $topic->video_id,
            'video_file_path' => $topic->video_file_path,
            'video_file_url' => $topic->video_file_url,
            'video_description' => $topic->video_description,
            'text_content' => $topic->text_content,
            'image_attachments' => $imageAttachments,
            'worksheet_file_path' => $topic->file_path,
            'worksheet_file_url' => $topic->file_path ? Storage::url($topic->file_path) : null,
            'worksheet_files' => $worksheetFiles,
            'worksheet_instructions' => $topic->worksheet_instructions,
            'interactive_type' => $topic->interactive_config['type'] ?? null,
            'interactive_instructions' => $topic->interactive_instructions,
            'interactive_config' => $topic->interactive_config,
            'slideshow_data' => $topic->slideshow_data,
        ]);
    }

    /**
     * Handle image uploads from TinyMCE editor
     */
    public function uploadImage(Request $request)
    {
        $this->authorize('create', LessonTopic::class);

        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('tinymce-images', 'public');
            $url = Storage::url($path);

            return response()->json([
                'location' => $url,
            ]);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer|exists:lesson_topics,id']);

        foreach ($request->order as $index => $id) {
            $topic = LessonTopic::query()->findOrFail($id);
            $this->authorize('update', $topic);
            $this->ensureAdminCanMutateTopic($topic);
            LessonTopic::where('id', $id)->update(['order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    private function ensureAdminCanMutateLesson(Lesson $lesson): void
    {
        if (! $this->isAdminPanel()) {
            return;
        }

        $ownerType = $this->ownershipGuard()->ownerTypeForLesson($lesson);

        abort_unless(
            $this->ownershipGuard()->canAdminMutateOwnerType($ownerType),
            403,
            'Admins can only modify platform-owned learning content.',
        );
    }

    private function ensureAdminCanMutateTopic(LessonTopic $topic): void
    {
        if (! $this->isAdminPanel()) {
            return;
        }

        $ownerType = $this->ownershipGuard()->ownerTypeForTopic($topic);

        abort_unless(
            $this->ownershipGuard()->canAdminMutateOwnerType($ownerType),
            403,
            'Admins can only modify platform-owned learning content.',
        );
    }

    private function isAdminPanel(): bool
    {
        return app(ContentPanelContext::class)->isAdmin();
    }

    private function ownershipGuard(): ContentOwnershipGuard
    {
        return app(ContentOwnershipGuard::class);
    }
}
