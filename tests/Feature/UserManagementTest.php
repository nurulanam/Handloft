<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('users.form')
            ->set('name', 'Karim Rahman')
            ->set('user_id', 'karim')
            ->set('email', 'karim@example.com')
            ->set('phone', '01700000000')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', Role::TeamMember->value)
            ->set('department', 'Operations')
            ->set('joining_date', now()->toDateString())
            ->set('status', 'active')
            ->call('save')
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'karim@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(Role::TeamMember->value));
        $this->assertSame('karim', $user->user_id);
    }

    public function test_non_admin_cannot_create_a_user(): void
    {
        RoleModel::findOrCreate(Role::TeamMember->value);

        $teamMember = User::factory()->create(['status' => 'active']);
        $teamMember->assignRole(Role::TeamMember->value);

        $this->actingAs($teamMember)
            ->get(route('users.create'))
            ->assertForbidden();
    }

    public function test_inactive_status_prevents_login(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('users.form')
            ->set('name', 'Hasan Ali')
            ->set('user_id', 'hasan')
            ->set('email', 'hasan@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', Role::TeamMember->value)
            ->set('status', 'inactive')
            ->call('save');

        $user = User::where('email', 'hasan@example.com')->firstOrFail();

        $this->assertFalse($user->isActive());
    }

    public function test_new_user_form_auto_generates_an_am_prefixed_unique_user_id(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        $userId = Livewire::actingAs($admin)->test('users.form')->get('user_id');

        $this->assertMatchesRegularExpression('/^AM-\d{4}$/', $userId);
    }

    public function test_regenerating_the_user_id_produces_a_new_valid_code(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        $userId = Livewire::actingAs($admin)
            ->test('users.form')
            ->call('regenerateUserId')
            ->get('user_id');

        $this->assertMatchesRegularExpression('/^AM-\d{4}$/', $userId);
    }

    public function test_duplicate_user_id_is_rejected_on_save(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        User::factory()->create(['user_id' => 'AM-1234']);

        Livewire::actingAs($admin)
            ->test('users.form')
            ->set('name', 'Duplicate Test')
            ->set('user_id', 'AM-1234')
            ->set('email', 'duplicate-test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', Role::TeamMember->value)
            ->call('save')
            ->assertHasErrors(['user_id' => 'unique']);

        $this->assertSame(1, User::where('user_id', 'AM-1234')->count());
    }

    public function test_user_can_log_in_with_user_id_instead_of_email(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $admin->update(['password' => bcrypt('password123')]);

        Livewire::test('auth.login')
            ->set('email', 'admin')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
