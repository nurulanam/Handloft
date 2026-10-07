<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\LiveUpdates;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LiveUpdatesSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        LiveUpdates::reset();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@handloft.test')->firstOrFail();
    }

    private function customServer(): array
    {
        return [
            'live_enabled' => true,
            'live_custom' => true,
            'reverb_app_id' => 'handloft',
            'reverb_app_key' => 'public-key-123',
            'reverb_app_secret' => 'super-secret-value',
            'reverb_host' => '127.0.0.1',
            'reverb_port' => '8080',
            'reverb_scheme' => 'http',
            'reverb_client_host' => 'desk.example.com',
            'reverb_client_port' => '443',
            'reverb_client_scheme' => 'https',
        ];
    }

    private function fill($component, array $values)
    {
        foreach ($values as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    public function test_with_nothing_saved_it_follows_env(): void
    {
        // The test environment broadcasts to "null", so live push is off and the page says so.
        $this->assertFalse(LiveUpdates::enabled());

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertSee('name="live-config"', false)
            ->assertSee('&quot;enabled&quot;:false', false);
    }

    public function test_a_custom_server_is_saved_applied_and_its_secret_kept_private(): void
    {
        $this->fill(Livewire::actingAs($this->admin())->test('settings')->set('tab', 'live'), $this->customServer())
            ->set('live_poll_seconds', 60)
            ->call('saveLive')
            ->assertHasNoErrors()
            ->assertDispatched('live-config-changed', fn ($name, $params) => $params['config']['enabled'] === true && $params['config']['key'] === 'public-key-123' && $params['config']['host'] === 'desk.example.com')
            ->assertSet('reverb_app_secret', '')
            ->assertSet('reverb_secret_saved', true);

        $settings = AppSetting::current()->fresh();
        $this->assertTrue($settings->live_updates_enabled);
        $this->assertSame('super-secret-value', $settings->reverb_app_secret);
        $this->assertNotSame('super-secret-value', DB::table('app_settings')->value('reverb_app_secret'), 'stored encrypted');

        LiveUpdates::forget();
        LiveUpdates::applyFromDatabase();
        $this->assertSame('reverb', config('broadcasting.default'));
        $this->assertSame('public-key-123', config('broadcasting.connections.reverb.key'));
        $this->assertSame('127.0.0.1', config('broadcasting.connections.reverb.options.host'));
        $this->assertSame('public-key-123', config('reverb.apps.apps.0.key'));

        $page = $this->actingAs($this->admin())->get(route('settings', ['tab' => 'live']));
        $page->assertSee('desk.example.com')->assertSee('&quot;poll&quot;:60', false)->assertDontSee('super-secret-value');

        // Saving again with the secret left blank keeps it.
        Livewire::actingAs($this->admin())->test('settings')->set('reverb_app_key', 'rotated-key')->call('saveLive')->assertHasNoErrors();
        $this->assertSame('super-secret-value', AppSetting::current()->fresh()->reverb_app_secret);
        $this->assertSame('rotated-key', AppSetting::current()->fresh()->reverb_app_key);
    }

    public function test_switching_back_to_env_clears_the_custom_server_and_switching_off_stops_the_push(): void
    {
        $this->fill(Livewire::actingAs($this->admin())->test('settings'), $this->customServer())->call('saveLive');

        Livewire::actingAs($this->admin())->test('settings')
            ->set('live_enabled', false)
            ->set('live_custom', false)
            ->call('saveLive')
            ->assertHasNoErrors()
            ->assertDispatched('live-config-changed', fn ($name, $params) => $params['config']['enabled'] === false);

        $settings = AppSetting::current()->fresh();
        $this->assertFalse($settings->live_updates_enabled);
        $this->assertNull($settings->reverb_app_secret);

        LiveUpdates::forget();
        LiveUpdates::applyFromDatabase();
        $this->assertSame('null', config('broadcasting.default'));
    }

    public function test_the_server_details_are_validated(): void
    {
        Livewire::actingAs($this->admin())->test('settings')
            ->set('live_custom', true)
            ->call('saveLive')
            ->assertHasErrors(['reverb_app_id', 'reverb_app_key', 'reverb_app_secret', 'reverb_host', 'reverb_port']);

        $this->fill(Livewire::actingAs($this->admin())->test('settings'), ['reverb_host' => 'https://reverb.example.com/ws', 'reverb_client_port' => '70000'] + $this->customServer())
            ->set('reverb_host', 'https://reverb.example.com/ws')
            ->set('reverb_client_port', '70000')
            ->call('saveLive')
            ->assertHasErrors(['reverb_host', 'reverb_client_port']);

        Livewire::actingAs($this->admin())->test('settings')->set('live_poll_seconds', 1)->call('saveLive')->assertHasErrors(['live_poll_seconds']);
    }

    public function test_it_will_not_switch_on_without_any_credentials(): void
    {
        config(['broadcasting.connections.reverb.key' => null, 'broadcasting.connections.reverb.app_id' => null]);
        LiveUpdates::reset();

        Livewire::actingAs($this->admin())->test('settings')
            ->set('live_enabled', true)
            ->set('live_custom', false)
            ->call('saveLive')
            ->assertHasErrors(['live_custom']);

        $this->assertNull(AppSetting::current()->fresh()->live_updates_enabled);
    }

    public function test_testing_an_unreachable_server_reports_it_clearly(): void
    {
        $started = microtime(true);

        $this->fill(Livewire::actingAs($this->admin())->test('settings')->set('tab', 'live'), ['reverb_host' => '127.0.0.1', 'reverb_port' => '1'] + $this->customServer())
            ->set('reverb_port', '1')
            ->call('testLive')
            ->assertSet('liveTest.ok', false)
            ->assertSee('Couldn')
            ->assertSee('Server: not reachable');

        $this->assertLessThan(10, microtime(true) - $started);
        $this->assertNull(AppSetting::current()->fresh()->reverb_app_id, 'testing never saves');
    }

    public function test_only_super_admins_can_change_or_test_it(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole(Role::Manager->value);

        Livewire::actingAs($manager)->test('settings')->call('saveLive')->assertForbidden();
        Livewire::actingAs($manager)->test('settings')->call('testLive')->assertForbidden();
        Livewire::actingAs($manager)->test('settings')->set('tab', 'live')->assertDontSee('Reverb server');
    }
}
