<?php

namespace Tests\Feature\Rbac;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacRoleCapabilityMatrixSeederTest extends TestCase
{
    public function test_core_roles_exist_with_expected_capabilities(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $admin = Role::findByName('admin', 'web');
        $instructor = Role::findByName('instructor', 'web');
        $learner = Role::findByName('learner', 'web');
        $parent = Role::findByName('parent', 'web');

        $this->assertNotNull($admin);
        $this->assertNotNull($instructor);
        $this->assertNotNull($learner);
        $this->assertNotNull($parent);

        $this->assertTrue($admin->hasPermissionTo('manage users'));
        $this->assertTrue($admin->hasPermissionTo('publish modules'));
        $this->assertTrue($admin->hasPermissionTo('community.view_any'));
        $this->assertTrue($admin->hasPermissionTo('community.moderate_any'));
        $this->assertTrue($admin->hasPermissionTo('community.manage_settings'));
        $this->assertTrue($admin->hasPermissionTo('community.freeze'));

        $this->assertTrue($instructor->hasPermissionTo('create modules'));
        $this->assertTrue($instructor->hasPermissionTo('view learners'));
        $this->assertFalse($instructor->hasPermissionTo('publish modules'));
        $this->assertFalse($instructor->hasPermissionTo('manage users'));

        $this->assertTrue($learner->hasPermissionTo('view modules'));
        $this->assertTrue($learner->hasPermissionTo('take quizzes'));
        $this->assertFalse($learner->hasPermissionTo('create modules'));

        $this->assertTrue($parent->hasPermissionTo('view learner progress'));
        $this->assertTrue($parent->hasPermissionTo('view learner enrollments'));
        $this->assertFalse($parent->hasPermissionTo('create modules'));
    }
}
