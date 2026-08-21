@extends($contentPanelLayout ?? 'layouts.instructor-app')

@section('content')
<div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            <p class="text-sm font-semibold">Please review the highlighted fields before saving.</p>
            <ul class="mt-2 list-disc list-inside text-sm space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" style="display: none;">
        <div class="bg-white rounded-xl p-8 flex flex-col items-center">
            <svg class="animate-spin h-12 w-12 text-purple-700 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-lg font-semibold text-gray-700">Updating topic...</p>
            <p class="text-sm text-gray-500 mt-2">Please wait while we save your changes.</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-purple-600">Edit Topic</p>
                <h1 class="mt-1 text-xl font-bold text-gray-900">{{ $topic->title }}</h1>
                <p class="mt-1 text-sm text-gray-500">Update content, media, and instructions while keeping the learner experience consistent.</p>
            </div>
            <a href="{{ route($contentRoutePrefix . '.lessons.show', $topic->lesson->id) }}" class="inline-flex items-center px-3.5 py-2 rounded-lg text-sm font-semibold border border-gray-200 text-gray-700 hover:bg-gray-100 transition-colors">
                Back to Lesson
            </a>
        </div>
    </div>

    <!-- Form -->
    <form action="{{ route($contentRoutePrefix . '.topics.update', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-8" id="topicEditForm">
        @csrf
        @method('PUT')

        <!-- Basic Information Card -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Basic Information</h2>

            <!-- Topic Title -->
            <div class="mb-6">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                    Topic Title <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    name="title" 
                    id="title" 
                    value="{{ old('title', $topic->title) }}"
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('title') border-red-500 @enderror"
                    required
                >
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Duration -->
            <div class="mb-6">
                <label for="duration" class="block text-sm font-medium text-gray-700 mb-2">
                    Duration (minutes) <span class="text-red-500">*</span>
                </label>
                <input 
                    type="number" 
                    name="duration" 
                    id="duration" 
                    value="{{ old('duration', $topic->duration) }}"
                    min="1"
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('duration') border-red-500 @enderror"
                    required
                >
                @error('duration')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Prerequisite Checkbox -->
            <div class="mb-6">
                <label class="flex items-start gap-3 cursor-pointer group">
                    <input 
                        type="checkbox" 
                        name="is_prerequisite" 
                        id="is_prerequisite" 
                        value="1"
                        {{ old('is_prerequisite', $topic->is_prerequisite) ? 'checked' : '' }}
                        class="w-5 h-5 mt-0.5 text-purple-700 border-2 border-gray-200 rounded focus:ring-2 focus:ring-purple-300 focus:ring-offset-0 cursor-pointer transition-all hover:border-purple-300"
                    >
                    <div class="flex-1">
                        <span class="text-sm font-semibold text-gray-900 group-hover:text-purple-700 transition-colors">
                            Mark as Prerequisite Topic
                        </span>
                        <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                            If checked, learners must complete this topic before proceeding to the next prerequisite topic in sequence
                        </p>
                    </div>
                </label>
            </div>

            <!-- Topic Type Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-4">
                    Topic Type <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Video Type -->
                    <label class="relative flex flex-col items-center p-6 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:shadow-md transition-all topic-type-card">
                        <input 
                            type="radio" 
                            name="type" 
                            value="video" 
                            class="sr-only topic-type-radio"
                            {{ old('type', $topic->type) === 'video' ? 'checked' : '' }}
                            required
                        >
                        <svg class="w-12 h-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900">Video</span>
                    </label>

                    <!-- Text Type -->
                    <label class="relative flex flex-col items-center p-6 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:shadow-md transition-all topic-type-card">
                        <input 
                            type="radio" 
                            name="type" 
                            value="text" 
                            class="sr-only topic-type-radio"
                            {{ old('type', $topic->type) === 'text' ? 'checked' : '' }}
                            required
                        >
                        <svg class="w-12 h-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900">Text</span>
                    </label>

                    <!-- Worksheet Type -->
                    <label class="relative flex flex-col items-center p-6 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:shadow-md transition-all topic-type-card">
                        <input 
                            type="radio" 
                            name="type" 
                            value="worksheet" 
                            class="sr-only topic-type-radio"
                            {{ old('type', $topic->type) === 'worksheet' ? 'checked' : '' }}
                            required
                        >
                        <svg class="w-12 h-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900">Worksheet</span>
                    </label>

                    <!-- Interactive Checkpoint Type -->
                    <label class="relative flex flex-col items-center p-6 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:shadow-md transition-all topic-type-card">
                        <input
                            type="radio"
                            name="type"
                            value="interactive_checkpoint"
                            class="sr-only topic-type-radio"
                            {{ old('type', $topic->type) === 'interactive_checkpoint' ? 'checked' : '' }}
                            required
                        >
                        <svg class="w-12 h-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-gray-900 text-center">Interactive Checkpoint</span>
                    </label>

                </div>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Video Content Section -->
        <div id="videoContent" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 content-section hidden">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Video Content</h2>

            <!-- Video Source Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Video Source</label>
                <div class="flex space-x-4">
                    <label class="flex items-center space-x-2">
                        <input 
                            type="radio" 
                            name="video_source" 
                            value="url" 
                            class="text-purple-700 focus:ring-purple-300"
                            {{ old('video_source', $topic->video_provider !== 'local' ? 'url' : '') === 'url' ? 'checked' : '' }}
                        >
                        <span class="text-sm text-gray-700">URL (YouTube/Vimeo)</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input 
                            type="radio" 
                            name="video_source" 
                            value="upload" 
                            class="text-purple-700 focus:ring-purple-300"
                            {{ old('video_source', $topic->video_provider === 'local' ? 'upload' : '') === 'upload' ? 'checked' : '' }}
                        >
                        <span class="text-sm text-gray-700">Upload File</span>
                    </label>
                </div>
            </div>

            <!-- Video URL Input -->
            <div id="videoUrlInput" class="mb-6">
                <label for="video_url" class="block text-sm font-medium text-gray-700 mb-2">
                    Video URL
                </label>
                <input 
                    type="url" 
                    name="video_url" 
                    id="video_url" 
                    value="{{ old('video_url', $topic->video_provider !== 'local' ? $topic->video_url : '') }}"
                    placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..."
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('video_url') border-red-500 @enderror"
                >
                <p class="mt-1 text-sm text-gray-500">Enter a YouTube or Vimeo URL</p>
                @error('video_url')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Video File Upload -->
            <div id="videoFileInput" class="mb-6 hidden">
                @if($topic->video_file_path)
                    <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Current Video File</p>
                                    <p class="text-xs text-gray-500">{{ basename($topic->video_file_path) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                <label for="video_file" class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $topic->video_file_path ? 'Replace Video File' : 'Video File' }}
                </label>
                <input 
                    type="file" 
                    name="video_file" 
                    id="video_file" 
                    accept="video/*"
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('video_file') border-red-500 @enderror"
                >
                <p class="mt-1 text-sm text-gray-500">Supported formats: MP4, WebM, OGG (Max: 100MB)</p>
                @error('video_file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="video_description" class="block text-sm font-medium text-gray-700 mb-2">
                    Video Description/Instructions
                </label>
                <textarea
                    name="video_description"
                    id="video_description"
                    rows="4"
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400"
                    placeholder="Add instructions or context for this video..."
                >{{ old('video_description', $topic->text_content) }}</textarea>
            </div>
        </div>

        <!-- Text Content Section -->
        <div id="textContent" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 content-section hidden">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Text Content</h2>

            <!-- Text Editor -->
            <div class="mb-6">
                <label for="text_content" class="block text-sm font-medium text-gray-700 mb-2">
                    Content
                </label>
                <textarea 
                    name="text_content" 
                    id="text_content" 
                    rows="15"
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('text_content') border-red-500 @enderror"
                >{{ old('text_content', $topic->text_content) }}</textarea>
                @error('text_content')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Existing Images -->
            @if($topic->image_attachments && count($topic->image_attachments) > 0)
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Current Images</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($topic->image_attachments as $index => $image)
                            <div class="relative group">
                                <img src="{{ Storage::url($image['path']) }}" alt="Image {{ $index + 1 }}" class="w-full h-32 object-cover rounded-xl border border-gray-200">
                                <div class="absolute top-2 right-2">
                                    <label class="flex items-center space-x-1 bg-white rounded px-2 py-1 shadow-sm">
                                        <input 
                                            type="checkbox" 
                                            name="delete_images[]" 
                                            value="{{ $index }}"
                                            class="w-4 h-4 text-red-600 border-gray-200 rounded focus:ring-red-500"
                                        >
                                        <span class="text-xs text-red-600 font-medium">Delete</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Image Attachments -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $topic->image_attachments ? 'Add More Images (Optional)' : 'Image Attachments (Optional)' }}
                </label>
                <input
                    type="file"
                    name="image_attachments[]"
                    id="image_attachments"
                    accept="image/*"
                    multiple
                    class="hidden"
                >
                <label for="image_attachments"
                    class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-purple-400 hover:bg-purple-50/20 transition-colors">
                    <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                    </svg>
                    <p class="text-sm text-gray-600"><span class="font-semibold text-purple-700">Click to upload</span> new image attachments</p>
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF up to 2MB each</p>
                </label>
            </div>
        </div>

        <!-- Worksheet Content Section -->
        <div id="worksheetContent" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 content-section hidden">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Worksheet Content</h2>

            <!-- Current Worksheet File -->
            @if($topic->file_path)
                <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <svg class="w-8 h-8 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Current Worksheet</p>
                                <p class="text-xs text-gray-500">{{ basename($topic->file_path) }}</p>
                            </div>
                        </div>
                        <a 
                            href="{{ Storage::url($topic->file_path) }}" 
                            target="_blank"
                            class="px-3 py-1 text-sm text-purple-700 hover:text-purple-700 font-medium"
                        >
                            Download
                        </a>
                    </div>
                </div>
            @endif

            <!-- Worksheet File -->
            <div class="mb-6">
                <label for="worksheet_files" class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $topic->file_path ? 'Replace Worksheet File(s)' : 'Worksheet File(s)' }}
                </label>
                <input 
                    type="file" 
                    name="worksheet_files[]" 
                    id="worksheet_files" 
                    accept=".pdf,.doc,.docx"
                    multiple
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('worksheet_files') border-red-500 @enderror"
                >
                <p class="mt-1 text-sm text-gray-500">Supported formats: PDF, DOC, DOCX (Max: 10MB)</p>
                @error('worksheet_files')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Instructions -->
            <div class="mb-6">
                <label for="worksheet_instructions" class="block text-sm font-medium text-gray-700 mb-2">
                    Instructions
                </label>
                <textarea 
                    name="worksheet_instructions" 
                    id="worksheet_instructions" 
                    rows="6"
                    class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-300 focus:border-purple-400 @error('worksheet_instructions') border-red-500 @enderror"
                    placeholder="Enter instructions for completing this worksheet..."
                >{{ old('worksheet_instructions', $topic->text_content) }}</textarea>
                @error('worksheet_instructions')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Interactive Checkpoint Content Section -->
        <div id="interactive_checkpointContent" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 content-section hidden">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Edit Interactive Checkpoint</h2>
            <div class="grid gap-4 md:grid-cols-2 mb-6">
                <label class="rounded-xl border border-gray-200 p-4">
                    <input type="radio" name="checkpoint_placement" value="inside_topic" class="text-purple-600">
                    <span class="ml-2 font-semibold">Inside Topic</span>
                    <p class="mt-1 text-sm text-gray-500">Place this checkpoint within the selected Topic's content.</p>
                </label>
                <label class="rounded-xl border border-gray-200 p-4">
                    <input type="radio" name="checkpoint_placement" value="between_topics" class="text-purple-600" checked>
                    <span class="ml-2 font-semibold">Between Topics</span>
                    <p class="mt-1 text-sm text-gray-500">Place this checkpoint between Topics as a separate step in the Lesson learning flow.</p>
                </label>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Containing Topic for Inside Topic Placement</label>
                <select name="parent_topic_id" class="w-full rounded-xl border-gray-200">
                    @foreach($topic->lesson->topics as $lessonTopic)
                        @if($lessonTopic->type !== 'interactive_checkpoint')
                            <option value="{{ $lessonTopic->id }}">{{ $lessonTopic->title }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            @include('instructor.quizzes.partials.question-fields', ['lesson' => $topic->lesson])
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end space-x-4">
            <a 
                href="{{ route($contentRoutePrefix . '.lessons.show', $topic->lesson->id) }}" 
                class="px-6 py-2 border border-gray-200 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors"
            >
                Cancel
            </a>
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white rounded-xl hover:opacity-90 active:scale-[0.98] transition-all shadow-sm"
                style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
            >
                Update Topic
            </button>
        </div>
    </form>

<script src="{{ asset('build/tinymce/tinymce.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const topicEditForm = document.getElementById('topicEditForm');
    const loadingOverlay = document.getElementById('loadingOverlay');

    topicEditForm?.addEventListener('submit', function() {
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
        }
    });

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    async function uploadTinyMceImage(file) {
        if (!file) {
            throw new Error('No file selected.');
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            throw new Error('Only JPG, PNG, GIF, and WEBP images are allowed.');
        }

        const maxBytes = 5 * 1024 * 1024;
        if (file.size > maxBytes) {
            throw new Error('Image is too large. Maximum allowed size is 5MB.');
        }

        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch('{{ route($contentRoutePrefix . '.upload.image') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        const result = await response.json();

        if (!response.ok || !result.location) {
            throw new Error(result.error || 'Image upload failed.');
        }

        return result.location;
    }

    // Initialize TinyMCE
    tinymce.init({
        selector: '#text_content',
        license_key: 'gpl',
        promotion: false,
        height: 400,
        menubar: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        images_upload_url: '{{ route($contentRoutePrefix . '.upload.image') }}',
        automatic_uploads: true,
        paste_data_images: false,
        images_reuse_filename: true,
        images_file_types: 'jpeg,jpg,png,gif,webp',
        relative_urls: false,
        remove_script_host: false,
        convert_urls: true,
        document_base_url: '{{ url('/') }}/',
        images_upload_handler: async (blobInfo, progress) => {
            progress(15);
            const location = await uploadTinyMceImage(blobInfo.blob());
            progress(100);
            return location;
        },
        file_picker_types: 'image',
        file_picker_callback: async function(callback, value, meta) {
            if (meta.filetype !== 'image') {
                return;
            }

            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', '.jpg,.jpeg,.png,.gif,.webp');

            input.onchange = async function() {
                const file = this.files?.[0];

                if (!file) {
                    return;
                }

                try {
                    const location = await uploadTinyMceImage(file);
                    callback(location, { alt: file.name });
                } catch (error) {
                    tinymce.activeEditor?.notificationManager.open({
                        text: error.message || 'Image upload failed.',
                        type: 'error'
                    });
                }
            };

            input.click();
        },
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }'
    });

    // Get all elements
    const typeRadios = document.querySelectorAll('.topic-type-radio');
    const typeCards = document.querySelectorAll('.topic-type-card');
    const contentSections = document.querySelectorAll('.content-section');
    const videoSourceRadios = document.querySelectorAll('input[name="video_source"]');
    const videoUrlInput = document.getElementById('videoUrlInput');
    const videoFileInput = document.getElementById('videoFileInput');

    // Function to update card styles
    function updateCardStyles() {
        typeCards.forEach(card => {
            const radio = card.querySelector('.topic-type-radio');
            if (radio.checked) {
                card.classList.add('border-purple-400', 'bg-purple-50');
                card.classList.remove('border-gray-200');
            } else {
                card.classList.remove('border-purple-400', 'bg-purple-50');
                card.classList.add('border-gray-200');
            }
        });
    }

    // Function to show content section based on type
    function showContentSection(type) {
        contentSections.forEach(section => {
            section.classList.add('hidden');
        });

        const targetSection = document.getElementById(type + 'Content');
        if (targetSection) {
            targetSection.classList.remove('hidden');
        }
    }

    // Function to toggle video source inputs
    function toggleVideoSource() {
        const selectedSource = document.querySelector('input[name="video_source"]:checked');
        if (selectedSource) {
            if (selectedSource.value === 'url') {
                videoUrlInput.classList.remove('hidden');
                videoFileInput.classList.add('hidden');
            } else {
                videoUrlInput.classList.add('hidden');
                videoFileInput.classList.remove('hidden');
            }
        }
    }


    // Event listeners for topic type selection
    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateCardStyles();
            showContentSection(this.value);
        });
    });

    // Event listeners for video source
    videoSourceRadios.forEach(radio => {
        radio.addEventListener('change', toggleVideoSource);
    });


    // Initialize on page load
    updateCardStyles();
    
    // Show content section if type is already selected (from old input or existing topic)
    const selectedType = document.querySelector('.topic-type-radio:checked');
    if (selectedType) {
        showContentSection(selectedType.value);
    }

    // Initialize video source visibility
    toggleVideoSource();

});
</script>
</div>
@endsection

