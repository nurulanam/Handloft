<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
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
        'view-own-work-history',
        'view-all-work-history',
        'edit-completed-hours',
        'create-lead',
        'assign-lead',
        'manage-follow-ups',
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
            'view-own-work-history',
            'create-lead',
            'assign-lead',
            'manage-follow-ups',
        ]);

        $teamMember = Role::findOrCreate(RoleEnum::TeamMember->value);
        $teamMember->syncPermissions([
            'create-task',
            'assign-task',
            'view-own-work-history',
            'create-lead',
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@am2amdesk.test'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole(RoleEnum::SuperAdmin->value)) {
            $admin->assignRole(RoleEnum::SuperAdmin->value);
        }
    }
}
