<?php

namespace App\Models;

use App\Enums\CommunityCommentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityComment extends Model
{
    protected $fillable = [
        'community_post_id',
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class);
    }
}
