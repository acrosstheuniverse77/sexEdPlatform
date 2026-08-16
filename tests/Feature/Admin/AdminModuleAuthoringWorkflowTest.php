<?php

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DatabaseTestCase;

class AdminModuleAuthoringWorkflowTest extends DatabaseTestCase
{
    use DatabaseTransactions;

    public function test_admin_can_publish_draft_and_archive_via_shared_module_flow(): void
    {
        $admin = $this->createAdmin();

        $publishResponse = $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->modulePayload('Published Admin Module', 'publish'));
        $publishResponse->assertRedirect();

        $publishedModule = Module::query()->where('title', 'Published Admin Module')->firstOrFail();
        $this->assertSame('admin', $publishedModule->content_owner_type);
        $this->assertTrue((bool) $publishedModule->is_published);
        $this->assertSame('approved', $publishedModule->current_review_status);
        $this->assertSame($admin->id, (int) $publishedModule->published_by_admin_id);

        $draftResponse = $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->modulePayload('Draft Admin Module', 'draft'));
        $draftResponse->assertRedirect();

        $this->assertDatabaseHas('modules', [
            'title' => 'Draft Admin Module',
            'content_owner_type' => 'admin',
            'is_published' => false,
            'current_review_status' => 'draft',
        ]);

        $archiveResponse = $this->actingAs($admin)
            ->post(route('admin.modules.store'), $this->modulePayload('Archived Admin Module', 'archive'));
        $archiveResponse->assertRedirect();

        $archivedModule = Module::withTrashed()->where('title', 'Archived Admin Module')->firstOrFail();
        $this->assertNotNull($archivedModule->deleted_at);
        $this->assertSame('admin', $archivedModule->content_owner_type);
    }

    /**
     * @return array<string, mixed>
     */
    private function modulePayload(string $title, string $action): array
    {
        return [
            'title' => $title,
            'description' => 'Admin module payload',
            'age_bracket' => 'teens',
            'enrollment_mode' => 'auto',
            'enrollment_limit' => 50,
            'action' => $action,
        ];
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        return $admin;
    }
}
