<?php

namespace Database\Factories;

use App\Enums\InteractiveActivityType;
use App\Models\InteractiveActivity;
use App\Models\LessonTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InteractiveActivityFactory extends Factory
{
    protected $model = InteractiveActivity::class;

    public function definition(): array
    {
        return [
            'lesson_topic_id' => LessonTopic::factory(),
            'placement' => 'inside_topic',
            'block_uuid' => (string) Str::uuid(),
            'activity_type' => InteractiveActivityType::MATCHING,
            'title' => fake()->sentence(4),
            'instructions' => fake()->sentence(),
            'explanation' => fake()->sentence(),
            'configuration' => [],
            'revision' => 1,
        ];
    }

    public function matching(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => InteractiveActivityType::MATCHING,
        ]);
    }

    public function sequencing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => InteractiveActivityType::SEQUENCING,
        ]);
    }

    public function insideTopic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'placement' => 'inside_topic',
        ]);
    }

    public function betweenTopics(): static
    {
        return $this->state(fn (array $attributes): array => [
            'placement' => 'between_topics',
        ]);
    }
}
