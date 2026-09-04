<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunitySpace extends Model
{
    protected $fillable = [
        'connector_id',
        'name',
        'status',
        'settings',
        'frozen_at',
        'frozen_by',
        'freeze_reason',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'frozen_at' => 'datetime',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }
}
