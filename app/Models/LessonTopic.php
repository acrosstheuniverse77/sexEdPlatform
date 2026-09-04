<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'type',
        'video_provider',
        'video_id',
        'video_file_path',
        'caption_file_path',
        'text_content',
        'content_blocks',
        'file_path',
        'worksheet_files',
        'quiz_id',
        'interactive_config',
        'image_attachments',
        'slideshow_data',
        'duration',
        'is_prerequisite',
        'order',
    ];

    protected $casts = [
        'is_prerequisite' => 'boolean',
        'duration' => 'integer',
        'order' => 'integer',
        'interactive_config' => 'array',
        'image_attachments' => 'array',
        'slideshow_data' => 'array',
        'worksheet_files' => 'array',
        'content_blocks' => 'array',
    ];

    /**
     * Get the lesson that owns the topic.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the quiz associated with this topic (if type is 'quiz').
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get all progress records for this topic.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonTopicProgress::class);
    }

    public function interactiveActivities(): HasMany
    {
        return $this->hasMany(InteractiveActivity::class);
    }

    public function standaloneInteractiveActivity(): HasOne
    {
        return $this->hasOne(InteractiveActivity::class)
            ->where('placement', 'between_topics');
    }

    public function checkpointQuestion()
    {
        return $this->hasOne(QuizQuestion::class, 'checkpoint_topic_id')
            ->whereNull('checkpoint_block_uuid');
    }

    public function checkpointQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'checkpoint_topic_id');
    }

    /**
     * Check if user has completed this topic.
     */
    public function isCompletedBy($userId): bool
    {
        return $this->progress()
            ->where('user_id', $userId)
            ->where('completed', true)
            ->exists();
    }

    /**
     * Mark topic as completed for user.
     */
    public function markCompleted($userId): void
    {
        $this->progress()->updateOrCreate(
            ['user_id' => $userId],
            [
                'completed' => true,
                'completed_at' => now(),
            ]
        );
    }

    /**
     * Scope to order topics by their order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeInstructional($query)
    {
        return $query->whereNotIn('type', ['interactive', 'interactive_checkpoint']);
    }

    public function isOptionalInteraction(): bool
    {
        return in_array($this->type, ['interactive', 'interactive_checkpoint'], true);
    }

    /**
     * Get the video embed URL for video topics.
     */
    public function getVideoEmbedUrlAttribute(): ?string
    {
        if ($this->type !== 'video' || ! $this->video_provider || ! $this->video_id) {
            return null;
        }

        return \App\Helpers\VideoEmbedHelper::getEmbedUrl($this->video_provider, $this->video_id);
    }

    /**
     * Get video thumbnail URL for video topics.
     */
    public function getVideoThumbnailAttribute(): ?string
    {
        if ($this->type !== 'video' || ! $this->video_provider || ! $this->video_id) {
            return null;
        }

        return \App\Helpers\VideoEmbedHelper::getThumbnailUrl($this->video_provider, $this->video_id);
    }

    public function getVideoFileUrlAttribute(): ?string
    {
        return $this->resolvePublicMediaUrl($this->video_file_path, 'videos');
    }

    public function getTextContentAttribute($value): ?string
    {
        return $this->normalizeEditorMediaPaths($value);
    }

    public function setTextContentAttribute($value): void
    {
        $this->attributes['text_content'] = $this->normalizeEditorMediaPaths($value);
    }

    private function resolvePublicMediaUrl(?string $path, string $defaultDirectory = ''): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if (! str_contains($normalized, '/') && $defaultDirectory !== '') {
            $normalized = trim($defaultDirectory, '/').'/'.$normalized;
        }

        return Storage::url($normalized);
    }

    private function normalizeEditorMediaPaths($content): ?string
    {
        if ($content === null || $content === '') {
            return $content;
        }

        $normalized = (string) $content;

        // Normalize URLs that were saved with an incorrect "/instructor/storage" prefix.
        $normalized = preg_replace(
            '~((?:src|href)=["\']https?://[^"\']+)/instructor/storage/~i',
            '$1/storage/',
            $normalized,
        );

        $normalized = preg_replace(
            '~((?:src|href)=["\'])/instructor/storage/~i',
            '$1/storage/',
            $normalized,
        );

        $normalized = preg_replace(
            '~((?:src|href)=["\'])instructor/storage/~i',
            '$1/storage/',
            $normalized,
        );

        // Ensure storage URLs are root-relative to avoid route-prefix 404s.
        $normalized = preg_replace(
            '~((?:src|href)=["\'])(?!https?://|//|data:|mailto:|tel:|#|/)(storage/)~i',
            '$1/$2',
            $normalized,
        );

        return $normalized;
    }
}
