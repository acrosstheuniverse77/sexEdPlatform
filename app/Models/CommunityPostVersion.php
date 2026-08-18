<?php

namespace App\Models;

use App\Enums\CommunityPostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPostVersion extends Model
{
    protected $fillable = [
        'community_post_id',
        'edited_by',
        'version_number',
        'title',
        'body',
        'resource_url',
        'post_type',
        'prescreen_decision',
        'prescreen_flags',
    ];

    protected function casts(): array
    {
        return [
            'post_type' => CommunityPostType::class,
            'prescreen_flags' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
