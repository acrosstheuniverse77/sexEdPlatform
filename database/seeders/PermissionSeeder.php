<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissionNames() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);                                                                                                                                                                                                                                                                                                                                                 
        }
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(): array
    {
        $canonical = [
            'access admin panel',
            'access instructor panel',
            'access learner platform',
            'access parent dashboard',
            'manage users',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'archive users',
            'manage user relationships',
            'manage roles',
            'assign roles',
            'manage permissions',
            'view role assignments',
            'update role assignments',
            'view modules',
            'create modules',
            'edit modules',
            'delete modules',
            'submit modules',
            'resubmit modules',
            'withdraw module submissions',
            'review modules',
            'publish modules',
            'moderate modules',
            'view lessons',
            'create lessons',
            'edit lessons',
            'delete lessons',
            'reorder lessons',
            'move lessons',
            'create lesson topics',
            'edit lesson topics',
            'delete lesson topics',
            'reorder lesson topics',
            'view quizzes',
            'create quizzes',
            'edit quizzes',
            'delete quizzes',
            'manage quiz questions',
            'import quiz questions',
            'view assessment logs',
            'view learners',
            'view enrollments',
            'manage enrollments',
            'approve enrollments',
            'reject enrollments',
            'take quizzes',
            'view certificates',
            'generate certificates',
            'download certificates',
            'enroll modules',
            'purchase modules',
            'view learner progress',
            'view learner enrollments',
            'access chat',
            'start conversations',
            'send messages',
            'edit own messages',
            'delete own messages',
            'report messages',
            'manage message requests',
            'moderate chat',
            'view payments',
            'manage payments',
            'manage subscription plans',
            'manage monetization settings',
            'view financial reports',
            'export financial reports',
            'view own financial reports',
            'export own financial reports',
            'view analytics',
            'view activity logs',
            'manage system settings',
            'manage notifications',
            'community.view_any',
            'community.moderate_any',
            'community.manage_settings',
            'community.freeze',
        ];

        $legacy = [
            'view quiz results',
            'view seminars',
            'create seminars',
            'edit seminars',
            'delete seminars',
            'register seminars',
            'request consultation',
            'manage consultations',
            'approve consultations',
            'manage clinics',
            'approve clinics',
            'manage counselors',
            'approve counselors',
            'download modules',
            'unlimited quiz attempts',
            'access premium content',
        ];

        return array_values(array_unique(array_merge($canonical, $legacy)));
    }
}
