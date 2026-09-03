<?php

namespace Tests\Feature\Admin;

use App\Models\InteractiveActivity;
use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\ModuleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminContentReviewPreviewEndpointTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_admin_can_fetch_topic_preview_payload(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reviewRequest = $this->createPendingReviewRequest();

        $this->actingAs($admin)
            ->getJson(route('admin.content-reviews.preview', $reviewRequest).'?node_type=topic&node_id=201')
            ->assertOk()
            ->assertJsonPath('node.type', 'topic')
            ->assertJsonPath('node.id', 201)
            ->assertJsonMissing(['text_content' => '<script>alert(1)</script><p>Safe</p>']);
    }

    public function test_non_admin_cannot_fetch_preview_payload(): void
    {
        $instructor = $this->createUserWithRole('instructor');
        $reviewRequest = $this->createPendingReviewRequest();

        $this->actingAs($instructor)
            ->getJson(route('admin.content-reviews.preview', $reviewRequest).'?node_type=topic&node_id=201')
            ->assertStatus(403);
    }

    public function test_admin_can_fetch_matching_and_sequencing_activity_preview_payloads(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reviewRequest = $this->createPendingReviewRequest();

        $this->actingAs($admin)
            ->getJson(route('admin.content-reviews.preview', $reviewRequest).'?node_type=activity&node_id=401')
            ->assertOk()
            ->assertJsonPath('node.type', 'activity')
            ->assertJsonPath('node.id', 401)
            ->assertJsonPath('node.activity_type', 'matching')
            ->assertJsonPath('node.configuration.pairs.0.left.value', 'Consent')
            ->assertJsonPath('node.configuration.pairs.0.right.value', 'Agreement')
            ->assertJsonPath('node.instructions', '<p>Match safely.</p>')
            ->assertJsonMissing(['instructions' => '<script>alert(1)</script><p>Match safely.</p>']);

        $this->actingAs($admin)
            ->getJson(route('admin.content-reviews.preview', $reviewRequest).'?node_type=activity&node_id=402')
            ->assertOk()
            ->assertJsonPath('node.activity_type', 'sequencing')
            ->assertJsonPath('node.configuration.items.0.value', 'Notice')
            ->assertJsonPath('node.configuration.items.1.value', 'Name')
            ->assertJsonPath('node.configuration.items.2.value', 'Negotiate');
    }

    public function test_activity_absent_from_frozen_snapshot_is_not_resolved_from_live_module(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reviewRequest = $this->createPendingReviewRequest();
        $lesson = Lesson::factory()->create(['module_id' => $reviewRequest->module_id]);
        $host = LessonTopic::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'interactive',
            'duration' => 0,
        ]);
        InteractiveActivity::query()->create([
            'id' => 999,
            'lesson_topic_id' => $host->id,
            'placement' => 'between_topics',
            'activity_type' => 'matching',
            'title' => 'Live only activity',
            'configuration' => ['schema_version' => 1, 'pairs' => []],
            'revision' => 1,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.content-reviews.preview', $reviewRequest).'?node_type=activity&node_id=999')
            ->assertNotFound();
    }

    private function createPendingReviewRequest(): ModuleReviewRequest
    {
        $instructor = $this->createUserWithRole('instructor');

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => false,
            'current_review_status' => 'in_review',
        ]);

        $revision = ModuleRevision::query()->create([
            'module_id' => $module->id,
            'revision_number' => 1,
            'snapshot_payload' => [
                'module' => ['id' => $module->id, 'title' => $module->title],
                'lessons' => [
                    [
                        'attributes' => [
                            'id' => 101,
                            'title' => 'Lesson One',
                            'order' => 1,
                        ],
                        'topics' => [
                            [
                                'id' => 201,
                                'title' => 'Unsafe Topic',
                                'type' => 'text',
                                'text_content' => '<script>alert(1)</script><p>Safe</p>',
                                'order' => 1,
                                'interactive_activities' => [[
                                    'id' => 401,
                                    'lesson_topic_id' => 201,
                                    'placement' => 'inside_topic',
                                    'block_uuid' => '11111111-1111-4111-8111-111111111111',
                                    'activity_type' => 'matching',
                                    'title' => 'Match safely',
                                    'instructions' => '<script>alert(1)</script><p>Match safely.</p>',
                                    'explanation' => '<p>Matching explanation.</p>',
                                    'configuration' => [
                                        'schema_version' => 1,
                                        'pairs' => [[
                                            'id' => 'pair-1',
                                            'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'Consent'],
                                            'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'Agreement'],
                                        ]],
                                    ],
                                    'revision' => 2,
                                ]],
                            ],
                            [
                                'id' => 202,
                                'title' => 'Sequencing Host',
                                'type' => 'interactive',
                                'order' => 2,
                                'interactive_activities' => [[
                                    'id' => 402,
                                    'lesson_topic_id' => 202,
                                    'placement' => 'between_topics',
                                    'block_uuid' => null,
                                    'activity_type' => 'sequencing',
                                    'title' => 'Order safely',
                                    'instructions' => '<p>Order the steps.</p>',
                                    'explanation' => '<p>Start by noticing.</p>',
                                    'configuration' => [
                                        'schema_version' => 1,
                                        'items' => [
                                            ['id' => 'item-1', 'value' => 'Notice'],
                                            ['id' => 'item-2', 'value' => 'Name'],
                                            ['id' => 'item-3', 'value' => 'Negotiate'],
                                        ],
                                    ],
                                    'revision' => 4,
                                ]],
                            ],
                        ],
                    ],
                ],
                'quizzes' => [
                    [
                        'attributes' => [
                            'id' => 301,
                            'title' => 'Quiz A',
                        ],
                        'questions' => [],
                    ],
                ],
            ],
            'submitted_by' => $instructor->id,
            'status' => 'in_review',
            'submitted_at' => now(),
        ]);

        return ModuleReviewRequest::query()->create([
            'module_id' => $module->id,
            'module_revision_id' => $revision->id,
            'status' => 'in_review',
            'submitted_by' => $instructor->id,
            'submitted_at' => now(),
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
