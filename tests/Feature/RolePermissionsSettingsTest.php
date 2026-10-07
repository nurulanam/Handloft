<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@am2amdesk.test')->firstOrFail();
    }

    private function user(string $name, Role $role): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole($role->value);

        return $user;
    }

    private function refreshPermissions(User ...$users): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($users as $user) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function test_a_super_admin_can_give_and_take_capabilities_and_they_apply_at_once(): void
    {
        $manager = $this->user('Mira Manager', Role::Manager);
        $member = $this->user('Karim Hasan', Role::TeamMember);
        $this->assertFalse($manager->can('edit-completed-hours'));
        $this->assertFalse($member->can('view-reports'));

        $this->actingAs($this->admin())->get(route('settings', ['tab' => 'permissions']))
            ->assertOk()->assertSee('Roles & permissions')->assertSee('Correct logged hours')->assertSee('Always Super Admin only');

        Livewire::actingAs($this->admin())->test('settings')
            ->set('tab', 'permissions')
            ->set('grants.manager', [...RoleModel::findByName('manager')->permissions->pluck('name')->intersect(['view-all-tasks', 'create-task', 'manage-projects', 'view-reports', 'export-data'])->values()->all(), 'edit-completed-hours'])
            ->set('grants.team-member', ['create-task', 'view-reports'])
            ->assertSee('unsaved')
            ->call('savePermissions')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->refreshPermissions($manager, $member);
        $this->assertTrue($manager->can('edit-completed-hours'));
        $this->assertTrue($member->can('view-reports'));

        // Legacy permissions the screen doesn't show are left alone.
        $this->assertTrue(RoleModel::findByName('team-member')->hasPermissionTo('assign-task'));
    }

    public function test_turning_off_reports_also_turns_off_exporting_and_correcting_hours(): void
    {
        $manager = $this->user('Mira Manager', Role::Manager);

        Livewire::actingAs($this->admin())->test('settings')
            ->set('grants.manager', ['view-reports', 'export-data', 'edit-completed-hours'])
            ->set('grants.manager', ['export-data', 'edit-completed-hours'])
            ->assertSet('grants.manager', [])
            ->call('savePermissions');

        $this->refreshPermissions($manager);
        $this->assertFalse($manager->can('export-data'));

        // Even a crafted request can't save a permission without its prerequisite.
        Livewire::actingAs($this->admin())->test('settings')
            ->call('savePermissions')
            ->set('grants', ['manager' => ['export-data'], 'team-member' => []])
            ->call('savePermissions');

        $this->refreshPermissions($manager);
        $this->assertFalse($manager->can('export-data'));
    }

    public function test_reset_puts_back_the_defaults(): void
    {
        Livewire::actingAs($this->admin())->test('settings')
            ->set('grants.manager', [])
            ->call('savePermissions')
            ->call('resetPermissionDefaults')
            ->assertSet('grants.manager', ['view-all-tasks', 'create-task', 'manage-projects', 'view-reports', 'export-data'])
            ->call('savePermissions');

        $this->assertTrue(RoleModel::findByName('manager')->hasPermissionTo('view-reports'));
    }

    public function test_only_super_admins_can_change_permissions_and_the_seeder_keeps_their_choices(): void
    {
        $manager = $this->user('Mira Manager', Role::Manager);

        Livewire::actingAs($manager)->test('settings')->call('savePermissions')->assertForbidden();
        Livewire::actingAs($manager)->test('settings')->set('tab', 'permissions')->assertDontSee('Roles & permissions');

        Livewire::actingAs($this->admin())->test('settings')->set('grants.manager', ['create-task'])->call('savePermissions');
        $this->seed(RoleAndAdminSeeder::class);

        $this->assertFalse(RoleModel::findByName('manager')->hasPermissionTo('view-reports'));
        $this->assertTrue(RoleModel::findByName('super-admin')->hasPermissionTo('view-reports'));
    }

    public function test_a_manager_who_can_manage_people_still_cannot_touch_super_admins(): void
    {
        RoleModel::findByName('manager')->givePermissionTo('manage-users');
        $manager = $this->user('Mira Manager', Role::Manager);
        $member = $this->user('Karim Hasan', Role::TeamMember);

        $this->actingAs($manager)->get(route('users.edit', $member))->assertOk()->assertDontSee('Full access, including settings');
        $this->actingAs($manager)->get(route('users.edit', $this->admin()))->assertForbidden();

        Livewire::actingAs($manager)->test('users.form', ['user' => $member])
            ->set('role', Role::SuperAdmin->value)
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertFalse($member->fresh()->hasRole(Role::SuperAdmin->value));
    }
}
