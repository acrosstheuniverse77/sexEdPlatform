@php
    $topics = $topics ?? \App\Http\Requests\Community\StoreCommunityPostRequest::TOPICS;
    $postTypes = $postTypes ?? \App\Enums\CommunityPostType::cases();
    $storedTopic = old('topic_choice', in_array($post?->topic, $topics ?? [], true) ? $post?->topic : ($post?->topic ? 'Other' : ''));
    $customTopic = old('custom_topic', $storedTopic === 'Other' ? $post?->topic : '');
    $existingMedia = $post
        ? ($post->relationLoaded('activeMedia') ? $post->activeMedia : $post->activeMedia()->get())
        : collect();
    $removedMediaIds = collect(old('remove_media_ids', []))
        ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-bold text-gray-800" for="post_type">Post type</label>
        <select id="post_type" name="post_type" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" required>
            <option value="">Choose a post type</option>
            @foreach($postTypes as $type)
                <option value="{{ $type->value }}" @selected(old('post_type', $post?->post_type?->value ?? $post?->post_type) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @error('post_type')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
    </div>

    <div x-data="{ topic: @js($storedTopic) }">
        <label class="block text-sm font-bold text-gray-800" for="topic_choice">Category/topic</label>
        <select id="topic_choice" name="topic_choice" x-model="topic" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" required>
            <option value="">Choose a topic</option>
            @foreach($topics as $topic)
                <option value="{{ $topic }}">{{ $topic }}</option>
            @endforeach
        </select>
        @error('topic_choice')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror

        <div class="mt-3" x-show="topic === 'Other'" x-cloak>
            <label class="block text-sm font-bold text-gray-800" for="custom_topic">Custom topic</label>
            <input id="custom_topic" name="custom_topic" value="{{ $customTopic }}" maxlength="100" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" :required="topic === 'Other'">
            @error('custom_topic')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div>
    <label class="block text-sm font-bold text-gray-800" for="body">Content</label>
    <textarea id="body" name="body" rows="8" class="mt-1 w-full rounded-lg border-gray-300 text-sm leading-6 focus:border-brand-500 focus:ring-brand-500" required>{{ old('body', $post?->body) }}</textarea>
    @error('body')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
</div>

<div
    data-testid="community-media-picker"
    x-data="communityMediaPicker(@js($existingMedia->map(fn ($item) => ['id' => $item->id, 'type' => $item->media_type])->values()), @js($removedMediaIds))"
    class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-bold text-gray-900">Images or video <span class="font-medium text-gray-500">(optional)</span></p>
            <p class="mt-1 text-xs font-medium leading-5 text-gray-500">Up to 6 images (JPG, PNG, or WebP), 5 MB each — or one MP4, WebM, or MOV video up to 25 MB.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="$refs.images.click()" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-brand-200 bg-white px-3 text-sm font-bold text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                Add images
            </button>
            <button type="button" @click="$refs.video.click()" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 text-sm font-bold text-gray-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15.75 10.5 4.72-2.36A.75.75 0 0 1 21.5 8.8v6.4a.75.75 0 0 1-1.03.7l-4.72-2.36M4.75 6.5h8.5a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-8.5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z"/></svg>
                Add video
            </button>
        </div>
    </div>

    <input x-ref="images" id="community_images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="chooseImages($event)">
    <input x-ref="video" id="community_video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime" class="sr-only" @change="chooseVideo($event)">

    @if($existingMedia->isNotEmpty())
        <div class="mt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Existing media</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($existingMedia as $item)
                    <div data-existing-media-id="{{ $item->id }}" class="relative overflow-hidden rounded-xl border border-gray-200 bg-white" :class="isExistingRemoved({{ $item->id }}) ? 'opacity-50' : ''">
                        @if($item->media_type === 'image')
                            <img src="{{ route('connector.community.media.show', [$connector, $post, $item]) }}" alt="Existing image {{ $loop->iteration }}" class="aspect-video w-full object-cover">
                        @else
                            <video controls preload="metadata" class="aspect-video w-full bg-black object-contain">
                                <source src="{{ route('connector.community.media.show', [$connector, $post, $item]) }}" type="{{ $item->mime_type }}">
                            </video>
                        @endif
                        <div class="flex items-center justify-between gap-2 px-3 py-2">
                            <p class="min-w-0 truncate text-xs font-semibold text-gray-600">
                                {{ $item->original_name ?: str($item->media_type)->headline() }}
                                @if($item->size_bytes)
                                    · {{ number_format($item->size_bytes / 1024 / 1024, 1) }} MB
                                @endif
                            </p>
                            <button type="button" @click="toggleExisting({{ $item->id }}, '{{ $item->media_type }}')" class="min-h-11 shrink-0 rounded-lg px-2 text-xs font-bold text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500" x-text="isExistingRemoved({{ $item->id }}) ? 'Undo' : 'Remove'"></button>
                        </div>
                        <input type="checkbox" class="sr-only" name="remove_media_ids[]" value="{{ $item->id }}" x-model.number="removedExisting" @checked($removedMediaIds->contains($item->id))>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div x-show="selectedImages.length" x-cloak class="mt-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">New images</p>
            <span class="rounded-full bg-brand-100 px-2 py-1 text-xs font-bold text-brand-700" x-text="`${activeExistingCount('image') + selectedImages.length}/6`"></span>
        </div>
        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="item in selectedImages" :key="item.key">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <img :src="item.url" alt="Selected image preview" class="aspect-video w-full object-cover">
                    <div class="flex items-center justify-between gap-2 px-3 py-2">
                        <p class="min-w-0 truncate text-xs font-semibold text-gray-600" x-text="`${item.file.name} · ${formatBytes(item.file.size)}`"></p>
                        <button type="button" @click="removeImage(item.key)" class="min-h-11 shrink-0 rounded-lg px-2 text-xs font-bold text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500">Remove</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="selectedVideo" x-cloak class="mt-4 max-w-xl overflow-hidden rounded-xl border border-gray-200 bg-white">
        <video x-ref="videoPreview" :src="selectedVideo?.url" controls preload="metadata" class="aspect-video w-full bg-black object-contain"></video>
        <div class="flex items-center justify-between gap-2 px-3 py-2">
            <p class="min-w-0 truncate text-xs font-semibold text-gray-600" x-text="selectedVideo ? `${selectedVideo.file.name} · ${formatBytes(selectedVideo.file.size)}` : ''"></p>
            <button type="button" @click="clearVideo()" class="min-h-11 shrink-0 rounded-lg px-2 text-xs font-bold text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500">Remove</button>
        </div>
    </div>

    <p x-show="notice" x-cloak class="mt-3 text-sm font-semibold text-amber-700" role="status" x-text="notice"></p>
    @if($errors->any())
        <p class="mt-3 text-xs font-semibold text-amber-700">For your security, uploaded files are not retained after validation. If you selected new files, choose them again before saving.</p>
    @endif
    @error('images')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('images.*')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('video')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('media')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
    @error('remove_media_ids')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('communityMediaPicker', (existing = [], removed = []) => ({
                existing,
                removedExisting: removed.filter((id) => existing.some((item) => item.id === id)),
                selectedImages: [],
                selectedVideo: null,
                notice: '',

                activeExistingCount(type) {
                    return this.existing.filter((item) => item.type === type && !this.removedExisting.includes(item.id)).length;
                },

                isExistingRemoved(id) {
                    return this.removedExisting.includes(id);
                },

                toggleExisting(id, type) {
                    if (this.isExistingRemoved(id)) {
                        if (type === 'image' && this.selectedVideo) this.clearVideo();
                        if (type === 'video' && this.selectedImages.length) this.clearImages();
                        this.removedExisting = this.removedExisting.filter((value) => value !== id);
                    } else {
                        this.removedExisting.push(id);
                    }
                },

                chooseImages(event) {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) return;

                    const available = Math.max(0, 6 - this.activeExistingCount('image') - this.selectedImages.length);
                    const validFiles = files.filter((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 5 * 1024 * 1024);
                    const filesToAdd = validFiles.slice(0, available);

                    if (!filesToAdd.length) {
                        this.notice = 'No images were added. Use JPG, PNG, or WebP files up to 5 MB and keep the gallery to six.';
                        this.syncImages();
                        return;
                    }

                    this.clearVideo();
                    this.existing.filter((item) => item.type === 'video').forEach((item) => {
                        if (!this.removedExisting.includes(item.id)) this.removedExisting.push(item.id);
                    });

                    filesToAdd.forEach((file) => {
                        this.selectedImages.push({
                            key: `${Date.now()}-${Math.random()}`,
                            file,
                            url: URL.createObjectURL(file),
                        });
                    });
                    this.notice = filesToAdd.length < files.length ? 'Some images were skipped. Use JPG, PNG, or WebP files up to 5 MB and keep the gallery to six.' : '';
                    this.syncImages();
                },

                removeImage(key) {
                    const item = this.selectedImages.find((image) => image.key === key);
                    if (item) URL.revokeObjectURL(item.url);
                    this.selectedImages = this.selectedImages.filter((image) => image.key !== key);
                    this.syncImages();
                },

                clearImages() {
                    this.selectedImages.forEach((item) => URL.revokeObjectURL(item.url));
                    this.selectedImages = [];
                    this.syncImages();
                },

                syncImages() {
                    const transfer = new DataTransfer();
                    this.selectedImages.forEach((item) => transfer.items.add(item.file));
                    this.$refs.images.files = transfer.files;
                },

                chooseVideo(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    if (!['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type) || file.size > 25 * 1024 * 1024) {
                        this.clearVideo();
                        this.notice = 'Use one MP4, WebM, or MOV video up to 25 MB.';
                        return;
                    }

                    this.clearImages();
                    this.existing.filter((item) => item.type === 'image').forEach((item) => {
                        if (!this.removedExisting.includes(item.id)) this.removedExisting.push(item.id);
                    });
                    this.clearVideo(false);
                    this.selectedVideo = { file, url: URL.createObjectURL(file) };
                    this.notice = '';
                },

                clearVideo(resetInput = true) {
                    if (this.selectedVideo) URL.revokeObjectURL(this.selectedVideo.url);
                    this.selectedVideo = null;
                    if (resetInput && this.$refs.video) this.$refs.video.value = '';
                },

                formatBytes(bytes) {
                    if (!Number.isFinite(bytes) || bytes <= 0) return '0 MB';
                    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
                },
            }));
        });
    </script>
@endonce
