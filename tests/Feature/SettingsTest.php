<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    public function test_super_admin_can_update_the_loading_screen_settings(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('show_loading_screen', false)
            ->set('loading_screen_seconds', 7)
            ->set('loading_screen_opacity', 25)
            ->set('loading_screen_blur', 32)
            ->call('save');

        $settings = AppSetting::current();

        $this->assertFalse($settings->show_loading_screen);
        $this->assertSame(7, $settings->loading_screen_seconds);
        $this->assertSame(25, $settings->loading_screen_opacity);
        $this->assertSame(32, $settings->loading_screen_blur);
    }

    public function test_loading_screen_duration_must_be_within_range(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_seconds', 15)
            ->call('save')
            ->assertHasErrors(['loading_screen_seconds' => 'max']);
    }

    public function test_loading_screen_opacity_and_blur_must_be_within_range(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_opacity', 150)
            ->set('loading_screen_blur', -5)
            ->call('save')
            ->assertHasErrors(['loading_screen_opacity' => 'max', 'loading_screen_blur' => 'min']);
    }

    public function test_default_settings_have_sensible_values(): void
    {
        $settings = AppSetting::current();

        $this->assertTrue($settings->show_loading_screen);
        $this->assertSame(3, $settings->loading_screen_seconds);
        $this->assertSame(10, $settings->loading_screen_opacity);
        $this->assertSame(64, $settings->loading_screen_blur);
    }

    public function test_team_member_cannot_access_settings(): void
    {
        $teamMember = User::factory()->create(['status' => 'active']);
        $teamMember->assignRole(Role::TeamMember->value);

        $this->actingAs($teamMember)
            ->get(route('settings'))
            ->assertForbidden();
    }

    public function test_login_flashes_loading_screen_data_when_enabled(): void
    {
        AppSetting::current()->update(['show_loading_screen' => true, 'loading_screen_seconds' => 5]);

        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'active']);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertTrue(session('just_logged_in'));
        $this->assertSame(5, session('loading_screen_seconds'));
    }

    public function test_login_does_not_flash_loading_screen_data_when_disabled(): void
    {
        AppSetting::current()->update(['show_loading_screen' => false]);

        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'active']);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertFalse(session('just_logged_in', false));
    }
}
