<div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] overflow-hidden">
    <!-- Topic Header -->
    <div class="px-4 py-3" style="background: linear-gradient(to right, #A30EB2, #730DB1, #3B0CB1);">
        <div class="flex items-center gap-2.5">
            <div class="flex-shrink-0 w-8 h-8 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                @if($currentTopic->type === 'video')
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
                    </svg>
                @elseif($currentTopic->type === 'text')
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                    </svg>
                @elseif($currentTopic->type === 'worksheet')
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                    </svg>
                @else
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-white leading-snug">{{ $currentTopic->title }}</h3>
                <p class="text-white/70 text-xs">
                    @if($currentTopic->isOptionalInteraction())
                        INTERACTIVE ACTIVITY · Optional
                    @else
                        Topic {{ $currentTopicIndex + 1 }} of {{ $lessonTopics->count() }}
                        <span class="mx-1">·</span>{{ $currentTopic->duration }}m
                        <span class="mx-1">·</span>{{ ucfirst($currentTopic->type) }}
                    @endif
                </p>
                @if(($module->created_by ?? null) && ($module->created_by ?? null) !== auth()->id())
                    <button
                        type="button"
                        @click="$dispatch('open-global-chat', {
                            target_user_id: {{ $module->created_by }},
                            name: '{{ addslashes($module->creator?->name ?? 'Instructor') }}',
                            avatar: 'https://ui-avatars.com/api/?name={{ urlencode($module->creator?->name ?? 'Instructor') }}&color=1D4ED8&background=EFF6FF',
                            target_role: 'instructor',
                            conversation_type: 'lesson_topic_chat',
                            lesson_topic_id: {{ $currentTopic->id }},
                            lesson_id: {{ $lesson->id }},
                            module_id: {{ $module->id }}
                        })"
                        class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-800 hover:bg-blue-100"
                    >
                        Ask About This Topic
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Topic Content -->
    <div class="p-6 bg-white dark:bg-transparent">
        @php
            $richTextBlocks = $currentTopic->type === 'text' && is_array($currentTopic->content_blocks)
                ? collect($currentTopic->content_blocks)->where('type', 'rich_text')->pluck('html')->filter()->all()
                : [];
            $topicTextContent = $richTextBlocks !== []
                ? implode('', $richTextBlocks)
                : $currentTopic->text_content;
        @endphp
        @if($currentTopic->type === 'interactive_checkpoint')
            @if($currentTopic->checkpointQuestion)
                @include('learner.lessons.partials.interactive-checkpoint', ['question' => $currentTopic->checkpointQuestion])
            @endif
        @elseif($currentTopic->type === 'video')
            <!-- Video Content -->
            <div class="space-y-4">
                @if($currentTopic->text_content)
                    <div class="prose max-w-none">
                        {!! $currentTopic->text_content !!}
                    </div>
                @endif

                @if($currentTopic->video_file_url)
                    {{-- Plyr.js Video Player (bundled via npm) --}}
                    <div class="rounded-2xl overflow-hidden bg-black" style="aspect-ratio: 16/9;">
                        <video id="plyr-video-{{ $currentTopic->id }}"
                               class="plyr-video w-full h-full"
                               playsinline
                               title="{{ $currentTopic->title }}">
                            <source src="{{ $currentTopic->video_file_url }}" type="video/mp4">
                            @if($currentTopic->caption_file_path)
                                <track kind="subtitles"
                                       label="Subtitles"
                                       srclang="en"
                                       src="{{ asset('storage/' . $currentTopic->caption_file_path) }}"
                                       default>
                            @endif
                        </video>
                    </div>
                @elseif($currentTopic->video_embed_url)
                    <!-- Embedded Video (YouTube/Vimeo) -->
                    <div class="relative bg-black rounded-lg overflow-hidden aspect-video">
                        <iframe 
                            src="{{ $currentTopic->video_embed_url }}"
                            class="absolute inset-0 w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </div>
                @else
                    <div class="bg-gray-100 rounded-lg p-12 text-center">
                        <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-gray-500">No video available for this topic.</p>
                    </div>
                @endif

            </div>

        @elseif($currentTopic->type === 'text')
            <!-- Text Content -->
            <div class="space-y-6" x-data="{ 
                displayMode: 'slideshow',
                currentImageIndex: 0,
                images: {{ json_encode($currentTopic->image_attachments ?? []) }},
                showZoomModal: false,
                zoomedImageIndex: 0,
                openZoom(index) {
                    this.zoomedImageIndex = index;
                    this.showZoomModal = true;
                    document.body.style.overflow = 'hidden';
                },
                closeZoom() {
                    this.showZoomModal = false;
                    document.body.style.overflow = 'auto';
                },
                nextZoomImage() {
                    this.zoomedImageIndex = this.zoomedImageIndex < this.images.length - 1 ? this.zoomedImageIndex + 1 : 0;
                },
                prevZoomImage() {
                    this.zoomedImageIndex = this.zoomedImageIndex > 0 ? this.zoomedImageIndex - 1 : this.images.length - 1;
                }
            }" 
            @keydown.escape.window="closeZoom()"
            @keydown.arrow-left.window="showZoomModal && prevZoomImage()"
            @keydown.arrow-right.window="showZoomModal && nextZoomImage()">
                @if($currentTopic->image_attachments && count($currentTopic->image_attachments) > 0)
                    <div>
                        <div class="flex justify-end mb-3">
                            <div class="flex gap-0.5 p-0.5 bg-gray-100 dark:bg-gray-800 rounded-lg">
                                <button
                                    @click="displayMode = 'slideshow'"
                                    class="px-3 py-1 rounded-md text-xs font-medium transition-all duration-200"
                                    :class="displayMode === 'slideshow' ? 'text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                                    :style="displayMode === 'slideshow' ? 'background: linear-gradient(135deg, #A30EB2, #3B0CB1);' : ''">
                                    Slideshow
                                </button>
                                <button
                                    @click="displayMode = 'gallery'"
                                    class="px-3 py-1 rounded-md text-xs font-medium transition-all duration-200"
                                    :class="displayMode === 'gallery' ? 'text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
                                    :style="displayMode === 'gallery' ? 'background: linear-gradient(135deg, #A30EB2, #3B0CB1);' : ''">
                                    Gallery
                                </button>
                            </div>
                        </div>

                        <!-- Slideshow Mode -->
                        <div x-show="displayMode === 'slideshow'" class="space-y-4">
                            <div class="relative bg-gray-100 rounded-lg overflow-hidden group">
                                <template x-for="(image, index) in images" :key="index">
                                    <div x-show="currentImageIndex === index" class="aspect-video cursor-zoom-in" @click="openZoom(index)">
                                        <img 
                                            :src="`/storage/${image.path}`" 
                                            :alt="image.caption || 'Image'"
                                            class="w-full h-full object-contain bg-gray-900 transition hover:opacity-95">
                                    </div>
                                </template>

                                <!-- Navigation Arrows - Always Visible -->
                                <template x-if="images.length > 1">
                                    <div>
                                        <button
                                            @click.stop="currentImageIndex = currentImageIndex > 0 ? currentImageIndex - 1 : images.length - 1"
                                            class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full flex items-center justify-center shadow transition-all hover:scale-110 z-10 bg-white/70 dark:bg-black/50 hover:bg-white/90 dark:hover:bg-black/70 backdrop-blur-sm"
                                            title="Previous image">
                                            <svg class="w-4 h-4 text-gray-800 dark:text-gray-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                            </svg>
                                        </button>
                                        <button
                                            @click.stop="currentImageIndex = currentImageIndex < images.length - 1 ? currentImageIndex + 1 : 0"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full flex items-center justify-center shadow transition-all hover:scale-110 z-10 bg-white/70 dark:bg-black/50 hover:bg-white/90 dark:hover:bg-black/70 backdrop-blur-sm"
                                            title="Next image">
                                            <svg class="w-4 h-4 text-gray-800 dark:text-gray-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </div>
                                </template>

                                {{-- Dot indicators --}}
                                <template x-if="images.length > 1">
                                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-1.5 z-10">
                                        <template x-for="(img, idx) in images" :key="idx">
                                            <button @click.stop="currentImageIndex = idx"
                                                    class="rounded-full transition-all duration-300"
                                                    :class="currentImageIndex === idx ? 'w-5 h-2' : 'w-2 h-2 opacity-60'"
                                                    :style="currentImageIndex === idx
                                                        ? 'background: linear-gradient(135deg, #A30EB2, #3B0CB1);'
                                                        : 'background-color: white;'"
                                                    :title="`Image ${idx + 1}`">
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Image Caption (only shown if caption exists) -->
                            <template x-for="(image, index) in images" :key="index">
                                <template x-if="currentImageIndex === index && image.caption">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center px-2" x-text="image.caption"></p>
                                </template>
                            </template>
                        </div>

                        <!-- Gallery Mode -->
                        <div x-show="displayMode === 'gallery'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <template x-for="(image, index) in images" :key="index">
                                <div class="bg-gray-100 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition cursor-zoom-in" @click="openZoom(index)">
                                    <div class="aspect-video bg-gray-900 relative group">
                                        <img 
                                            :src="`/storage/${image.path}`" 
                                            :alt="image.caption || 'Image'"
                                            class="w-full h-full object-cover transition hover:opacity-95">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <template x-if="image.caption">
                                        <div class="p-3 bg-white">
                                            <p class="text-sm text-gray-700" x-text="image.caption"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Image Zoom Modal -->
                    <div x-show="showZoomModal" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-95 p-4"
                         @click.self="closeZoom()">
                        
                        <!-- Close Button -->
                        <button @click="closeZoom()" 
                                class="absolute top-4 right-4 bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-full transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>

                        <!-- Previous Button -->
                        <template x-if="images.length > 1">
                            <button @click="prevZoomImage()" 
                                    class="absolute left-4 top-1/2 -translate-y-1/2 bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-4 rounded-full transition z-10">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </template>

                        <!-- Next Button -->
                        <template x-if="images.length > 1">
                            <button @click="nextZoomImage()" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-4 rounded-full transition z-10">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </template>

                        <!-- Image Container -->
                        <div class="max-w-7xl max-h-full w-full flex flex-col items-center justify-center px-16">
                            <template x-for="(image, index) in images" :key="index">
                                <div x-show="zoomedImageIndex === index" class="w-full flex flex-col items-center gap-4">
                                    <!-- Main Image -->
                                    <img :src="`/storage/${image.path}`" 
                                         :alt="image.caption || 'Image'"
                                         class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
                                    
                                    <!-- Caption and Counter Container -->
                                    <div class="flex flex-col items-center gap-2 w-full max-w-3xl">
                                        <!-- Image Counter -->
                                        <div class="text-white text-sm font-semibold px-4 py-2 rounded-full shadow-lg" style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                            <span x-text="`Image ${index + 1} of ${images.length}`"></span>
                                        </div>
                                        
                                        <!-- Caption -->
                                        <template x-if="image.caption">
                                            <div class="bg-white bg-opacity-95 rounded-xl px-6 py-4 shadow-xl w-full">
                                                <p class="text-gray-900 text-center text-base leading-relaxed" x-text="image.caption"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif

                @if($topicTextContent)
                    <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40 p-4"
                         x-data="{
                            isPreparingAudio: false,
                            isReading: false,
                            ttsError: '',
                            audioPlayer: null,
                                     canUseVoiceTranslator: @js(app(\App\Services\EntitlementService::class)->canAccessFeature(auth()->user(), \App\Support\SubscriptionFeatureKeys::VOICE_SPEECH_TRANSLATOR)),
                            topicId: @js($currentTopic->id),
                            ttsApiUrl: @js(route('learner.translator.tts')),
                            translationLanguage: 'en',
                            translationLanguageEventName: 'cc:translation-language-changed',
                            translationStorageKey: 'cc_page_translation_language',
                            onTranslationLanguageChange: null,
                            onTranslationLanguageStorage: null,
                            initReader() {
                                this.audioPlayer = new Audio();
                                this.audioPlayer.preload = 'none';

                                this.audioPlayer.addEventListener('ended', () => {
                                    this.isReading = false;
                                });

                                this.audioPlayer.addEventListener('error', () => {
                                    this.isReading = false;
                                    this.ttsError = 'Failed to load audio source. Please try again.';
                                });

                                this.onTranslationLanguageChange = (event) => {
                                    this.syncTranslationLanguage(event?.detail?.language, true);
                                };

                                this.onTranslationLanguageStorage = (event) => {
                                    if (event.key !== this.translationStorageKey) {
                                        return;
                                    }

                                    this.syncTranslationLanguage(event.newValue, true);
                                };

                                window.addEventListener(this.translationLanguageEventName, this.onTranslationLanguageChange);
                                window.addEventListener('storage', this.onTranslationLanguageStorage);
                                this.syncTranslationLanguage(localStorage.getItem(this.translationStorageKey), false);
                            },
                            normalizeLanguage(language) {
                                if (language === 'tl') {
                                    return 'tl';
                                }

                                return 'en';
                            },
                            syncTranslationLanguage(language, stopCurrentAudio) {
                                const nextLanguage = this.normalizeLanguage(language);
                                if (this.translationLanguage === nextLanguage) {
                                    return;
                                }

                                this.translationLanguage = nextLanguage;

                                if (stopCurrentAudio && this.isReading) {
                                    this.stopReading();
                                    this.ttsError = `Reader language updated to ${this.currentVoiceLanguageLabel()}. Tap play to continue.`;
                                }
                            },
                            currentVoiceLanguageLabel() {
                                if (this.translationLanguage === 'tl') {
                                    return 'Tagalog';
                                }

                                return 'English';
                            },
                            getPreferredLanguageCode() {
                                if (this.translationLanguage === 'tl') {
                                    return 'fil-PH';
                                }

                                return 'en-US';
                            },
                            getVisibleLessonText() {
                                if (!this.$refs.lessonContent) {
                                    return '';
                                }

                                return (this.$refs.lessonContent.innerText || '').replace(/\s+/g, ' ').trim();
                            },
                            async resolvePlayableUrl(payload) {
                                const candidates = [payload.audio_relative_url, payload.audio_url]
                                    .filter((value) => typeof value === 'string' && value.trim() !== '')
                                    .map((value) => value.trim());

                                if (!candidates.length) {
                                    throw new Error('No lesson audio URL returned.');
                                }

                                for (let i = 0; i < candidates.length; i++) {
                                    const candidate = candidates[i];
                                    try {
                                        const response = await fetch(candidate, { method: 'GET', cache: 'no-store' });
                                        const contentType = (response.headers.get('content-type') || '').toLowerCase();
                                        if (response.ok && (contentType.includes('audio') || contentType.includes('octet-stream'))) {
                                            return candidate;
                                        }
                                    } catch (error) {
                                        // Try the next candidate URL.
                                    }
                                }

                                throw new Error('Generated lesson audio file is not reachable.');
                            },
                            async toggleReadLesson() {
                                if (!this.canUseVoiceTranslator) {
                                    this.ttsError = 'Upgrade to unlock translated lesson narration.';
                                    return;
                                }

                                if (this.isReading) {
                                    this.stopReading();
                                    return;
                                }

                                if (this.isPreparingAudio) {
                                    return;
                                }

                                this.ttsError = '';
                                this.isPreparingAudio = true;

                                try {
                                    const response = await fetch(this.ttsApiUrl, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                                        },
                                        body: JSON.stringify({
                                            topic_id: this.topicId,
                                            language_code: this.getPreferredLanguageCode(),
                                            translation_language: this.translationLanguage,
                                            text: this.getVisibleLessonText() || null,
                                            speaking_rate: 1.0,
                                        }),
                                    });

                                    const payload = await response.json();
                                    if (!response.ok) {
                                        throw new Error(payload.message || 'Unable to generate lesson audio.');
                                    }

                                    const playableUrl = await this.resolvePlayableUrl(payload);
                                    this.audioPlayer.src = playableUrl;
                                    this.audioPlayer.load();
                                    await this.audioPlayer.play();
                                    this.isReading = true;
                                } catch (error) {
                                    this.ttsError = error.message || 'Unable to read this lesson right now.';
                                    this.isReading = false;
                                } finally {
                                    this.isPreparingAudio = false;
                                }
                            },
                            stopReading() {
                                if (!this.audioPlayer) {
                                    return;
                                }

                                this.audioPlayer.pause();
                                this.audioPlayer.currentTime = 0;
                                this.isReading = false;
                            }
                         }"
                         x-init="initReader()">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Lesson Reader</p>
                                <button type="button"
                                        @click="toggleReadLesson()"
                                    :disabled="isPreparingAudio || !canUseVoiceTranslator"
                                        :title="isReading ? 'Stop reading lesson' : 'Read lesson text'"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-purple-300 text-purple-700 hover:bg-purple-100 dark:hover:bg-purple-900/40 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg x-show="!isReading" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.383 3.076A1 1 0 0111 3.894v12.212a1 1 0 01-1.617.79L5.7 14H3a1 1 0 01-1-1V7a1 1 0 011-1h2.7l3.683-2.684z" />
                                        <path d="M14.657 6.343a1 1 0 10-1.414 1.414A3 3 0 0114 10a3 3 0 01-.757 2.243 1 1 0 101.414 1.414A5 5 0 0016 10a5 5 0 00-1.343-3.657z" />
                                    </svg>
                                    <svg x-show="isReading" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6 6h3v8H6V6zm5 0h3v8h-3V6z" />
                                    </svg>
                                </button>
                                <span x-show="isPreparingAudio" class="text-[11px] text-gray-500">Preparing...</span>
                            </div>
                            <template x-if="canUseVoiceTranslator">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Voice: <span class="font-semibold text-brand-600" x-text="currentVoiceLanguageLabel()"></span></p>
                            </template>
                            <template x-if="!canUseVoiceTranslator">
                                <p class="text-xs text-amber-700">
                                    <a href="{{ route('subscription.index') }}" class="font-semibold underline">Upgrade</a>
                                </p>
                            </template>
                        </div>

                        <div x-show="ttsError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="ttsError"></div>

                        <div x-ref="lessonContent" class="prose dark:prose-invert max-w-none mt-4">
                            {!! $topicTextContent !!}
                        </div>
                    </div>
                @endif
            </div>

        @elseif($currentTopic->type === 'worksheet')
            <!-- Worksheet Content -->
            <div class="space-y-6">
                @if($currentTopic->text_content)
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                        <h4 class="font-semibold text-yellow-900 mb-2 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            Instructions:
                        </h4>
                        <div class="text-yellow-800 prose prose-sm max-w-none">
                            {!! $currentTopic->text_content !!}
                        </div>
                    </div>
                @endif

                @if($currentTopic->worksheet_files && count($currentTopic->worksheet_files) > 0)
                    <div class="space-y-6">
                        <div class="border-l-4 pl-3 flex items-center justify-between" style="border-color: #730DB1;">
                            <h4 class="font-semibold text-gray-800 dark:text-gray-100 text-sm tracking-wide uppercase">
                                Worksheet Files
                                <span class="ml-1.5 text-xs font-normal text-gray-400 normal-case tracking-normal">{{ count($currentTopic->worksheet_files) }} file{{ count($currentTopic->worksheet_files) > 1 ? 's' : '' }}</span>
                            </h4>
                        </div>

                        @foreach($currentTopic->worksheet_files as $index => $file)
                        @php
                            $__filePath     = $file['path'] ?? '';
                            $__fileName     = $file['original_name'] ?? basename($__filePath);
                            $__mimeType     = $file['mime_type'] ?? '';
                            $__fileSize     = isset($file['size']) ? number_format($file['size'] / 1024, 1) . ' KB' : null;
                            $__fileExt      = strtolower(pathinfo($__fileName, PATHINFO_EXTENSION));
                            $__isPdf        = str_contains($__mimeType, 'pdf') || $__fileExt === 'pdf';
                            $__isWord       = str_contains($__mimeType, 'word') || str_contains($__mimeType, 'document') || in_array($__fileExt, ['doc', 'docx']);
                            $__fileUrl      = asset('storage/' . $__filePath);
                        @endphp

                        @if($__isPdf)
                        {{-- PDF.js inline viewer --}}
                        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden"
                             x-data="pdfViewer('{{ $__fileUrl }}')"
                             x-init="init()">
                            {{-- PDF viewer header --}}
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ $__fileName }}</span>
                                    @if($__fileSize)
                                        <span class="flex-shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ $__fileSize }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    {{-- Page counter --}}
                                    <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                          x-text="totalPages ? `Page ${page} / ${totalPages}` : 'Loading…'"></span>
                                    {{-- Download --}}
                                    <a href="{{ $__fileUrl }}" download="{{ $__fileName }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white rounded-lg transition-all hover:opacity-90"
                                       style="background: linear-gradient(135deg, #A30EB2, #3B0CB1);">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        Download
                                    </a>
                                </div>
                            </div>

                            {{-- Canvas area --}}
                            <div class="bg-gray-100 dark:bg-gray-950 overflow-auto p-3 flex justify-center" style="min-height: 400px; max-height: 600px;">
                                <canvas x-ref="canvas" class="shadow-lg rounded max-w-full"></canvas>
                            </div>

                            {{-- Controls bar --}}
                            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                                {{-- Pagination --}}
                                <div class="flex items-center gap-2">
                                    <button @click="prev()"
                                            :disabled="page <= 1"
                                            class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                        </svg>
                                    </button>
                                    <button @click="next()"
                                            :disabled="page >= totalPages"
                                            class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                {{-- Zoom --}}
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="`${Math.round(scale * 100)}%`"></span>
                                    <button @click="zoomOut()"
                                            class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
                                        </svg>
                                    </button>
                                    <button @click="zoomIn()"
                                            class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        @else
                        {{-- Non-PDF download card --}}
                        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex items-center gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center"
                                 style="background: linear-gradient(135deg, rgba(163,14,178,0.1), rgba(59,12,177,0.1));">
                                @if($__isWord)
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" style="color: #4F81BD;">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $__fileName }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ strtoupper($__fileExt) }} file{{ $__fileSize ? ' · ' . $__fileSize : '' }}
                                </p>
                            </div>
                            <a href="{{ $__fileUrl }}" download="{{ $__fileName }}"
                               class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl transition-all hover:opacity-90 active:scale-[0.98]"
                               style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </a>
                        </div>
                        @endif

                        @endforeach
                    </div>
                @elseif($currentTopic->file_path)
                    {{-- Legacy single file support --}}
                    @php
                        $__legacyExt = strtolower(pathinfo($currentTopic->file_path, PATHINFO_EXTENSION));
                        $__legacyUrl = asset('storage/' . $currentTopic->file_path);
                        $__legacyName = basename($currentTopic->file_path);
                    @endphp
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center"
                             style="background: linear-gradient(135deg, rgba(163,14,178,0.1), rgba(59,12,177,0.1));">
                            <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $__legacyName }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ strtoupper($__legacyExt) }} file</p>
                        </div>
                        <a href="{{ $__legacyUrl }}" download="{{ $__legacyName }}"
                           class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl transition-all hover:opacity-90 active:scale-[0.98]"
                           style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download
                        </a>
                    </div>
                @else
                    <div class="bg-gray-100 rounded-lg p-12 text-center">
                        <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-gray-500">No worksheet file available.</p>
                    </div>
                @endif
            </div>

        @elseif($currentTopic->type === 'interactive')
            @php
                $standaloneActivity = $currentTopic->interactiveActivities->firstWhere('placement', 'between_topics');
                $standalonePresentation = $standaloneActivity ? ($interactiveActivityPresentations[$standaloneActivity->id] ?? null) : null;
            @endphp
            @if($standalonePresentation)
                @include('learner.lessons.partials.interactive-activities.shell', ['activity' => $standalonePresentation, 'continueUrl' => $standalonePresentation['continue_url'] ?? null, 'inside' => false])
            @else
                @include('learner.lessons.partials.interactive-activities.unavailable', ['activity' => ['title' => $currentTopic->title, 'message' => 'This activity is temporarily unavailable.'], 'continueUrl' => $currentTopicIndex < $lessonTopics->count() - 1 ? route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $currentTopicIndex + 1]) : null])
            @endif
        @endif

        @if($currentTopic->type !== 'interactive_checkpoint' && is_array($currentTopic->content_blocks))
            @foreach($currentTopic->content_blocks as $block)
                @if(($block['type'] ?? null) === 'checkpoint')
                    @php
                        $question = $currentTopic->checkpointQuestions->firstWhere('id', (int) ($block['question_id'] ?? 0));
                        $validBlock = $question && $question->checkpoint_block_uuid === ($block['uuid'] ?? null);
                    @endphp
                    @if($validBlock)
                        @include('learner.lessons.partials.interactive-checkpoint', ['question' => $question])
                    @endif
                @elseif(($block['type'] ?? null) === 'interactive_activity')
                    @php
                        $activity = $currentTopic->interactiveActivities->firstWhere('id', (int) ($block['activity_id'] ?? 0));
                        $presentation = $activity ? ($interactiveActivityPresentations[$activity->id] ?? null) : null;
                        $validActivityBlock = $activity
                            && $activity->placement === 'inside_topic'
                            && $activity->lesson_topic_id === $currentTopic->id
                            && $activity->block_uuid === ($block['uuid'] ?? null)
                            && $presentation
                            && ($presentation['available'] ?? false);
                    @endphp
                    @if($validActivityBlock)
                        @include('learner.lessons.partials.interactive-activities.shell', ['activity' => $presentation, 'inside' => true])
                    @endif
                @endif
            @endforeach
        @endif
    </div>

    <!-- Topic Actions -->
    <div class="border-t border-gray-200 p-6 bg-gray-50">
        <div class="flex items-center justify-between gap-4">
            <!-- Completion Status -->
            <div class="flex items-center gap-3">
                @if(in_array($currentTopic->id, $completedTopicIds))
                    <div class="flex items-center gap-2 text-green-600">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">Completed</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">In Progress</span>
                    </div>
                @endif
            </div>

            {{-- Navigation buttons moved to the persistent bottom action bar in show.blade.php --}}
            @if(! $currentTopic->isOptionalInteraction())
            <div class="hidden">
                <div>
                    @if($currentTopicIndex > 0)
                        <a href="{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $currentTopicIndex - 1]) }}" 
                           class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Previous
                        </a>
                    @endif
                </div>

                <div>
                    @if($currentTopicIndex < $lessonTopics->count() - 1)
                        {{-- Not last topic - show next topic button --}}
                        @if(in_array($currentTopic->id, $completedTopicIds))
                            <a href="{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'topic' => $currentTopicIndex + 1]) }}" 
                               class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition flex items-center gap-2">
                                Next
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @else
                            <form id="complete-and-next-form" action="{{ route('learner.topics.complete', $currentTopic) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="next_topic_index" value="{{ $currentTopicIndex + 1 }}">
                                <button type="submit" 
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Complete & Next
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                    @else
                        {{-- Last topic in lesson --}}
                        @if($lessonQuiz && count($completedTopicIds) >= $lessonTopics->count())
                            {{-- Has lesson quiz and all topics completed - show quiz button --}}
                            @if(!in_array($currentTopic->id, $completedTopicIds))
                                <form action="{{ route('learner.topics.complete', $currentTopic) }}" method="POST" class="inline" id="complete-then-quiz-form">
                                    @csrf
                                    <button type="submit" 
                                            class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition flex items-center gap-2"
                                            onclick="event.preventDefault(); 
                                                     fetch(this.form.action, { method: 'POST', body: new FormData(this.form), headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} })
                                                     .then(() => window.location.href = '{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'quiz' => 1]) }}');">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Complete & Take Quiz
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('learner.lessons.show', ['lesson' => $lesson->id, 'quiz' => 1]) }}" 
                                   class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition flex items-center gap-2">
                                    Next: Take Quiz
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </a>
                            @endif
                        @else
                            {{-- No lesson quiz - check for next lesson or module quiz --}}
                            @if(!in_array($currentTopic->id, $completedTopicIds))
                                <form action="{{ route('learner.topics.complete', $currentTopic) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Complete Topic
                                    </button>
                                </form>
                            @else
                                {{-- Check if this is last lesson in module and module has quiz --}}
                                @php
                                    $module = $lesson->module;
                                    $isLastLesson = $module->lessons->sortBy('order')->last()->id === $lesson->id;
                                    $moduleQuiz = $module->quiz;
                                @endphp
                                
                                @if($isLastLesson && $moduleQuiz)
                                    <a href="{{ route('learner.quizzes.take', $moduleQuiz->id) }}" 
                                       class="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition flex items-center gap-2">
                                        Take Module Quiz
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </a>
                                @else
                                    <a href="{{ route('learner.modules.show', $lesson->module_id) }}" 
                                       class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Back to Module
                                    </a>
                                @endif
                            @endif
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    // PDF.js viewer — Alpine component factory
    // window.pdfjsLib is set by app.js (pdfjs-dist bundled via npm)
    window.pdfViewer = function(url) {
        // Keep PDF.js objects outside Alpine reactive state to avoid
        // private-field access errors when methods are invoked.
        let pdfDoc = null;

        return {
            page: 1,
            totalPages: 0,
            scale: 1.2,
            rendering: false,

            async init() {
                const lib = window.pdfjsLib || (window.ensurePdfJsLib ? await window.ensurePdfJsLib() : null);
                if (!lib) return;
                try {
                    pdfDoc = await lib.getDocument(url).promise;
                    this.totalPages = pdfDoc.numPages;
                    this.$nextTick(() => this.render());
                } catch (e) {
                    console.error('[pdfViewer] Failed to load PDF:', e);
                }
            },

            async render() {
                if (!pdfDoc || this.rendering) return;
                this.rendering = true;
                try {
                    const pdfPage = await pdfDoc.getPage(this.page);
                    const viewport = pdfPage.getViewport({ scale: this.scale });
                    const canvas   = this.$refs.canvas;
                    canvas.width   = viewport.width;
                    canvas.height  = viewport.height;
                    await pdfPage.render({
                        canvasContext: canvas.getContext('2d'),
                        viewport,
                    }).promise;
                } finally {
                    this.rendering = false;
                }
            },

            async prev() {
                if (this.page > 1) { this.page--; await this.render(); }
            },
            async next() {
                if (this.page < this.totalPages) { this.page++; await this.render(); }
            },
            zoomIn()  { this.scale = Math.min(this.scale + 0.2, 3);   this.render(); },
            zoomOut() { this.scale = Math.max(this.scale - 0.2, 0.5); this.render(); },
        };
    };
</script>
@endpush
