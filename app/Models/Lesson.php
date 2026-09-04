<?php

namespace App\Models;

use App\Helpers\VideoEmbedHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;
    protected $fillable = [
        'module_id',
        'title',
        'description',
        'order',
        'duration',
        'is_published',
        'text_content',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'duration' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    // Relationships

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function topics()
    {
        return $this->hasMany(LessonTopic::class)->orderBy('order');
    }

    public function userProgress()
    {
        return $this->hasMany(UserProgress::class);
    }

    /**
     * Get lesson completion percentage for a user based on topics.
     */
    public function getTopicCompletionPercentage($userId): int
    {
        $totalTopics = $this->topics()->instructional()->count();
        
        if ($totalTopics === 0) {
            return 0;
        }

        $completedTopics = $this->topics()
            ->instructional()
            ->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('completed', true);
            })
            ->count();

        return (int) (($completedTopics / $totalTopics) * 100);
    }

    /**
     * Check if all topics are completed by user.
     */
    public function allTopicsCompletedBy($userId): bool
    {
        $totalTopics = $this->topics()->instructional()->count();
        
        if ($totalTopics === 0) {
            return true; // No topics means lesson is accessible
        }

        return $this->getTopicCompletionPercentage($userId) === 100;
    }

    // Scopes

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
