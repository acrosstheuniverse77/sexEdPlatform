<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StartConversationRequest;
use App\Events\Chat\MessageRequestCreated;
use App\Enums\EnrollmentStatus;
use App\Models\Conversation;
use App\Models\MessageRequest;
use App\Models\ModuleEnrollment;
use App\Models\User;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatService;
use App\Services\Chat\SupportAdminResolver;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConversationController extends Controller
{
    public function __construct(
        protected ChatService $chatService,
        protected ChatAuthorizationService $chatAuthorizationService,
        protected SupportAdminResolver $supportAdminResolver,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $userId = (int) $user->id;
        $isAdminContext = $this->isAdminContext($user);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        $conversationPage = Conversation::query()
            ->where(function ($query) use ($userId, $isAdminContext) {
                $query->where('participant_one_id', $userId)
                    ->orWhere('participant_two_id', $userId);

                if ($isAdminContext) {
                    $query->orWhere('conversation_type', Conversation::TYPE_ADMIN_SUPPORT);
                }
            })
            ->with([
                'participantOne:id,name,role,status,chat_status',
                'participantOne.learnerProfile:id,user_id,avatar_path',
                'participantOne.instructorProfile:id,user_id,profile_photo_path',
                'participantOne.adminCreatorProfile:id,user_id,affiliation',
                'participantTwo:id,name,role,status,chat_status',
                'participantTwo.learnerProfile:id,user_id,avatar_path',
                'participantTwo.instructorProfile:id,user_id,profile_photo_path',
                'participantTwo.adminCreatorProfile:id,user_id,affiliation',
                'module:id,title',
                'lesson:id,title',
                'lessonTopic:id,title,lesson_id',
                'quiz:id,title',
                'latestMessage' => function ($query) {
                    $query->select([
                        'messages.id',
                        'messages.conversation_id',
                        'messages.sender_id',
                        'messages.message_body',
                        'messages.message_type',
                        'messages.created_at',
                    ]);
                },
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $conversationIds = $conversationPage->getCollection()->pluck('id');

        $pendingRequestsByConversation = MessageRequest::query()
            ->where('status', MessageRequest::STATUS_PENDING)
            ->whereIn('accepted_conversation_id', $conversationIds)
            ->with(['requester:id,name,role', 'instructor:id,name,role'])
            ->get()
            ->keyBy('accepted_conversation_id');

        $conversations = $conversationPage
            ->getCollection()
            ->map(function (Conversation $conversation) use ($user, $userId, $pendingRequestsByConversation) {
                $otherParticipant = $this->resolveOtherParticipantForViewer($conversation, $user);

                $pendingRequest = $pendingRequestsByConversation->get((int) $conversation->id);

                $pendingRequestPayload = null;
                if ($pendingRequest !== null) {
                    $pendingRequestPayload = [
                        'id' => $pendingRequest->id,
                        'status' => $pendingRequest->status,
                        'initial_message' => $pendingRequest->initial_message,
                        'requester_id' => $pendingRequest->requester_id,
                        'instructor_id' => $pendingRequest->instructor_id,
                        'requester' => $pendingRequest->requester,
                        'instructor' => $pendingRequest->instructor,
                    ];
                }

                return [
                    'id' => $conversation->id,
                    'participant_one_id' => $conversation->participant_one_id,
                    'participant_two_id' => $conversation->participant_two_id,
                    'conversation_type' => $conversation->conversation_type,
                    'status' => $conversation->status,
                    'context_key' => $conversation->context_key,
                    'context_label' => $this->buildContextLabel($conversation),
                    'last_message_at' => $conversation->last_message_at,
                    'latest_message_preview' => $this->latestMessagePreview($conversation->latestMessage?->message_body, $conversation->latestMessage?->message_type),
                    'participant_one' => $this->buildUserSnapshot($conversation->participantOne),
                    'participant_two' => $this->buildUserSnapshot($conversation->participantTwo),
                    'other_participant' => $this->buildUserSnapshot($otherParticipant),
                    'pending_request' => $pendingRequestPayload,
                    'can_send' => $this->chatAuthorizationService->canSendMessage($user, $conversation),
                    'unread_count' => $this->chatService->unreadCountForConversation($user, $conversation),
                    'support_availability' => $conversation->conversation_type === Conversation::TYPE_ADMIN_SUPPORT
                        ? $this->buildSupportAvailability($this->resolveSupportParticipant($conversation))
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'conversations' => $conversations,
            'pagination' => [
                'current_page' => $conversationPage->currentPage(),
                'last_page' => $conversationPage->lastPage(),
                'per_page' => $conversationPage->perPage(),
                'total' => $conversationPage->total(),
                'has_more' => $conversationPage->hasMorePages(),
                'next_page' => $conversationPage->hasMorePages() ? $conversationPage->currentPage() + 1 : null,
            ],
        ]);
    }

    public function start(StartConversationRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $conversationType = (string) $request->validated('conversation_type');

        $target = User::query()->findOrFail((int) $request->validated('target_user_id'));

        if ($conversationType === Conversation::TYPE_ADMIN_SUPPORT && !$this->isAdminContext($actor)) {
            $resolvedSupportAdmin = $this->supportAdminResolver->resolve((int) $actor->id);

            if ($resolvedSupportAdmin === null) {
                return response()->json([
                    'message' => 'Platform support is currently unavailable. Please try again later.',
                ], 409);
            }

            $target = $resolvedSupportAdmin;
        }

        if ($conversationType === Conversation::TYPE_DIRECT) {
            $directConversation = Conversation::query()
                ->where('pair_key', Conversation::makePairKey((int) $actor->id, (int) $target->id))
                ->where('context_key', Conversation::makeContextKey(Conversation::TYPE_DIRECT, null))
                ->first();

            if ($directConversation !== null) {
                if ((string) $directConversation->status === Conversation::STATUS_PENDING_REQUEST) {
                    $actorIsLearnerContext = $this->isLearnerContext($actor);

                    $requesterId = $actorIsLearnerContext ? (int) $actor->id : (int) $target->id;
                    $instructorId = $actorIsLearnerContext ? (int) $target->id : (int) $actor->id;

                    $pendingRequest = MessageRequest::query()
                        ->where('status', MessageRequest::STATUS_PENDING)
                        ->where(function ($query) use ($directConversation) {
                            $query->where('accepted_conversation_id', $directConversation->id)
                                ->orWhereNull('accepted_conversation_id');
                        })
                        ->where(function ($query) use ($requesterId, $instructorId) {
                            $query->where('requester_id', $requesterId)
                                ->where('instructor_id', $instructorId);
                        })
                        ->latest('id')
                        ->first();

                    return response()->json([
                        'requires_request' => true,
                        'conversation' => $directConversation,
                        'message_request' => $pendingRequest,
                    ], 202);
                }

                if ((string) $directConversation->status === Conversation::STATUS_DECLINED) {
                    return response()->json([
                        'message' => 'This instructor declined the conversation request.',
                    ], 409);
                }

                return response()->json([
                    'requires_request' => false,
                    'conversation' => $directConversation,
                ], 201);
            }
        }

        try {
            $conversation = $this->chatService->createOrGetConversation(
                initiator: $actor,
                target: $target,
                conversationType: $conversationType,
                moduleId: $request->validated('module_id'),
                lessonId: $request->validated('lesson_id'),
                quizId: $request->validated('quiz_id'),
                lessonTopicId: $request->validated('lesson_topic_id'),
            );

            return response()->json([
                'requires_request' => false,
                'conversation' => $conversation,
            ], 201);
        } catch (DomainException $exception) {
            if ($exception->getMessage() !== 'Message request required before direct conversation creation.') {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 409);
            }

            if (trim((string) $request->validated('initial_message', '')) === '') {
                return response()->json([
                    'requires_initial_message' => true,
                    'target_user_id' => (int) $request->validated('target_user_id'),
                    'conversation_type' => $conversationType,
                ], 428);
            }

            $requestResult = $this->chatService->createOrGetPendingRequestConversation(
                requester: $actor,
                instructor: $target,
                initialMessage: (string) ($request->validated('initial_message') ?? ''),
            );

            if ($requestResult['created'] === true) {
                event(new MessageRequestCreated($requestResult['messageRequest']));
            }

            return response()->json([
                'requires_request' => true,
                'conversation' => $requestResult['conversation'],
                'message_request' => $requestResult['messageRequest'],
            ], 202);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $readState = $this->chatService->markConversationRead($user, $conversation);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'read_state' => $readState,
        ]);
    }

    public function discover(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->id;
        $search = trim((string) $request->query('q', ''));

        $supportAdmin = $this->supportAdminResolver->resolve($userId);

        $isAdminContext = $this->isAdminContext($user);
        $isInstructorContext = $this->isInstructorContext($user);

        $payload = [
            'role' => $isAdminContext ? 'admin' : ($isInstructorContext ? 'instructor' : 'learner'),
            'support_admin' => $this->buildUserSnapshot($supportAdmin),
            'support_availability' => $this->buildSupportAvailability($supportAdmin),
            'contacts' => [],
        ];

        if ($isAdminContext) {
            $payload['contacts'] = [
                'learners' => $this->queryUsersByRole('learner', $search)
                    ->limit(30)
                    ->get(['id', 'name', 'role', 'status', 'chat_status'])
                    ->map(fn (User $contact) => [
                        ...($this->buildUserSnapshot($contact) ?? []),
                        'subtitle' => 'Learner',
                    ])
                    ->values(),
                'instructors' => $this->queryUsersByRole('instructor', $search)
                    ->limit(30)
                    ->get(['id', 'name', 'role', 'status', 'chat_status'])
                    ->map(fn (User $contact) => [
                        ...($this->buildUserSnapshot($contact) ?? []),
                        'subtitle' => 'Instructor',
                    ])
                    ->values(),
            ];

            return response()->json($payload);
        }

        if ($isInstructorContext) {
            $learners = User::query()
                ->where('role', 'learner')
                ->whereHas('moduleEnrollments', function ($query) use ($userId) {
                    $query->where('status', EnrollmentStatus::Approved)
                        ->whereHas('module', function ($moduleQuery) use ($userId) {
                            $moduleQuery->where('created_by', $userId);
                        });
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->with([
                    'learnerProfile:id,user_id,avatar_path',
                    'instructorProfile:id,user_id,profile_photo_path',
                ])
                ->with(['moduleEnrollments' => function ($query) use ($userId) {
                    $query->where('status', EnrollmentStatus::Approved)
                        ->whereHas('module', function ($moduleQuery) use ($userId) {
                            $moduleQuery->where('created_by', $userId);
                        })
                        ->with('module:id,title');
                }])
                ->limit(50)
                ->get(['id', 'name', 'role', 'status', 'chat_status'])
                ->map(function (User $contact) {
                    $moduleTitle = $contact->moduleEnrollments->first()?->module?->title;

                    return [
                        ...($this->buildUserSnapshot($contact) ?? []),
                        'subtitle' => $moduleTitle ? "Enrolled in {$moduleTitle}" : 'Enrolled learner',
                    ];
                })
                ->values();

            $payload['contacts'] = [
                'learners' => $learners,
            ];

            return response()->json($payload);
        }

        $instructorIdsWithEnrollment = ModuleEnrollment::query()
            ->where('user_id', $userId)
            ->where('status', EnrollmentStatus::Approved)
            ->join('modules', 'module_enrollments.module_id', '=', 'modules.id')
            ->pluck('modules.created_by')
            ->unique();

        $instructors = User::query()
            ->where('role', 'instructor')
            ->whereHas('authoredModules', function ($query) {
                $query->where('is_published', true);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with([
                'learnerProfile:id,user_id,avatar_path',
                'instructorProfile:id,user_id,profile_photo_path',
            ])
            ->limit(50)
            ->get(['id', 'name', 'role', 'status', 'chat_status'])
            ->map(function (User $contact) use ($instructorIdsWithEnrollment) {
                $enrolled = $instructorIdsWithEnrollment->contains((int) $contact->id);

                return [
                    ...($this->buildUserSnapshot($contact) ?? []),
                    'subtitle' => $enrolled ? 'Direct messaging enabled' : 'Message request may be required',
                    'is_enrolled_relation' => $enrolled,
                ];
            })
            ->values();

        $payload['contacts'] = [
            'instructors' => $instructors,
        ];

        return response()->json($payload);
    }

    protected function queryUsersByRole(string $role, string $search)
    {
        return User::query()
            ->where('role', $role)
            ->with([
                'learnerProfile:id,user_id,avatar_path',
                'instructorProfile:id,user_id,profile_photo_path',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');
    }

    private function isAdminContext(User $user): bool
    {
        return $user->can('access admin panel')
            || $user->can('manage users')
            || $user->can('moderate chat')
            || $user->hasRole('admin')
            || $user->role === 'admin';
    }

    private function isInstructorContext(User $user): bool
    {
        return !$this->isAdminContext($user)
            && ($user->can('access instructor panel') || $user->can('view learners'));
    }

    private function isLearnerContext(User $user): bool
    {
        return !$this->isAdminContext($user)
            && !$this->isInstructorContext($user)
            && ($user->can('access learner platform') || $user->can('take quizzes'));
    }

    protected function resolveOtherParticipantForViewer(Conversation $conversation, User $viewer): ?User
    {
        $viewerId = (int) $viewer->id;

        if ((int) $conversation->participant_one_id === $viewerId) {
            return $conversation->participantTwo;
        }

        if ((int) $conversation->participant_two_id === $viewerId) {
            return $conversation->participantOne;
        }

        if ((string) $conversation->conversation_type === Conversation::TYPE_ADMIN_SUPPORT && $this->isAdminContext($viewer)) {
            $participantOne = $conversation->participantOne;
            $participantTwo = $conversation->participantTwo;

            $participantOneIsAdminContext = $participantOne !== null && $this->isAdminContext($participantOne);
            $participantTwoIsAdminContext = $participantTwo !== null && $this->isAdminContext($participantTwo);

            if ($participantOneIsAdminContext && !$participantTwoIsAdminContext) {
                return $participantTwo;
            }

            if ($participantTwoIsAdminContext && !$participantOneIsAdminContext) {
                return $participantOne;
            }

            return $participantOne ?? $participantTwo;
        }

        return $conversation->participantOne;
    }

    protected function buildContextLabel(Conversation $conversation): string
    {
        return match ($conversation->conversation_type) {
            Conversation::TYPE_MODULE_CHAT => 'Module Discussion - '.($conversation->module?->title ?? 'Module'),
            Conversation::TYPE_LESSON_CHAT => 'Lesson Discussion - '.($conversation->lesson?->title ?? 'Lesson'),
            Conversation::TYPE_LESSON_TOPIC_CHAT => 'Lesson Topic Discussion - '.($conversation->lessonTopic?->title ?? 'Topic'),
            Conversation::TYPE_QUIZ_HELP => 'Quiz Help - '.($conversation->quiz?->title ?? 'Quiz'),
            Conversation::TYPE_ADMIN_SUPPORT => 'Platform Support',
            default => 'Direct Conversation',
        };
    }

    protected function latestMessagePreview(?string $body, ?string $messageType): string
    {
        $trimmedBody = trim((string) $body);

        if ($trimmedBody !== '') {
            return Str::limit($trimmedBody, 80);
        }

        return match ((string) $messageType) {
            'attachment' => 'Attachment shared',
            'mixed' => 'Message and attachment shared',
            'deleted' => '[message removed]',
            default => '',
        };
    }

    protected function buildUserSnapshot(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $avatarPath = $user->learnerProfile?->avatar_path
            ?? $user->instructorProfile?->profile_photo_path;

        $displayName = (string) $user->name;

        if ((string) $user->role === 'admin') {
            $displayName = (string) ($user->adminCreatorProfile?->affiliation ?: $displayName ?: 'Conscious Connections Team');
        }

        return [
            'id' => $user->id,
            'name' => $displayName,
            'role' => $user->role,
            'status' => $this->normalizeUserStatus($user->chat_status ?? $user->status),
            'avatar_url' => $this->resolveAvatarUrl($avatarPath),
        ];
    }

    protected function buildSupportAvailability(?User $supportAdmin): array
    {
        $isOnline = $supportAdmin === null
            ? (bool) config('chat.support_availability.default_online', false)
            : $this->normalizeUserStatus($supportAdmin->chat_status ?? $supportAdmin->status) === 'online';

        return [
            'available' => $isOnline,
            'title' => (string) config(
                $isOnline ? 'chat.support_availability.online_title' : 'chat.support_availability.offline_title'
            ),
            'message' => (string) config(
                $isOnline ? 'chat.support_availability.online_message' : 'chat.support_availability.offline_message'
            ),
        ];
    }

    protected function resolveSupportParticipant(Conversation $conversation): ?User
    {
        $participantOne = $conversation->participantOne;
        $participantTwo = $conversation->participantTwo;

        if ($participantOne && $this->isAdminContext($participantOne)) {
            return $participantOne;
        }

        if ($participantTwo && $this->isAdminContext($participantTwo)) {
            return $participantTwo;
        }

        return $participantOne ?? $participantTwo;
    }

    protected function normalizeUserStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        if ($normalized === 'active' || $normalized === 'online') {
            return 'online';
        }

        if ($normalized === 'inactive' || $normalized === 'do_not_disturb' || $normalized === 'dnd') {
            return 'do_not_disturb';
        }

        if (in_array($normalized, ['busy', 'offline'], true)) {
            return $normalized;
        }

        return 'offline';
    }

    protected function resolveAvatarUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $raw = (string) $path;

        if (Str::startsWith($raw, ['http://', 'https://', '//'])) {
            return $raw;
        }

        $normalized = ltrim($raw, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if (!str_contains($normalized, '/')) {
            $normalized = 'avatars/'.$normalized;
        }

        if (!Storage::disk('public')->exists($normalized)) {
            return null;
        }

        return Storage::url($normalized);
    }
}
