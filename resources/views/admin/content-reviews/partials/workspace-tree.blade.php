<div class="bg-white shadow-sm rounded-xl border border-gray-200 p-5"
    x-data="{
        previewOpen: false,
        previewLoading: false,
        previewError: '',
        previewNodeType: null,
        previewTopic: null,
        previewQuiz: null,
        previewActivity: null,
        async previewNode(nodeType, nodeId) {
            this.previewOpen = true;
            this.previewLoading = true;
            this.previewError = '';
            this.previewNodeType = nodeType;
            this.previewTopic = null;
            this.previewQuiz = null;
            this.previewActivity = null;

            try {
                const response = await fetch('{{ route('admin.content-reviews.preview', $reviewRequest) }}?node_type=' + encodeURIComponent(nodeType) + '&node_id=' + nodeId, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error('Unable to load preview content.');
                }

                const payload = await response.json();

                if (nodeType === 'topic') {
                    this.previewTopic = payload.node;
                } else if (nodeType === 'quiz') {
                    this.previewQuiz = payload.node;
                } else {
                    this.previewActivity = payload.node;
                }
            } catch (error) {
                this.previewError = error.message || 'Unable to load preview content.';
            } finally {
                this.previewLoading = false;
            }
        },
        previewTopicContent(topicId) {
            this.previewNode('topic', topicId);
        },
        previewQuizContent(quizId) {
            this.previewNode('quiz', quizId);
        },
        previewActivityContent(activityId) {
            this.previewNode('activity', activityId);
        },
        formatLabel(value) {
            if (!value) {
                return 'N/A';
            }

            return String(value)
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\b\w/g, (char) => char.toUpperCase());
        },
        formatLimit(value) {
            if (value === null || value === undefined || value === '') {
                return 'No limit';
            }

            return value;
        },
        questionTypeLabel(value) {
            const map = {
                multiple_choice: 'Multiple Choice',
                true_false: 'True / False',
                multiple_select: 'Multiple Select',
                fill_blank_text: 'Fill Blank (Text)',
                fill_blank_select: 'Fill Blank (Word Bank)',
                identification: 'Identification',
            };

            if (!value) {
                return 'Unknown';
            }

            return map[value] || this.formatLabel(value);
        },
        questionTypeClasses(value) {
            const map = {
                multiple_choice: 'bg-brand-50 text-brand-700 border-brand-100',
                true_false: 'bg-emerald-50 text-emerald-700 border-emerald-100',
                multiple_select: 'bg-brand-50 text-brand-700 border-brand-100',
                fill_blank_text: 'bg-amber-50 text-amber-700 border-amber-100',
                fill_blank_select: 'bg-amber-50 text-amber-700 border-amber-100',
                identification: 'bg-rose-50 text-rose-700 border-rose-100',
            };

            return map[value] || 'bg-gray-100 text-gray-600 border-gray-200';
        },
        optionLabel(index) {
            return String.fromCharCode(65 + index);
        },
        quizQuestionCount() {
            return Array.isArray(this.previewQuiz?.questions) ? this.previewQuiz.questions.length : 0;
        },
        quizTotalPoints() {
            if (!Array.isArray(this.previewQuiz?.questions)) {
                return 0;
            }

            return this.previewQuiz.questions.reduce((total, question) => {
                const points = Number(question?.attributes?.points || 0);
                return total + (Number.isNaN(points) ? 0 : points);
            }, 0);
        },
        normalizeImageUrl(path) {
            if (!path) {
                return null;
            }

            const value = String(path).trim();
            if (!value) {
                return null;
            }

            if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('//')) {
                return value;
            }

            if (value.startsWith('/storage/')) {
                return value;
            }

            return '/storage/' + value.replace(/^\/+/, '');
        }
    }">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Content Structure</h2>
        <span class="text-xs text-gray-500">{{ data_get($workspace, 'hierarchy.lesson_count', 0) }} lessons · {{ data_get($workspace, 'hierarchy.quiz_count', 0) }} quizzes</span>
    </div>

    <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50/70">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Learner Module Progression</p>
                <p class="text-xs text-gray-400 mt-0.5">Mirrors learner-side lesson flow and unlock structure</p>
            </div>
            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 border border-gray-200">
                {{ data_get($workspace, 'hierarchy.lesson_count', 0) }} {{ \Illuminate\Support\Str::plural('lesson', (int) data_get($workspace, 'hierarchy.lesson_count', 0)) }}
            </span>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse(data_get($workspace, 'hierarchy.lessons', []) as $lesson)
                @php
                    $lessonId = (int) data_get($lesson, 'attributes.id', 0);
                    $topicItems = (array) data_get($lesson, 'topics', []);
                    $lessonQuizzes = (array) data_get($workspace, 'hierarchy.quizzes_by_lesson.' . $lessonId, []);
                    $topicCount = count($topicItems);
                    $quizCount = count($lessonQuizzes);
                    $requirementLabel = data_get($lesson, 'requirement_label', 'Optional Lesson');
                    $isRequired = data_get($lesson, 'requirement_type') === 'required';
                @endphp
                <div data-testid="review-tree-lesson-node" class="px-4 py-4">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex mt-0.5 h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isRequired ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $loop->iteration }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ data_get($lesson, 'attributes.title', 'Untitled Lesson') }}</p>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $isRequired ? 'bg-brand-50 text-brand-700 border border-brand-100' : 'bg-gray-100 text-gray-600 border border-gray-200' }}">
                                    {{ $requirementLabel }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ $topicCount }} topics · {{ $quizCount }} quizzes</p>

                            <div class="mt-3 space-y-0">
                                @foreach($topicItems as $topic)
                                    @php
                                        $topicId = (int) data_get($topic, 'id', 0);
                                        $typeLabel = \Illuminate\Support\Str::upper(str_replace(['_', '-'], ' ', (string) data_get($topic, 'type', 'unknown')));
                                        $isPrerequisite = (bool) data_get($topic, 'is_prerequisite', false);
                                        $showConnector = !$loop->last || $quizCount > 0;
                                    @endphp

                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center flex-shrink-0" style="width:16px;">
                                            <div class="mt-2 h-3.5 w-3.5 rounded-full border-2 {{ $isPrerequisite ? 'border-amber-400 bg-amber-50' : 'border-gray-300 bg-white' }}"></div>
                                            @if($showConnector)
                                                <div class="mt-1 min-h-[16px] flex-1 rounded-full bg-gray-200" style="width:2px;"></div>
                                            @endif
                                        </div>

                                        <div class="flex-1 {{ $showConnector ? 'pb-2.5' : 'pb-1' }}">
                                            <div class="rounded-lg border border-gray-100 bg-white px-3 py-2.5">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">{{ data_get($topic, 'title', 'Untitled Topic') }}</p>
                                                        <p class="mt-1 text-xs text-gray-500">{{ $typeLabel }} · Prerequisite: {{ $isPrerequisite ? 'Yes' : 'No' }}</p>
                                                    </div>
                                                    @if($topicId > 0)
                                                        <button type="button"
                                                            class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100"
                                                            @click="previewTopicContent({{ $topicId }})">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            Preview Topic
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            @if(count((array) data_get($topic, 'interactive_activities', [])))
                                                <div class="mt-3 rounded-lg border border-violet-100 bg-violet-50/50 px-3 py-2.5">
                                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-700">Interactive Activities</p>
                                                    <div class="mt-2 space-y-2">
                                                        @foreach((array) data_get($topic, 'interactive_activities', []) as $activity)
                                                            @php
                                                                $activityId = (int) data_get($activity, 'id', 0);
                                                                $activityType = \Illuminate\Support\Str::title(str_replace(['_', '-'], ' ', (string) data_get($activity, 'activity_type', 'activity')));
                                                            @endphp
                                                            <div data-testid="review-tree-activity-node" class="flex items-center justify-between gap-3 rounded-lg border border-violet-100 bg-white px-2.5 py-2">
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-xs font-semibold text-gray-900">{{ data_get($activity, 'title', 'Untitled Activity') }}</p>
                                                                    <p class="mt-0.5 text-[11px] text-gray-500">{{ $activityType }} · {{ data_get($activity, 'placement') === 'inside_topic' ? 'Inside topic' : 'Between topics' }} · Optional</p>
                                                                </div>
                                                                @if($activityId > 0)
                                                                    <button type="button"
                                                                        class="inline-flex flex-shrink-0 items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700 hover:bg-violet-100"
                                                                        @click="previewActivityContent({{ $activityId }})">
                                                                        Preview Activity
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @foreach($lessonQuizzes as $quiz)
                                    @php
                                        $quizId = (int) data_get($quiz, 'attributes.id', 0);
                                        $showConnector = !$loop->last;
                                    @endphp

                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center flex-shrink-0" style="width:16px;">
                                            <div class="mt-2 h-3.5 w-3.5 rounded-full border-2 border-brand-300 bg-brand-50"></div>
                                            @if($showConnector)
                                                <div class="mt-1 min-h-[16px] flex-1 rounded-full bg-gray-200" style="width:2px;"></div>
                                            @endif
                                        </div>

                                        <div class="flex-1 {{ $showConnector ? 'pb-2.5' : 'pb-1' }}">
                                            <div class="rounded-lg border border-brand-100 bg-brand-50/40 px-3 py-2.5">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-semibold text-gray-900 truncate">Lesson Quiz: {{ data_get($quiz, 'attributes.title', 'Untitled Quiz') }}</p>
                                                        <p class="mt-1 text-xs text-gray-500">Passing score: {{ data_get($quiz, 'attributes.passing_score', '-') }}% · Attempt limit: {{ data_get($quiz, 'attributes.attempt_limit', 'No limit') }}</p>
                                                    </div>
                                                    @if($quizId > 0)
                                                        <button type="button"
                                                            class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-white px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100"
                                                            @click="previewQuizContent({{ $quizId }})">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            Preview Quiz
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if($topicCount === 0 && $quizCount === 0)
                                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">
                                        No topics or quizzes were included for this lesson in the submitted revision.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-gray-500">No lesson progression available for this submission.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 space-y-3">
        @foreach((array) data_get($workspace, 'hierarchy.final_quizzes', []) as $quiz)
            @php
                $quizId = (int) data_get($quiz, 'attributes.id', 0);
            @endphp
            <div class="rounded-lg border border-brand-100 bg-brand-50/50 px-3 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Final Quiz: {{ data_get($quiz, 'attributes.title', 'Untitled Quiz') }}</p>
                        <p class="text-xs text-gray-500 mt-1">Passing score: {{ data_get($quiz, 'attributes.passing_score', '-') }}% · Time limit: {{ data_get($quiz, 'attributes.time_limit', 'No limit') }} min</p>
                    </div>
                    @if($quizId > 0)
                        <button type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-white px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100"
                            @click="previewQuizContent({{ $quizId }})">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Preview Quiz
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div x-show="previewOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 px-4"
         @click.self="previewOpen = false">
        <div class="w-full max-w-5xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900" x-text="previewNodeType === 'quiz' ? 'Quiz Preview' : (previewNodeType === 'activity' ? 'Interactive Activity Preview' : 'Lesson Topic Preview')"></h3>
                <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="previewOpen = false">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                <template x-if="previewLoading">
                    <p class="text-sm text-gray-500">Loading preview...</p>
                </template>

                <template x-if="previewError && !previewLoading">
                    <p class="text-sm text-rose-600" x-text="previewError"></p>
                </template>

                <template x-if="previewTopic && !previewLoading">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-900" x-text="previewTopic.title"></p>
                            <p class="mt-1 text-xs text-gray-500" x-text="previewTopic.topic_type_label"></p>
                        </div>

                        <div class="prose max-w-none" x-html="previewTopic.text_content"></div>

                        <template x-if="previewTopic.video_url">
                            <div class="overflow-hidden rounded-xl border border-gray-200">
                                <iframe class="h-72 w-full" :src="previewTopic.video_url" allowfullscreen></iframe>
                            </div>
                        </template>

                        <template x-if="previewTopic.video_file_url">
                            <video controls class="w-full rounded-xl border border-gray-200" :src="previewTopic.video_file_url"></video>
                        </template>

                        <template x-if="previewTopic.file_url">
                            <a class="inline-flex items-center rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-700" :href="previewTopic.file_url" target="_blank" rel="noopener">Open Attachment</a>
                        </template>

                        <template x-if="Array.isArray(previewTopic.image_attachment_urls) && previewTopic.image_attachment_urls.length">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <template x-for="(imageUrl, imageIndex) in previewTopic.image_attachment_urls" :key="'image-' + imageIndex">
                                    <img :src="imageUrl" alt="Topic attachment" class="h-44 w-full rounded-xl border border-gray-200 object-cover">
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="previewActivity && !previewLoading">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Interactive Activity · Optional</p>
                            <p class="mt-1 text-lg font-bold text-gray-900" x-text="previewActivity.title || 'Untitled Activity'"></p>
                            <p class="mt-1 text-xs text-gray-500" x-text="previewActivity.activity_type_label"></p>
                            <div class="prose prose-sm mt-3 max-w-none" x-html="previewActivity.instructions"></div>
                        </div>

                        <template x-if="previewActivity.activity_type === 'matching'">
                            <div class="rounded-xl border border-gray-100 bg-white">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Matching relationships</h4>
                                    <p class="mt-1 text-xs text-gray-500">Submitted answer relationships shown for review.</p>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    <template x-for="(pair, pairIndex) in (previewActivity.configuration?.pairs || [])" :key="'review-pair-' + pairIndex">
                                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 py-3 text-sm">
                                            <span class="font-medium text-gray-800" x-text="pair?.left?.value || 'Left item'"></span>
                                            <span class="text-gray-400">↔</span>
                                            <span class="font-medium text-gray-800" x-text="pair?.right?.value || 'Right item'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="previewActivity.activity_type === 'sequencing'">
                            <div class="rounded-xl border border-gray-100 bg-white">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Canonical sequence</h4>
                                    <p class="mt-1 text-xs text-gray-500">Submitted answer order shown for review.</p>
                                </div>
                                <ol class="space-y-2 px-4 py-4">
                                    <template x-for="(item, itemIndex) in (previewActivity.configuration?.items || [])" :key="'review-item-' + itemIndex">
                                        <li class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700" x-text="itemIndex + 1"></span>
                                            <span class="font-medium text-gray-800" x-text="item?.value || 'Sequence item'"></span>
                                        </li>
                                    </template>
                                </ol>
                            </div>
                        </template>

                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Explanation</p>
                            <div class="prose prose-sm mt-2 max-w-none" x-html="previewActivity.explanation"></div>
                        </div>
                    </div>
                </template>

                <template x-if="previewQuiz && !previewLoading">
                    <div class="space-y-5">
                        <div class="rounded-2xl border border-gray-100 bg-gray-50 px-5 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Quiz Information</p>
                            <p class="mt-1 text-lg font-bold text-gray-900" x-text="previewQuiz.title || 'Untitled Quiz'"></p>
                            <p class="mt-1 text-sm text-gray-600" x-text="previewQuiz.description || 'No description provided.'"></p>
                        </div>

                        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                            <div class="rounded-xl border border-brand-100 bg-brand-50/40 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-widest text-gray-400">Questions</dt>
                                <dd class="text-2xl font-bold text-gray-900" x-text="quizQuestionCount()"></dd>
                            </div>
                            <div class="rounded-xl border border-brand-100 bg-brand-50/40 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-widest text-gray-400">Total Points</dt>
                                <dd class="text-2xl font-bold text-gray-900" x-text="quizTotalPoints()"></dd>
                            </div>
                            <div class="rounded-xl border border-brand-100 bg-brand-50/40 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-widest text-gray-400">Attempt Limit</dt>
                                <dd class="text-2xl font-bold text-gray-900" x-text="formatLimit(previewQuiz.attempt_limit)"></dd>
                            </div>
                            <div class="rounded-xl border border-brand-100 bg-brand-50/40 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-widest text-gray-400">Quiz Timer</dt>
                                <dd class="text-2xl font-bold text-gray-900" x-text="formatLimit(previewQuiz.time_limit)"></dd>
                            </div>
                            <div class="rounded-xl border border-brand-100 bg-brand-50/40 px-3 py-2.5">
                                <dt class="text-xs font-semibold uppercase tracking-widest text-gray-400">Passing Score</dt>
                                <dd class="text-2xl font-bold text-gray-900" x-text="(previewQuiz.passing_score ?? '-') + '%'">-</dd>
                            </div>
                        </dl>

                        <div class="rounded-2xl border border-gray-100 bg-white">
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                                <div class="border-l-4 pl-3" style="border-color: #730DB1;">
                                    <h4 class="text-sm font-bold text-gray-900">Questions</h4>
                                    <p class="text-xs text-gray-400 mt-0.5" x-text="quizQuestionCount() + ' questions total'"></p>
                                </div>
                            </div>

                            <div class="divide-y divide-gray-50" x-show="Array.isArray(previewQuiz.questions) && previewQuiz.questions.length">
                            <template x-for="(question, questionIndex) in previewQuiz.questions" :key="'quiz-question-' + questionIndex">
                                <div class="px-5 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-gray-100">
                                            <span class="text-xs font-bold text-gray-500" x-text="questionIndex + 1"></span>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900" x-text="question?.attributes?.question_text || 'Untitled question'"></p>

                                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex rounded-lg border px-2 py-0.5 text-xs font-medium"
                                                    :class="questionTypeClasses(question?.attributes?.question_type)"
                                                    x-text="questionTypeLabel(question?.attributes?.question_type)"></span>
                                                <span class="inline-flex rounded-lg border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-600"
                                                    x-text="String(question?.attributes?.points || 0) + ' ' + ((Number(question?.attributes?.points || 0) === 1) ? 'pt' : 'pts')"></span>
                                                <span x-show="Boolean(question?.attributes?.case_sensitive)"
                                                    class="inline-flex rounded-lg border border-rose-100 bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-600">
                                                    Case Sensitive
                                                </span>
                                            </div>

                                            <template x-if="Array.isArray(question?.options) && question.options.length">
                                                <div class="mt-3 space-y-1">
                                                    <template x-for="(option, optionIndex) in question.options" :key="'quiz-option-' + questionIndex + '-' + optionIndex">
                                                        <div class="flex items-center gap-2 text-xs">
                                                            <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full"
                                                                :class="Boolean(option?.is_correct) ? 'bg-emerald-100' : 'bg-gray-100'">
                                                                <template x-if="Boolean(option?.is_correct)">
                                                                    <svg class="h-2.5 w-2.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                </template>
                                                                <template x-if="!Boolean(option?.is_correct)">
                                                                    <span class="h-1 w-1 rounded-full bg-gray-400"></span>
                                                                </template>
                                                            </span>

                                                            <span :class="Boolean(option?.is_correct) ? 'font-semibold text-emerald-700' : 'text-gray-600'"
                                                                x-text="optionLabel(optionIndex) + '. ' + (option?.option_text || 'Option')"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            <p x-show="Boolean(question?.attributes?.acceptable_answers)" class="mt-2 text-xs text-gray-500">
                                                <span class="font-medium text-gray-600">Acceptable answers: </span>
                                                <span x-text="String(question?.attributes?.acceptable_answers || '').replaceAll('|', ' / ')"></span>
                                            </p>

                                            <p x-show="Boolean(question?.attributes?.word_bank)" class="mt-2 text-xs text-gray-500">
                                                <span class="font-medium text-gray-600">Word bank: </span>
                                                <span x-text="Array.isArray(question?.attributes?.word_bank) ? question.attributes.word_bank.join(', ') : String(question?.attributes?.word_bank || '')"></span>
                                            </p>

                                            <img x-show="normalizeImageUrl(question?.attributes?.image_path)"
                                                :src="normalizeImageUrl(question?.attributes?.image_path)"
                                                alt="Question image"
                                                class="mt-2 h-16 w-auto rounded-xl border border-gray-100 object-cover">
                                        </div>
                                    </div>
                                </div>
                            </template>
                            </div>

                            <div x-show="!Array.isArray(previewQuiz.questions) || !previewQuiz.questions.length" class="px-5 py-8 text-center">
                                <p class="text-sm font-semibold text-gray-700">No questions available</p>
                                <p class="text-xs text-gray-400 mt-1">This quiz has no question data in the submitted revision.</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
