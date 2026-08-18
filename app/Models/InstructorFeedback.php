<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorFeedback extends Model
{
    use HasFactory;

    protected $table = 'instructor_feedback';

    protected $fillable = [
        'instructor_id',
        'learner_id',
        'source_module_id',
        'rating',
        'review_html',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function sourceModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'source_module_id');
    }
}
