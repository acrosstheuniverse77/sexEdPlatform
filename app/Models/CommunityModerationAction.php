<?php

namespace App\Models;

use App\Enums\CommunityModerationActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityModerationAction extends Model
{
    protected $fillable = [
        'connector_id',
        'community_space_id',
        'actor_id',
        'target_type',
        'target_id',
        'action_type',
        'previous_status',
        'new_status',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => CommunityModerationActionType::class,
            'metadata' => 'array',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(CommunitySpace::class, 'community_space_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
