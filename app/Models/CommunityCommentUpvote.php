<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityCommentUpvote extends Model
{
    protected $fillable = [
        'community_comment_id',
        'user_id',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'community_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}