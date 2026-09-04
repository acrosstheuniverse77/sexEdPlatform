<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractiveCheckpointProgress extends Model
{
    protected $table = 'interactive_checkpoint_progress';

    protected $fillable = [
        'user_id',
        'lesson_topic_id',
        'quiz_question_id',
        'checkpoint_block_uuid',
        'status',
        'latest_answer',
        'is_correct',
        'attempt_count',
        'answered_at',
        'skipped_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'latest_answer' => 'array',
            'is_correct' => 'boolean',
            'attempt_count' => 'integer',
            'answered_at' => 'datetime',
            'skipped_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessonTopic(): BelongsTo
    {
        return $this->belongsTo(LessonTopic::class);
    }

    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}
