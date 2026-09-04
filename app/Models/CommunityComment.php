<?php

namespace App\Models;

use App\Enums\CommunityCommentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityComment extends Model
{
    protected $fillable = [
        'community_post_id',
        'parent_id',
        'author_id',
        'body',
        'status',
        'prescreen_decision',
        'prescreen_flags',
        'hidden_at',
        'hidden_by',
        'hidden_reason',
        'removed_at',
        'removed_by',
        'removed_reason',
        'escalated_at',
        'escalated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityCommentStatus::class,
            'prescreen_flags' => 'array',
            'hidden_at' => 'datetime',
            'removed_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->oldest('created_at')
            ->oldest('id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class);
    }

    public function upvotes(): HasMany
    {
        return $this->hasMany(CommunityCommentUpvote::class);
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function scopeMemberVisible(Builder $query): Builder
    {
        return $query
            ->where('status', CommunityCommentStatus::Visible->value)
            ->where(function (Builder $visibility): void {
                $visibility
                    ->whereNull('parent_id')
                    ->orWhereHas('parent', fn (Builder $parent) => $parent
                        ->whereNull('parent_id')
                        ->where('status', CommunityCommentStatus::Visible->value));
            });
    }

    public function oldReportsPlaceholder(): HasMany
    {
        return $this->hasMany(CommunityReport::class);
    }
}
