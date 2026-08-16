<?php

namespace Tests\Feature\Instructor;

use App\Models\Module;
use App\Models\ModuleEnrollment;
use App\Models\ParentChildAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorLearnerCategoryClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adult_learner_without_verified_child_link_is_categorized_as_adult(): void
    {
        /** @var User $instructor */
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        /** @var User $adultLearner */
        $adultLearner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(22)->toDateString(),
            'age' => 22,
        ]);
        $adultLearner->assignRole('learner');

        $module = Module::factory()->create(['created_by' => $instructor->id]);

        ModuleEnrollment::factory()->create([
            'module_id' => $module->id,
            'user_id' => $adultLearner->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseCount('parent_child_accounts', 0);

        $this->actingAs($instructor)
            ->get(route('instructor.users.index'))
            ->assertOk()
            ->assertSeeText('Learner Category Adult')
            ->assertDontSeeText('Learner Category Adult (Guardian)');

        $this->actingAs($instructor)
            ->get(route('instructor.users.show', $adultLearner))
            ->assertOk()
            ->assertSeeText('Adult')
            ->assertDontSeeText('Adult (Guardian)');
    }

    public function test_adult_learner_with_verified_child_link_is_categorized_as_adult_parent(): void
    {
        /** @var User $instructor */
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        /** @var User $adultLearner */
        $adultLearner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(28)->toDateString(),
            'age' => 28,
        ]);
        $adultLearner->assignRole('learner');

        /** @var User $childLearner */
        $childLearner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(10)->toDateString(),
            'age' => 10,
        ]);
        $childLearner->assignRole('learner');

        ParentChildAccount::query()->create([
            'parent_user_id' => $adultLearner->id,
            'child_user_id' => $childLearner->id,
            'can_view_progress' => true,
            'can_view_quiz_answers' => true,
            'can_approve_content' => true,
            'relationship_verified_at' => now(),
        ]);

        $module = Module::factory()->create(['created_by' => $instructor->id]);

        ModuleEnrollment::factory()->create([
            'module_id' => $module->id,
            'user_id' => $adultLearner->id,
            'status' => 'approved',
        ]);

        $this->actingAs($instructor)
            ->get(route('instructor.users.index'))
            ->assertOk()
            ->assertSeeText('Learner Category Adult (Guardian)');

        $this->actingAs($instructor)
            ->get(route('instructor.users.show', $adultLearner))
            ->assertOk()
            ->assertSeeText('Adult (Guardian)');
    }
}
