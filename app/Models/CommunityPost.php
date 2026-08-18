<?php

namespace App\Models;

use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPost extends Model
{
    protected $fillable = [
        'community_space_id',
        'connector_id',
        'author_id',
        'post_type',
        'seminar_id',
        'status',
        'title',
        'body',
        'resource_url',
        'prescreen_decision',
        'prescreen_flags',
        'submitted_at',
        'published_at',
        'published_by',
        'featured_at',
        'featured_by',
        'locked_at',
        'locked_by',
        'lock_reason',
        'hidden_at',
        'hidden_by',
        'hidden_reason',
        'removed_at',
        'removed_by',
        'removed_reason',
        'escalated_at',
        'escalated_by',
        'moderation_case_id',
        'official_answer_comment_id',
    ];

    protected function casts(): array
    {
        return [
            'post_type' => CommunityPostType::class,
            'status' => CommunityPostStatus::class,
            'prescreen_flags' => 'array',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'featured_at' => 'datetime',
            'locked_at' => 'datetime',
            'hidden_at' => 'datetime',
            'removed_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(CommunitySpace::class, 'community_space_id');
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }

    public function officialAnswerComment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'official_answer_comment_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityReaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CommunityPostVersion::class);
    }

    public function moderationActions(): HasMany
    {
        return $this->hasMany(CommunityModerationAction::class, 'target_id')
            ->where('target_type', self::class);
    }

    public function moderationCase(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class);
    }

    public function isPublished(): bool
    {
        return $this->status === CommunityPostStatus::Published;
    }

    public function isLocked(): bool
    {
        return $this->status === CommunityPostStatus::Locked || $this->locked_at !== null;
    }

    public function isFeatured(): bool
    {
        return $this->featured_at !== null;
    }

    public function isEvent(): bool
    {
        return ($this->post_type?->value ?? $this->post_type) === CommunityPostType::Event->value;
    }

    public function isQuestion(): bool
    {
        return ($this->post_type?->value ?? $this->post_type) === CommunityPostType::ModeratedQuestion->value;
    }

    public function isVisibleToMembers(): bool
    {
        return in_array($this->status?->value ?? $this->status, [
            CommunityPostStatus::Published->value,
            CommunityPostStatus::Locked->value,
        ], true);
    }
}
