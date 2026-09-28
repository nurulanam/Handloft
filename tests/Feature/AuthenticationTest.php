<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::TeamMember->value);
    }

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
            'status' => 'inactive',
        ]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_password_does_not_log_in(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivating_a_logged_in_user_ends_their_session_on_next_request(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $user->update(['status' => 'inactive']);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_there_is_no_public_registration_route(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
