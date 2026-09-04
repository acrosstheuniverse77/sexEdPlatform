<?php

namespace Tests\Unit\Chat;

use App\Models\User;
use App\Services\Chat\ChatSuggestionCatalog;
use Tests\TestCase;

class ChatSuggestionCatalogTest extends TestCase
{
    public function test_kids_receive_only_active_kids_or_shared_prompts(): void
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'age_bracket_cached' => 'kids',
        ]);

        $suggestions = app(ChatSuggestionCatalog::class)->forUser($learner);

        $this->assertNotEmpty($suggestions);
        $this->assertTrue(collect($suggestions)->every(
            fn (array $entry): bool => $entry['active'] === true
                && in_array('kids', $entry['audience'], true)
        ));
    }

    public function test_age_fallback_resolves_adult_audience_when_cache_is_missing(): void
    {
        $learner = User::factory()->create([
            'role' => 'learner',
            'age_bracket_cached' => null,
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);

        $suggestions = app(ChatSuggestionCatalog::class)->forUser($learner);

        $this->assertNotEmpty($suggestions);
        $this->assertTrue(collect($suggestions)->every(
            fn (array $entry): bool => $entry['active'] === true
                && in_array('adults', $entry['audience'], true)
        ));
    }

    public function test_unknown_non_learner_receives_no_learner_suggestions(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->assertSame([], app(ChatSuggestionCatalog::class)->forUser($instructor));
    }

    public function test_context_selection_prioritizes_matching_entries_and_removes_duplicates(): void
    {
        $catalog = app(ChatSuggestionCatalog::class);
        $learner = User::factory()->create([
            'role' => 'learner',
            'age_bracket_cached' => 'teens',
        ]);

        $suggestions = $catalog->select($catalog->forUser($learner), 'lesson', 4);

        $this->assertNotEmpty($suggestions);
        $this->assertLessThanOrEqual(4, count($suggestions));
        $this->assertSame(
            count($suggestions),
            collect($suggestions)->pluck('key')->unique()->count()
        );
        $this->assertSame(
            count($suggestions),
            collect($suggestions)->pluck('text')->unique()->count()
        );
        $this->assertTrue(
            in_array('lesson', $suggestions[0]['context'], true)
                || in_array('general', $suggestions[0]['context'], true)
        );
    }

    public function test_excluded_keys_are_not_returned(): void
    {
        $catalog = app(ChatSuggestionCatalog::class);
        $learner = User::factory()->create([
            'role' => 'learner',
            'age_bracket_cached' => 'adults',
        ]);

        $all = $catalog->forUser($learner);
        $excludedKey = $all[0]['key'];
        $selected = $catalog->select($all, 'general', 20, [$excludedKey]);

        $this->assertNotContains($excludedKey, collect($selected)->pluck('key')->all());
    }
}
