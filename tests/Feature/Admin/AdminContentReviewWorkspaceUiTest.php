<?php

namespace Tests\Feature\Admin;

use App\Models\InstructorModerationProfile;
use App\Models\Module;
use App\Models\ModuleReviewRequest;
use App\Models\ModuleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminContentReviewWorkspaceUiTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_workspace_ui_renders_metadata_hierarchy_and_structured_rejection_fields(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reviewRequest = $this->createPendingReviewRequest();

        $this->actingAs($admin)
            ->get(route('admin.content-reviews.show', $reviewRequest))
            ->assertOk()
            ->assertSee('Module Overview', false)
            ->assertSee('Creator Information', false)
            ->assertSee('Module Structure Summary', false)
            ->assertSee('Instructor Credibility', false)
            ->assertSee('Content Structure', false)
            ->assertSee('Learner Module Progression', false)
            ->assertSee('data-testid="review-tree-lesson-node"', false)
            ->assertSee('Preview Topic', false)
            ->assertSee('Interactive Activities', false)
            ->assertSee('Preview Activity', false)
            ->assertSee('Matching relationships', false)
            ->assertSee('Canonical sequence', false)
            ->assertSee('Prerequisite: No', false)
            ->assertSee('View Creator Profile', false)
            ->assertSee('Instructor Evaluation', false)
            ->assertSee('name="reason_code"', false)
            ->assertSee('name="issue_warning"', false)
            ->assertSee('name="moderation_notes"', false);
    }

    private function createPendingReviewRequest(): ModuleReviewRequest
    {
        $instructor = $this->createUserWithRole('instructor');

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => false,
            'current_review_status' => 'in_review',
            'title' => 'Workspace Module',
        ]);

        $revision = ModuleRevision::query()->create([
            'module_id' => $module->id,
            'revision_number' => 1,
            'snapshot_payload' => [
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => 'Module description for workspace.',
                ],
                'lessons' => [
                    [
                        'attributes' => [
                            'id' => 1,
                            'title' => 'Lesson 1',
                            'order' => 1,
                        ],
                        'topics' => [
                            [
                                'id' => 11,
                                'title' => 'Topic 1',
                                'type' => 'text',
                                'order' => 1,
                                'interactive_activities' => [[
                                    'id' => 211,
                                    'lesson_topic_id' => 11,
                                    'placement' => 'inside_topic',
                                    'block_uuid' => '11111111-1111-4111-8111-111111111111',
                                    'activity_type' => 'matching',
                                    'title' => 'Review matching activity',
                                    'instructions' => '<p>Match the pairs.</p>',
                                    'explanation' => '<p>Review explanation.</p>',
                                    'configuration' => [
                                        'schema_version' => 1,
                                        'pairs' => [[
                                            'id' => 'pair-1',
                                            'left' => ['id' => 'left-1', 'kind' => 'text', 'value' => 'Consent'],
                                            'right' => ['id' => 'right-1', 'kind' => 'text', 'value' => 'Agreement'],
                                        ]],
                                    ],
                                    'revision' => 1,
                                ]],
                            ],
                        ],
                    ],
                ],
                'quizzes' => [],
            ],
            'submitted_by' => $instructor->id,
            'status' => 'in_review',
            'submitted_at' => now(),
        ]);

        InstructorModerationProfile::query()->create([
            'user_id' => $instructor->id,
            'warning_count' => 1,
            'current_restriction_status' => null,
            'last_violation_at' => now()->subDay(),
            'escalation_level' => 1,
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
