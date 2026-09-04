<?php

namespace Tests\Feature\Rbac;

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RbacPermissionCatalogSeederTest extends TestCase
{
    public function test_canonical_permission_catalog_is_seeded_with_legacy_stable_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $canonical = [
            'access admin panel',
            'assign roles',
            'manage permissions',
            'review modules',
            'access chat',
            'view learner progress',
            'community.view_any',
            'community.moderate_any',
            'community.manage_settings',
            'community.freeze',
        ];

        $legacyStable = [
            'view modules',
            'create modules',
            'edit modules',
            'delete modules',
            'take quizzes',
            'view users',
        ];

        foreach (array_merge($canonical, $legacyStable) as $permissionName) {
            $this->assertTrue(
                Permission::query()->where('name', $permissionName)->where('guard_name', 'web')->exists(),
                sprintf('Expected permission "%s" on web guard.', $permissionName)
            );
        }
    }
}
