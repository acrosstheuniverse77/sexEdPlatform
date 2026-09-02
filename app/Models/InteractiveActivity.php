<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InteractiveActivityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InteractiveActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_topic_id',
        'placement',
        'block_uuid',
        'activity_type',
        'title',
        'instructions',
        'explanation',
        'configuration',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => InteractiveActivityType::class,
            'configuration' => 'array',
            'revision' => 'integer',
        ];
    }

    public function lessonTopic(): BelongsTo
    {
        return $this->belongsTo(LessonTopic::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(InteractiveActivityProgress::class);
    }
}
