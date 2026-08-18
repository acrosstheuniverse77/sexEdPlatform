<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Carbon\Carbon;
use App\Notifications\CustomVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    public const ACCOUNT_TYPE_LEARNER_CHILD = 'learner-child';
    public const ACCOUNT_TYPE_LEARNER_TEEN = 'learner-teen';
    public const ACCOUNT_TYPE_LEARNER_ADULT = 'learner-adult';
    public const ACCOUNT_TYPE_PARENT = 'parent';
    public const ACCOUNT_TYPE_INSTRUCTOR = 'instructor';
    public const ACCOUNT_TYPE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_initial',
        'last_name',
        'suffix',
        'email',
        'birthdate',
        'age',
        'password',
        'role',
        'status',
        'account_type',
        'age_bracket_cached',
        'chat_status',
        'verified',
        'is_parent_registration',
        'parent_verification_status',
        'parent_id_type',
        'parent_id_type_other',
        'parent_id_document_path',
        'parent_id_document_back_path',
        'parent_verification_rejection_reason',
        'parent_verification_submitted_at',
        'parent_verification_reviewed_by',
        'parent_verification_reviewed_at',
        'parent_verification_approved_at',
        'guardian_onboarding_status',
        'guardian_onboarding_started_at',
        'guardian_onboarding_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'verified' => 'boolean',
            'birthdate' => 'date',
            'is_parent_registration' => 'boolean',
            'parent_verification_submitted_at' => 'datetime',
            'parent_verification_reviewed_at' => 'datetime',
            'parent_verification_approved_at' => 'datetime',
            'guardian_onboarding_started_at' => 'datetime',
            'guardian_onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and auto-create gamification record.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->gamification()->create([
                'level' => 1,
                'score' => 0,
                'streak_count' => 0,
            ]);
        });
    }

    /**
     * Send the email verification notification using custom template.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    // Relationships

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function learnerProfile()
    {
        return $this->hasOne(LearnerProfile::class);
    }

    public function adminCreatorProfile()
    {
        return $this->hasOne(AdminCreatorProfile::class);
    }

    public function subscription()
    {
        // Return active subscription first, then fall back to latest
        return $this->hasOne(Subscription::class)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere('status', 'trialing');
            })
            ->latest();
    }

    /**
     * Get the latest subscription regardless of status
     */
    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Get the active subscription specifically
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            });
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function userSuspensions()
    {
        return $this->hasMany(UserSuspension::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function instructorSaleLedgers()
    {
        return $this->hasMany(ModuleSaleLedger::class, 'instructor_id');
    }

    public function learnerSaleLedgers()
    {
        return $this->hasMany(ModuleSaleLedger::class, 'learner_id');
    }

    public function instructorOverridePolicies()
    {
        return $this->hasMany(CommissionPolicy::class, 'scope_id')
            ->where('scope_type', CommissionPolicy::SCOPE_INSTRUCTOR);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function seminarSpeakerAssignments()
    {
        return $this->hasMany(SeminarSpeaker::class);
    }

    public function seminarComments()
    {
        return $this->hasMany(SeminarComment::class);
    }

    public function seminarQuestions()
    {
        return $this->hasMany(SeminarQuestion::class);
    }

    public function seminarAttendances()
    {
        return $this->hasMany(SeminarAttendance::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptionPlan()
    {
        return $this->hasOneThrough(
            SubscriptionPlan::class, 
            Subscription::class, 
            'user_id', 
            'id', 
            'id', 
            'plan_id'
        )->where('subscriptions.status', 'active');
    }

    public function counselor()
    {
        return $this->hasOne(Counselor::class);
    }

    public function clinic()
    {
        return $this->hasOne(Clinic::class);
    }

    public function connectorMemberships()
    {
        return $this->hasMany(ConnectorMembership::class);
    }

    public function connectorMembershipRequests()
    {
        return $this->hasMany(ConnectorMembershipRequest::class);
    }

    public function ownedConnectors()
    {
        return $this->hasMany(Connector::class, 'created_by');
    }

    public function moduleEnrollments()
    {
        return $this->hasMany(ModuleEnrollment::class);
    }

    public function modulePurchases()
    {
        return $this->hasMany(ModulePurchase::class);
    }

    public function chatConversationsAsParticipantOne()
    {
        return $this->hasMany(Conversation::class, 'participant_one_id');
    }

    public function chatConversationsAsParticipantTwo()
    {
        return $this->hasMany(Conversation::class, 'participant_two_id');
    }

    public function chatMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function chatMessageRequestsCreated()
    {
        return $this->hasMany(MessageRequest::class, 'requester_id');
    }

    public function chatMessageRequestsAsInstructor()
    {
        return $this->hasMany(MessageRequest::class, 'instructor_id');
    }

    public function chatConversationReads()
    {
        return $this->hasMany(ConversationRead::class);
    }

    public function authoredModules()
    {
        return $this->hasMany(Module::class, 'created_by');
    }

    public function moderationProfile()
    {
        return $this->hasOne(InstructorModerationProfile::class);
    }

    public function violationHistories()
    {
        return $this->hasMany(InstructorViolationHistory::class);
    }

    public function moduleReviewsSubmitted()
    {
        return $this->hasMany(ModuleRevision::class, 'submitted_by');
    }

    public function moduleReviewsReviewed()
    {
        return $this->hasMany(ModuleRevision::class, 'reviewed_by');
    }

    public function moduleFeedback()
    {
        return $this->hasMany(ModuleFeedback::class, 'learner_id');
    }

    public function instructorFeedbackReceived()
    {
        return $this->hasMany(InstructorFeedback::class, 'instructor_id');
    }

    public function instructorFeedbackSubmitted()
    {
        return $this->hasMany(InstructorFeedback::class, 'learner_id');
    }

    public function contentReports()
    {
        return $this->hasMany(ContentReport::class, 'reporter_id');
    }

    public function userProgress()
    {
        return $this->hasMany(UserProgress::class);
    }

    // Alias for userProgress (for cleaner syntax)
    public function progress()
    {
        return $this->hasMany(UserProgress::class);
    }

    public function gamification()
    {
        return $this->hasOne(UserGamification::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'rewards_logs')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function seminarRegistrants()
    {
        return $this->hasMany(SeminarRegistrant::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Helper Methods

    public function isPremium(): bool
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->where(function ($active) {
                    $active->where('status', 'active')
                        ->where(function ($window) {
                            $window->where('ends_at', '>', now())
                                ->orWhere(function ($fallback) {
                                    $fallback->whereNull('ends_at')
                                        ->where(function ($legacy) {
                                            $legacy->whereNull('end_date')
                                                ->orWhere('end_date', '>', now());
                                        });
                                });
                        });
                })->orWhere(function ($grace) {
                    $grace->where('status', 'grace_period')
                        ->where(function ($window) {
                            $window->where('grace_ends_at', '>', now())
                                ->orWhere(function ($fallback) {
                                    $fallback->whereNull('grace_ends_at')
                                        ->where('grace_period_ends', '>', now());
                                });
                        });
                });
            })
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin')
            || $this->role === 'admin'
            || $this->can('access admin panel');
    }

    public function isCounselor(): bool
    {
        return $this->role === 'counselor';
    }

    public function isClinic(): bool
    {
        return $this->role === 'clinic';
    }

    public function isInstructor(): bool
    {
        return ! $this->isAdmin()
            && (
                $this->hasRole('instructor')
                || $this->role === 'instructor'
                || $this->can('access instructor panel')
            );
    }

    /**
     * Get user's full name
     */
    public function getFullNameAttribute(): string
    {
        $nameParts = [];

        if (filled($this->first_name)) {
            $nameParts[] = trim((string) $this->first_name);
        }

        if (filled($this->middle_initial)) {
            $nameParts[] = trim((string) $this->middle_initial) . '.';
        }

        if (filled($this->last_name)) {
            $nameParts[] = trim((string) $this->last_name);
        }

        if (filled($this->suffix)) {
            $nameParts[] = trim((string) $this->suffix);
        }

        $fullName = trim(implode(' ', $nameParts));
        if ($fullName !== '') {
            return $fullName;
        }

        $fallbackName = trim((string) $this->name);
        if ($fallbackName !== '') {
            return $fallbackName;
        }

        return trim((string) $this->email);
    }

    /**
     * Calculate age from birthdate
     */
    public function calculateAge(): ?int
    {
        if (!$this->birthdate) {
            return null;
        }
        
        return Carbon::parse($this->birthdate)->age;
    }

    /**
     * Check if user is under 13 (requires parental consent)
     */
    public function requiresParentalConsent(): bool
    {
        return $this->calculateAge() < 13;
    }

    public function isMinorForCommunityFeed(): bool
    {
        if (in_array($this->account_type, [self::ACCOUNT_TYPE_LEARNER_CHILD, self::ACCOUNT_TYPE_LEARNER_TEEN], true)) {
            return true;
        }

        $age = $this->calculateAge() ?? $this->age;

        return $age !== null && (int) $age < 18;
    }

    /**
     * Check if user is 18+ (can be a parent)
     */
    public function canBeParent(): bool
    {
        return $this->calculateAge() >= 18;
    }

    /**
     * Check if user is a parent with children
     */
    public function isParent(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get parent-child relationships where user is parent
     */
    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_child_accounts', 'parent_user_id', 'child_user_id')
            ->withPivot([
                'id',
                'can_view_progress',
                'can_view_quiz_answers',
                'can_approve_content',
                'relationship_type',
                'relationship_custom',
                'relationship_status',
                'relationship_verified_status',
                'relationship_verification_submitted_at',
                'relationship_verification_reviewed_by',
                'relationship_verification_reviewed_at',
                'relationship_verification_rejection_reason',
                'relationship_verification_rejection_note',
                'relationship_verification_revoked_at',
                'relationship_notes',
                'is_legacy_relationship',
                'relationship_verified_at',
                'verification_status',
                'verification_document_path',
                'verification_rejection_reason',
                'verification_reviewed_by',
                'verification_reviewed_at',
                'verification_approved_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /**
     * Get parent of this user (if they are a child)
     */
    public function parent()
    {
        return $this->belongsToMany(User::class, 'parent_child_accounts', 'child_user_id', 'parent_user_id')
            ->withPivot([
                'id',
                'can_view_progress',
                'can_view_quiz_answers',
                'can_approve_content',
                'relationship_type',
                'relationship_custom',
                'relationship_status',
                'relationship_verified_status',
                'relationship_verification_submitted_at',
                'relationship_verification_reviewed_by',
                'relationship_verification_reviewed_at',
                'relationship_verification_rejection_reason',
                'relationship_verification_rejection_note',
                'relationship_verification_revoked_at',
                'relationship_notes',
                'is_legacy_relationship',
                'relationship_verified_at',
                'verification_status',
                'verification_document_path',
                'verification_rejection_reason',
                'verification_reviewed_by',
                'verification_reviewed_at',
                'verification_approved_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps()
            ->first();
    }

    public function parentChildLink()
    {
        return $this->hasOne(ParentChildAccount::class, 'child_user_id');
    }

    public function isParentRegistration(): bool
    {
        return (bool) $this->is_parent_registration;
    }

    public function isParentVerificationApproved(): bool
    {
        return $this->parent_verification_status === 'approved';
    }

    public function isParentVerificationPending(): bool
    {
        return $this->parent_verification_status === 'pending';
    }

    public function isParentVerificationRejected(): bool
    {
        return $this->parent_verification_status === 'rejected';
    }

    public function hasCompletedGuardianOnboarding(): bool
    {
        if (! $this->isParentRegistration() || ! $this->isParentVerificationApproved()) {
            return true;
        }

        return $this->guardian_onboarding_status === 'completed'
            || ($this->guardian_onboarding_status === null && $this->hasCompletedProfile());
    }

    /**
     * Check if learner profile is completed
     */
    public function hasCompletedProfile(): bool
    {
        if (!$this->isLearner()) {
            return true;
        }

        return $this->learnerProfile?->isCompleted() ?? false;
    }

    public function isLearner(): bool
    {
        if ($this->isAdmin() || $this->isInstructor()) {
            return false;
        }

        return $this->hasRole('learner')
            || $this->role === 'learner'
            || $this->can('access learner platform');
    }

    public function hasLearnerToInstructorTransition(): bool
    {
        return $this->roleTransitions()
            ->where('from_role', 'learner')
            ->where('to_role', 'instructor')
            ->exists();
    }

    public function canSwitchToLearnerView(): bool
    {
        return $this->isInstructor()
            && $this->learnerProfile()->exists()
            && $this->hasLearnerToInstructorTransition();
    }

    public function instructorApplication()
    {
        return $this->hasOne(InstructorApplication::class)->latestOfMany();
    }

    public function instructorApplications()
    {
        return $this->hasMany(InstructorApplication::class);
    }

    public function instructorProfile()
    {
        return $this->hasOne(InstructorProfile::class);
    }

    public function roleTransitions()
    {
        return $this->hasMany(RoleTransition::class);
    }

    public function childLinks()
    {
        return $this->hasMany(ParentChildAccount::class, 'parent_user_id');
    }

    public function parentLinks()
    {
        return $this->hasMany(ParentChildAccount::class, 'child_user_id');
    }

    public function outgoingParentInvitations()
    {
        return $this->hasMany(ParentChildInvitation::class, 'inviter_parent_user_id');
    }

    public function incomingParentInvitations()
    {
        return $this->hasMany(ParentChildInvitation::class, 'child_user_id');
    }

    public function deriveAgeBracketCache(): ?string
    {
        if (! $this->isLearner()) {
            return null;
        }

        $age = $this->calculateAge();

        if ($age === null) {
            return null;
        }

        if ($age <= 12) {
            return 'kids';
        }

        if ($age <= 17) {
            return 'teens';
        }

        return 'adults';
    }

    public function deriveAccountType(): string
    {
        if ($this->isAdmin()) {
            return self::ACCOUNT_TYPE_ADMIN;
        }

        if ($this->isInstructor()) {
            return self::ACCOUNT_TYPE_INSTRUCTOR;
        }

        if ($this->hasRole('parent') || $this->isParent()) {
            return self::ACCOUNT_TYPE_PARENT;
        }

        if ($this->isLearner()) {
            return match ($this->deriveAgeBracketCache()) {
                'kids' => self::ACCOUNT_TYPE_LEARNER_CHILD,
                'teens' => self::ACCOUNT_TYPE_LEARNER_TEEN,
                default => self::ACCOUNT_TYPE_LEARNER_ADULT,
            };
        }

        return (string) $this->role;
    }

    public function refreshClassificationCache(): void
    {
        $this->forceFill([
            'age_bracket_cached' => $this->deriveAgeBracketCache(),
            'account_type' => $this->deriveAccountType(),
        ])->saveQuietly();
    }
}
