<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractiveActivityProgress extends Model
{
    use HasFactory;

    protected $table = 'interactive_activity_progress';

    protected $fillable = [
        'user_id',
        'interactive_activity_id',
        'activity_revision',
        'status',
        'working_state',
        'attempt_count',
        'started_at',
        'completed_at',
        'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'working_state' => 'array',
            'activity_revision' => 'integer',
            'attempt_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactiveActivity(): BelongsTo
    {
        return $this->belongsTo(InteractiveActivity::class);
    }
}
