<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndAdminSeeder extends Seeder
{
    /**
     * Permissions from the SRS §41 permission matrix. Items marked "Permission"
     * for a role in the matrix are configurable by Admin later (Settings >
     * Roles & Permissions) rather than granted here by default.
     */
    private const PERMISSIONS = [
        'manage-users',
        'manage-roles-permissions',
        'view-all-tasks',
        'create-task',
        'assign-task',
        'reassign-task',
        'manage-projects',
        'view-own-work-history',
        'view-all-work-history',
        'edit-completed-hours',
        'manage-templates',
        'view-reports',
        'export-data',
        'view-audit-log',
        'manage-settings',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate(RoleEnum::SuperAdmin->value);
        $superAdmin->syncPermissions(self::PERMISSIONS);

        $manager = Role::findOrCreate(RoleEnum::Manager->value);
        $manager->syncPermissions([
            'view-all-tasks',
            'create-task',
            'assign-task',
            'manage-projects',
            'view-own-work-history',
            'view-all-work-history',
            'view-reports',
            'export-data',
        ]);

        $teamMember = Role::findOrCreate(RoleEnum::TeamMember->value);
        $teamMember->syncPermissions([
            'create-task',
            'assign-task',
            'view-own-work-history',
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@am2amdesk.test'],
            [
                'name' => 'Super Admin',
                'user_id' => 'admin',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->user_id) {
            $admin->update(['user_id' => 'admin']);
        }

        if (! $admin->hasRole(RoleEnum::SuperAdmin->value)) {
            $admin->assignRole(RoleEnum::SuperAdmin->value);
        }

        foreach (['General', 'Marketing', 'Development', 'Client Work'] as $category) {
            TaskCategory::firstOrCreate(['name' => $category]);
        }
    }
}
