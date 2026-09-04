<?php

namespace Tests\Feature\Admin;

use App\Models\InstructorModerationProfile;
use App\Models\InstructorViolationHistory;
use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\ModuleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminContentReviewWorkspaceDataTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_show_page_provides_workspace_payload_with_hierarchy_and_moderation_summary(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reviewRequest = $this->createPendingReviewRequest();

        $this->actingAs($admin)
            ->get(route('admin.content-reviews.show', $reviewRequest))
            ->assertOk()
            ->assertViewHas('workspace', function (array $workspace): bool {
                return isset(
                    $workspace['module'],
                    $workspace['hierarchy'],
                    $workspace['instructor'],
                    $workspace['moderation'],
                    $workspace['instructor_preview']
                )
                && isset($workspace['hierarchy']['lessons'])
                && isset($workspace['hierarchy']['final_quizzes'])
                && isset($workspace['hierarchy']['stepper'])
                && isset($workspace['hierarchy']['lesson_topic_count'])
                && isset($workspace['hierarchy']['lessons'][0]['topics'][0]['interactive_activities'])
                && data_get($workspace, 'hierarchy.lessons.0.topics.0.interactive_activities.0.title') === 'Topic activity'
                && data_get($workspace, 'hierarchy.lessons.0.topics.0.interactive_activities.0.configuration.pairs.0.left.value') === 'Consent'
                && isset($workspace['moderation']['warning_count'])
                && isset($workspace['moderation']['recent_violations'])
                && isset($workspace['module']['status_label'])
                && array_key_exists('thumbnail_url', $workspace['module'])
                && isset($workspace['instructor_preview']['profile'])
                && isset($workspace['instructor_preview']['indicators'])
                && isset($workspace['instructor_preview']['moderation'])
                && isset($workspace['instructor_preview']['module_portfolio']);
            });
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
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'access_type' => 'premium',
                    'enrollment_mode' => 'manual_approval',
                    'enrollment_limit' => 20,
                    'price_amount' => '499.00',
                    'price_currency' => 'PHP',
                ],
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
                                'title' => 'Topic A',
                                'type' => 'text',
                                'order' => 1,
                                'text_content' => '<p>Topic body</p>',
                                'content_blocks' => [[
                                    'type' => 'interactive_activity',
                                    'uuid' => '11111111-1111-4111-8111-111111111111',
                                    'activity_id' => 210,
                                ]],
                                'interactive_activities' => [[
                                    'id' => 210,
                                    'lesson_topic_id' => 201,
                                    'placement' => 'inside_topic',
                                    'block_uuid' => '11111111-1111-4111-8111-111111111111',
                                    'activity_type' => 'matching',
                                    'title' => 'Topic activity',
                                    'instructions' => '<p>Match these.</p>',
                                    'explanation' => '<p>Good work.</p>',
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
                        ],
                    ],
                ],
                'quizzes' => [
                    [
                        'attributes' => [
                            'id' => 301,
                            'lesson_id' => 101,
                            'title' => 'Quiz One',
                            'description' => 'Quiz description',
                            'passing_score' => 75,
                            'time_limit' => 10,
                            'attempt_limit' => 3,
                        ],
                        'questions' => [],
                    ],
                    [
                        'attributes' => [
                            'id' => 302,
                            'lesson_id' => null,
                            'title' => 'Final Quiz',
                            'description' => 'Final quiz description',
                            'passing_score' => 80,
                            'time_limit' => 12,
                            'attempt_limit' => 2,
                        ],
                        'questions' => [],
                    ],
                ],
            ],
            'submitted_by' => $instructor->id,
            'status' => 'in_review',
            'submitted_at' => now(),
        ]);

        $reviewRequest = ModuleReviewRequest::query()->create([
            'module_id' => $module->id,
            'module_revision_id' => $revision->id,
            'status' => 'in_review',
            'submitted_by' => $instructor->id,
            'submitted_at' => now(),
        ]);

        InstructorModerationProfile::query()->create([
            'user_id' => $instructor->id,
            'warning_count' => 2,
            'current_restriction_status' => 'restricted',
            'restriction_starts_at' => now()->subDay(),
            'restriction_ends_at' => now()->addDays(2),
            'last_violation_at' => now()->subDay(),
            'escalation_level' => 2,
        ]);

        InstructorViolationHistory::query()->create([
            'user_id' => $instructor->id,
            'module_id' => $module->id,
            'module_review_request_id' => $reviewRequest->id,
            'reason_code' => 'low_quality_lessons',
            'guidance_note' => 'Need better sequencing.',
            'violation_sequence' => 2,
            'suggested_penalty_action' => 'restrict_3_days',
            'confirmed_penalty_action' => null,
            'confirmed_by_admin_id' => null,
        ]);

        return $reviewRequest;
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
