<?php

namespace Tests\Feature\Instructor;

use App\Models\InteractiveActivity;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class InstructorModuleReviewSubmissionTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_instructor_can_submit_module_for_admin_review(): void
    {
        $instructor = $this->createInstructor();
        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => false,
        ]);

        $this->actingAs($instructor)
            ->post(route('instructor.modules.review.submit', $module))
            ->assertRedirect(route('instructor.modules.show', $module));

        $this->assertDatabaseHas('module_review_requests', [
            'module_id' => $module->id,
            'status' => 'submitted',
            'submitted_by' => $instructor->id,
        ]);
    }

    public function test_submission_snapshot_freezes_inside_and_between_activity_definitions(): void
    {
        $instructor = $this->createInstructor();
        $admin = $this->createAdmin();
        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => false,
        ]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $parent = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Consent Foundations',
            'order' => 1,
            'content_blocks' => [['type' => 'rich_text', 'html' => '<p>Read this first.</p>']],
        ]);
        $inside = InteractiveActivity::query()->create([
            'lesson_topic_id' => $parent->id,
            'placement' => 'inside_topic',
            'block_uuid' => '11111111-1111-4111-8111-111111111111',
            'activity_type' => 'matching',
            'title' => 'Match the concepts',
            'instructions' => '<p>Match each concept.</p>',
            'explanation' => '<p>These concepts support consent.</p>',
            'configuration' => [
                'schema_version' => 1,
                'pairs' => [[
                    'id' => 'pair-1',
                    'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'Consent'],
                    'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'Agreement'],
                ]],
            ],
            'revision' => 3,
        ]);
        $parent->update(['content_blocks' => [[
            'type' => 'rich_text',
            'html' => '<p>Read this first.</p>',
        ], [
            'type' => 'interactive_activity',
            'uuid' => $inside->block_uuid,
            'activity_id' => $inside->id,
        ]]]);
        $host = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Practice checkpoint',
            'type' => 'interactive',
            'order' => 2,
            'duration' => 0,
            'interactive_config' => ['placement' => 'between_topics'],
        ]);
        $between = InteractiveActivity::query()->create([
            'lesson_topic_id' => $host->id,
            'placement' => 'between_topics',
            'activity_type' => 'sequencing',
            'title' => 'Order the conversation',
            'instructions' => '<p>Put these steps in order.</p>',
            'explanation' => '<p>Start with noticing.</p>',
            'configuration' => [
                'schema_version' => 1,
                'items' => [
                    ['id' => 'item-1', 'value' => 'Notice'],
                    ['id' => 'item-2', 'value' => 'Name'],
                    ['id' => 'item-3', 'value' => 'Negotiate'],
                ],
            ],
            'revision' => 2,
        ]);
        LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Continue learning',
            'order' => 3,
        ]);

        $reviewRequest = app(\App\Services\ContentGovernanceService::class)
            ->submitForReview($module, $instructor);

        $snapshot = $reviewRequest->fresh('revision')->revision->snapshot_payload;
        $topics = collect($snapshot['lessons'][0]['topics']);
        $insideSnapshot = $topics->firstWhere('id', $parent->id);
        $betweenSnapshot = $topics->firstWhere('id', $host->id);

        $this->assertSame($parent->content_blocks, $insideSnapshot['content_blocks']);
        $this->assertSame($inside->block_uuid, $insideSnapshot['interactive_activities'][0]['block_uuid']);
        $this->assertSame(3, $insideSnapshot['interactive_activities'][0]['revision']);
        $this->assertSame($inside->configuration, $insideSnapshot['interactive_activities'][0]['configuration']);
        $this->assertSame('sequencing', $betweenSnapshot['interactive_activities'][0]['activity_type']);
        $this->assertSame($between->configuration, $betweenSnapshot['interactive_activities'][0]['configuration']);

        $inside->update(['title' => 'Changed after submission', 'revision' => 4]);
        $between->update(['configuration' => ['schema_version' => 1, 'items' => [['id' => 'item-new', 'value' => 'Changed']]]]);

        $this->actingAs($admin)
            ->get(route('admin.content-reviews.show', $reviewRequest))
            ->assertOk()
            ->assertViewHas('workspace', function (array $workspace) use ($inside, $between): bool {
                $topics = collect(data_get($workspace, 'hierarchy.lessons.0.topics', []));
                $insideActivity = data_get($topics->firstWhere('id', $inside->lesson_topic_id), 'interactive_activities.0');
                $betweenActivity = data_get($topics->firstWhere('id', $between->lesson_topic_id), 'interactive_activities.0');

                return data_get($insideActivity, 'title') === 'Match the concepts'
                    && data_get($betweenActivity, 'configuration.items.0.value') === 'Notice';
            });
    }

    public function test_rejected_module_can_be_resubmitted(): void
    {
        $instructor = $this->createInstructor();
        $admin = $this->createAdmin();
        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => false,
        ]);

        $this->actingAs($instructor)
            ->post(route('instructor.modules.review.submit', $module))
            ->assertRedirect();

        $initialRequest = ModuleReviewRequest::query()->latest('id')->firstOrFail();
        $governanceService = app(\App\Services\ContentGovernanceService::class);
        $governanceService->startReview($initialRequest, $admin);
        $governanceService->rejectReview($initialRequest, $admin, 'Please revise the copy.');

        $this->actingAs($instructor)
            ->post(route('instructor.modules.review.resubmit', $module))
            ->assertRedirect(route('instructor.modules.show', $module));

        $this->assertSame(2, ModuleReviewRequest::query()->where('module_id', $module->id)->count());
        $this->assertDatabaseHas('module_review_requests', [
            'module_id' => $module->id,
            'status' => 'submitted',
        ]);
    }

    public function test_instructor_store_does_not_directly_publish_module_when_governance_is_active(): void
    {
        $instructor = $this->createInstructor();

        $this->actingAs($instructor)
            ->post(route('instructor.modules.store'), [
                'title' => 'Governed Module',
                'description' => 'Needs review before publication',
                'age_bracket' => 'teens',
                'enrollment_mode' => 'auto',
                'is_published' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('modules', [
            'title' => 'Governed Module',
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => false,
        ]);
    }

    private function createInstructor(): User
    {
        $user = User::factory()->create([
            'role' => 'instructor',
            'status' => 'active',
        ]);
        $user->assignRole('instructor');

        return $user;
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
