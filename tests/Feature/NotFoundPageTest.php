<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_user_sees_the_not_found_page_inside_the_app_shell(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(RoleModel::findOrCreate(Role::TeamMember->value));

        $this->actingAs($user)
            ->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('This page wandered off')
            ->assertSee('Back to dashboard')
            ->assertSee('aria-label="Shortcuts"', false);
    }

    public function test_a_missing_record_also_shows_the_app_shell_not_found_page(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(RoleModel::findOrCreate(Role::TeamMember->value));

        $this->actingAs($user)
            ->get('/tasks/999999')
            ->assertNotFound()
            ->assertSee('This page wandered off');
    }

    public function test_a_guest_sees_the_not_found_page_with_a_sign_in_link(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Go to sign in')
            ->assertDontSee('aria-label="Shortcuts"', false);
    }

    public function test_a_signed_in_user_without_access_sees_the_forbidden_page_inside_the_app_shell(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(RoleModel::findOrCreate(Role::TeamMember->value));

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertForbidden()
            ->assertSee('You don&#039;t have access to this', false)
            ->assertSee('Back to dashboard')
            ->assertSee('aria-label="Shortcuts"', false);
    }
}
