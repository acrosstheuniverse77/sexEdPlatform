<?php

namespace App\Models;

use App\Enums\CommunityReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityReaction extends Model
{
    protected $fillable = [
        'community_post_id',
        'user_id',
        'reaction_type',
    ];

    protected function casts(): array
    {
        return [
            'reaction_type' => CommunityReactionType::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
