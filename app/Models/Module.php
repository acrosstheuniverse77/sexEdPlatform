<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'min_age',
        'max_age',
        'age_specific_content',
        'difficulty_level',
        'order',
        'duration_minutes',
        'is_published',
        'is_premium',
        'access_type',
        'price_amount',
        'price_currency',
        'enrollment_limit',
        'enrollment_mode',
        'final_quiz_id',
        'published_revision_id',
        'published_by_admin_id',
        'content_owner_type',
        'current_review_status',
        'certificate_pass_score',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'age_specific_content' => 'array',
            'order' => 'integer',
            'duration_minutes' => 'integer',
            'is_published' => 'boolean',
            'is_premium' => 'boolean',
            'access_type' => 'string',
            'price_amount' => 'decimal:2',
            'price_currency' => 'string',
            'enrollment_limit' => 'integer',
            'enrollment_mode' => 'string',
            'published_revision_id' => 'integer',
            'published_by_admin_id' => 'integer',
            'content_owner_type' => 'string',
            'current_review_status' => 'string',
            'certificate_pass_score' => 'integer',
        ];
    }

    // Relationships

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function finalQuiz()
    {
        return $this->belongsTo(Quiz::class, 'final_quiz_id');
    }

    public function enrollments()
    {
        return $this->hasMany(ModuleEnrollment::class);
    }

    public function purchases()
    {
        return $this->hasMany(ModulePurchase::class);
    }

    public function moduleSaleLedgers(): HasMany
    {
        return $this->hasMany(ModuleSaleLedger::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function userProgress()
    {
        return $this->hasMany(UserProgress::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function attachments()
    {
        return $this->hasMany(ModuleAttachment::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ModuleRevision::class);
    }

    public function reviewRequests(): HasMany
    {
        return $this->hasMany(ModuleReviewRequest::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(ModuleFeedback::class);
    }

    public function learnerCategories(): HasMany
    {
        return $this->hasMany(ModuleLearnerCategory::class);
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ModuleRevision::class, 'published_revision_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_admin_id');
    }

    // Scopes

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeLearnerVisible($query)
    {
        return $query->where('is_published', true)
            ->where(function ($inner) {
                $inner->whereNotNull('published_revision_id')
                    ->orWhere('current_review_status', 'approved')
                    ->orWhereNull('current_review_status');
            });
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Scope to filter modules appropriate for user's age
     */
    public function scopeForAge($query, int $age)
    {
        $ageBracket = self::ageBracketForAge($age);

        return $query->where(function ($q) use ($age, $ageBracket) {
            if ($ageBracket !== null) {
                $q->whereHas('learnerCategories', function ($categoryQuery) use ($ageBracket) {
                    $categoryQuery->where('category', $ageBracket);
                })->orWhere(function ($legacy) use ($age) {
                    $legacy->whereDoesntHave('learnerCategories')
                        ->where(function ($legacyAge) use ($age) {
                            $legacyAge->where(function ($normal) use ($age) {
                                $normal->where('min_age', '<=', $age)
                                    ->where('max_age', '>=', $age);
                            })->orWhere(function ($swapped) use ($age) {
                                $swapped->where('max_age', '<=', $age)
                                    ->where('min_age', '>=', $age);
                            });
                        });
                });

                return;
            }

            $q->where(function ($normal) use ($age) {
                $normal->where('min_age', '<=', $age)
                    ->where('max_age', '>=', $age);
            })->orWhere(function ($swapped) use ($age) {
                $swapped->where('max_age', '<=', $age)
                    ->where('min_age', '>=', $age);
            });
        });
    }

    /**
     * Scope to filter modules for age bracket
     */
    public function scopeForAgeBracket($query, string $ageBracket)
    {
        $ageRanges = self::learnerCategoryAgeRanges();

        if (!isset($ageRanges[$ageBracket])) {
            return $query;
        }

        [$minAge, $maxAge] = $ageRanges[$ageBracket];

        return $query->where(function ($q) use ($minAge, $maxAge) {
            $q->whereHas('learnerCategories', function ($categoryQuery) use ($minAge, $maxAge) {
                $categoryQuery->whereIn('category', array_keys(array_filter(
                    self::learnerCategoryAgeRanges(),
                    fn (array $range) => $range[0] <= $maxAge && $range[1] >= $minAge
                )));
            })->orWhere(function ($legacy) use ($minAge, $maxAge) {
                $legacy->whereDoesntHave('learnerCategories')
                    ->where('min_age', '<=', $maxAge)
                    ->where('max_age', '>=', $minAge);
            });
        });
    }

    /**
     * Check if module is appropriate for a specific age
     */
    public function isAppropriateForAge(int $age): bool
    {
        $ageBracket = self::ageBracketForAge($age);
        if ($ageBracket !== null && $this->learnerCategories()->exists()) {
            return $this->learnerCategories()
                ->where('category', $ageBracket)
                ->exists();
        }

        if ($this->min_age === null || $this->max_age === null) {
            return false;
        }

        $minAge = min((int) $this->min_age, (int) $this->max_age);
        $maxAge = max((int) $this->min_age, (int) $this->max_age);

        return $age >= $minAge && $age <= $maxAge;
    }

    public function isEligibleForLearnerCategory(string $category): bool
    {
        $category = self::normalizeLearnerCategory($category) ?? '';

        if ($category === '') {
            return false;
        }

        if ($this->learnerCategories()->exists()) {
            return $this->learnerCategories()
                ->where('category', $category)
                ->exists();
        }

        [$minAge, $maxAge] = self::learnerCategoryAgeRanges()[$category] ?? [null, null];

        return $minAge !== null
            && $this->min_age <= $maxAge
            && $this->max_age >= $minAge;
    }

    /**
     * @param array<int, string> $categories
     */
    public function syncLearnerCategories(array $categories): void
    {
        $normalized = self::normalizeLearnerCategories($categories);

        if ($normalized === []) {
            $normalized = ['teens'];
        }

        $this->learnerCategories()->delete();

        foreach ($normalized as $category) {
            $this->learnerCategories()->create(['category' => $category]);
        }

        [$minAge, $maxAge] = self::ageRangeForLearnerCategories($normalized);
        $this->forceFill([
            'min_age' => $minAge,
            'max_age' => $maxAge,
        ])->save();
    }

    /**
     * @return array<int, string>
     */
    public function learnerCategoryKeys(): array
    {
        $categories = $this->relationLoaded('learnerCategories')
            ? $this->learnerCategories->pluck('category')->all()
            : $this->learnerCategories()->pluck('category')->all();

        $normalized = self::normalizeLearnerCategories($categories);

        if ($normalized !== []) {
            return $normalized;
        }

        return self::categoriesForAgeRange((int) $this->min_age, (int) $this->max_age);
    }

    /**
     * @return array<int, string>
     */
    public function learnerCategoryLabels(): array
    {
        return array_map(
            fn (string $category) => self::learnerCategoryLabelsMap()[$category] ?? ucfirst($category),
            $this->learnerCategoryKeys()
        );
    }

    /**
     * Get age-specific content for a given age bracket
     */
    public function getContentForAgeBracket(string $ageBracket): ?array
    {
        if (!$this->age_specific_content) {
            return null;
        }

        return $this->age_specific_content[$ageBracket] ?? null;
    }

    public function isLearnerVisible(): bool
    {
        if (!$this->is_published) {
            return false;
        }

        return $this->published_revision_id !== null
            || $this->current_review_status === 'approved'
            || $this->current_review_status === null;
    }

    public function publishedSnapshot(): ?array
    {
        return $this->publishedRevision?->snapshot_payload;
    }

    public function applyPublishedSnapshot(): static
    {
        $snapshot = $this->publishedSnapshot();
        $moduleData = $snapshot['module'] ?? null;

        if (!is_array($moduleData)) {
            return $this;
        }

        // Keep live governance and lifecycle flags from the canonical module row.
        $protectedKeys = [
            'id',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
            'is_published',
            'current_review_status',
            'published_revision_id',
            'published_by_admin_id',
            'content_owner_type',
        ];

        foreach ($moduleData as $key => $value) {
            if (in_array($key, $protectedKeys, true)) {
                continue;
            }

            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function isPaidAccess(): bool
    {
        return $this->access_type === 'paid' && (float) ($this->price_amount ?? 0) > 0;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null
            && (int) ($this->created_by ?? 0) > 0
            && (int) $this->created_by === (int) $user->id;
    }

    public function getDisplayPriceAttribute(): string
    {
        if (!$this->isPaidAccess()) {
            return 'Free';
        }

        $amount = (float) ($this->price_amount ?? 0);
        $formatted = fmod($amount, 1.0) === 0.0
            ? number_format($amount, 0)
            : number_format($amount, 2);

        return '₱' . $formatted;
    }

    /**
     * Get a resilient public URL for module thumbnails.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->resolvePublicMediaUrl($this->thumbnail, 'modules');
    }

    public function averageFeedbackRating(): float
    {
        return (float) $this->feedback()->avg('rating');
    }

    public function feedbackCount(): int
    {
        return (int) $this->feedback()->count();
    }

    /**
     * @param array<int, string> $categories
     * @return array<int, string>
     */
    public static function normalizeLearnerCategories(array $categories): array
    {
        return collect($categories)
            ->map(fn ($category) => self::normalizeLearnerCategory((string) $category))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeLearnerCategory(string $category): ?string
    {
        $normalized = strtolower(trim($category));

        return match ($normalized) {
            'kid', 'kids', 'child', 'children' => 'kids',
            'teen', 'teens' => 'teens',
            'adult', 'adults' => 'adults',
            default => null,
        };
    }

    /**
     * @return array<string, array{0:int,1:int}>
     */
    public static function learnerCategoryAgeRanges(): array
    {
        return [
            'kids' => [5, 12],
            'teens' => [13, 17],
            'adults' => [18, 100],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function learnerCategoryLabelsMap(): array
    {
        return [
            'kids' => 'Kids',
            'teens' => 'Teens',
            'adults' => 'Adults',
        ];
    }

    /**
     * @param array<int, string> $categories
     * @return array{0:int,1:int}
     */
    public static function ageRangeForLearnerCategories(array $categories): array
    {
        $ranges = collect(self::normalizeLearnerCategories($categories))
            ->map(fn (string $category) => self::learnerCategoryAgeRanges()[$category])
            ->values();

        if ($ranges->isEmpty()) {
            return [13, 17];
        }

        return [
            (int) $ranges->min(fn (array $range) => $range[0]),
            (int) $ranges->max(fn (array $range) => $range[1]),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function categoriesForAgeRange(int $minAge, int $maxAge): array
    {
        $rawMinAge = $minAge;
        $rawMaxAge = $maxAge;
        $minAge = min($rawMinAge, $rawMaxAge);
        $maxAge = max($rawMinAge, $rawMaxAge);

        return collect(self::learnerCategoryAgeRanges())
            ->filter(fn (array $range) => $minAge <= $range[1] && $maxAge >= $range[0])
            ->keys()
            ->values()
            ->all();
    }

    public static function ageBracketForAge(int $age): ?string
    {
        foreach (self::learnerCategoryAgeRanges() as $category => [$minAge, $maxAge]) {
            if ($age >= $minAge && $age <= $maxAge) {
                return $category;
            }
        }

        return null;
    }

    private function resolvePublicMediaUrl(?string $path, string $defaultDirectory = ''): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if (!str_contains($normalized, '/') && $defaultDirectory !== '') {
            $normalized = trim($defaultDirectory, '/') . '/' . $normalized;
        }

        return Storage::url($normalized);
    }
}
