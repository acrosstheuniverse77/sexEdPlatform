<?php

namespace Database\Factories;

use App\Models\InteractiveActivity;
use App\Models\InteractiveActivityProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InteractiveActivityProgressFactory extends Factory
{
    protected $model = InteractiveActivityProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'interactive_activity_id' => InteractiveActivity::factory(),
            'activity_revision' => 1,
            'status' => 'in_progress',
            'working_state' => null,
            'attempt_count' => 0,
            'started_at' => null,
            'completed_at' => null,
            'skipped_at' => null,
        ];
    }
}
