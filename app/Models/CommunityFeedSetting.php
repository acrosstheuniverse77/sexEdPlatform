<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityFeedSetting extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'settings',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
