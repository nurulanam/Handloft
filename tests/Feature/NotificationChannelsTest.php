<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\TaskWorkflowService;
use App\Support\NotificationChannels;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    private function switches(bool $inApp, bool $email): void
    {
        AppSetting::current()->update(['app_notifications_enabled' => $inApp, 'mail_notifications_enabled' => $email]);
        NotificationChannels::reset();
    }

    /**
     * Assign a task to Karim for real and return the channels the notification actually went out on.
     *
     * @return array{channels: list<string>, stored: int}
     */
    private function assignAndCollect(): array
    {
        $sent = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sent) {
            $sent[] = $event->channel;
        });

        $rahim = $this->member('Rahim');
        $karim = $this->member('Karim');
        app(TaskWorkflowService::class)->createTask(['title' => 'Ship it'], $rahim, $karim);

        // Notifications go out after the response (DeferredNotification); run those callbacks now.
        $this->app->terminate();

        return ['channels' => $sent, 'stored' => $karim->notifications()->count()];
    }

    public function test_both_channels_are_on_by_default(): void
    {
        $this->assertTrue(NotificationChannels::inApp());
        $this->assertTrue(NotificationChannels::email());
        $this->assertSame(['database', 'mail'], (new TaskAssigned(...$this->taskAndActor()))->via(new User));
    }

    public function test_with_email_off_the_in_app_notification_is_still_created(): void
    {
        $this->switches(inApp: true, email: false);

        $result = $this->assignAndCollect();

        $this->assertSame(1, $result['stored']);
        $this->assertContains('database', $result['channels']);
        $this->assertNotContains('mail', $result['channels']);
    }

    public function test_with_in_app_off_only_the_email_goes_out(): void
    {
        $this->switches(inApp: false, email: true);

        $result = $this->assignAndCollect();

        $this->assertSame(0, $result['stored']);
        $this->assertSame(['mail'], $result['channels']);
    }

    public function test_with_both_off_nothing_is_sent(): void
    {
        $this->switches(inApp: false, email: false);

        $result = $this->assignAndCollect();

        $this->assertSame(0, $result['stored']);
        $this->assertSame([], $result['channels']);
    }

    public function test_a_super_admin_switches_them_in_settings(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)->test('settings')
            ->set('tab', 'live')
            ->assertSee('Notification emails')
            ->set('notify_email', false)
            ->call('saveLive')
            ->assertHasNoErrors();

        $settings = AppSetting::current()->fresh();
        $this->assertFalse($settings->mail_notifications_enabled);
        $this->assertTrue($settings->app_notifications_enabled);
        $this->assertFalse(NotificationChannels::email());

        Livewire::actingAs($admin)->test('settings')->set('tab', 'live')
            ->set('notify_in_app', false)
            ->assertSee('With both off, nobody is told');
    }

    /**
     * @return array{0: Task, 1: User}
     */
    private function taskAndActor(): array
    {
        $rahim = $this->member('Rahim');

        return [app(TaskWorkflowService::class)->createTask(['title' => 'Self'], $rahim, $rahim), $rahim];
    }
}
