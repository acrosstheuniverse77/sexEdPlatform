<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPostMedia extends Model
{
    protected $table = 'community_post_media';

    protected $fillable = [
        'community_post_id',
        'uploaded_by',
        'media_type',
        'path',
        'mime_type',
        'original_name',
        'size_bytes',
        'display_order',
        'removed_at',
        'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'display_order' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }
}
