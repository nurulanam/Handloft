<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\TaskCategory;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::ALL as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate(RoleEnum::SuperAdmin->value);
        $superAdmin->syncPermissions(PermissionCatalog::ALL);

        // Manager and Team Member are adjustable in Settings → Roles & permissions, so their defaults
        // (PermissionCatalog::DEFAULTS) are only applied when the role is first created; re-running
        // the seeder never undoes an admin's choices.
        foreach ([RoleEnum::Manager, RoleEnum::TeamMember] as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(PermissionCatalog::DEFAULTS[$roleEnum->value]);
            }
        }

        // Installs from before the rename used admin@am2amdesk.test: move that account over instead
        // of creating a second Super Admin.
        if (! User::where('email', 'admin@handloft.test')->exists()) {
            User::where('email', 'admin@am2amdesk.test')->update(['email' => 'admin@handloft.test']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@handloft.test'],
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
