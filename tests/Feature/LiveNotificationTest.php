<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Channels\SafeBroadcastChannel;
use App\Notifications\TaskAssigned;
use App\Services\TaskWorkflowService;
use App\Support\LiveUpdates;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class LiveNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);

        // Live notifications on, with a Reverb app of its own (the test environment's .env has none).
        AppSetting::current()->update([
            'live_updates_enabled' => true,
            'reverb_app_id' => 'test-app', 'reverb_app_key' => 'test-key', 'reverb_app_secret' => 'test-secret',
            'reverb_host' => '127.0.0.1', 'reverb_port' => 8080, 'reverb_scheme' => 'http',
        ]);
        LiveUpdates::forget();
    }

    private function teamMember(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    /**
     * Self-assigned, so creating it sends no notification of its own — the
     * tests below control exactly which notifications exist and when.
     */
    private function task(User $owner): Task
    {
        return app(TaskWorkflowService::class)->createTask(['title' => 'Create Client List'], $owner, $owner);
    }

    public function test_notifications_are_stored_then_broadcast_instantly_then_emailed(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $notification = new TaskAssigned($this->task($rahim), $rahim);

        $this->assertSame(['database', SafeBroadcastChannel::class, 'mail'], $notification->via($karim));

        // With live notifications switched off, nothing goes to Reverb; the record and the email still do.
        AppSetting::current()->update(['live_updates_enabled' => false]);
        LiveUpdates::forget();
        $this->assertSame(['database', 'mail'], $notification->via($karim));

        $message = $notification->toBroadcast($karim);
        $this->assertInstanceOf(BroadcastMessage::class, $message);
        $this->assertSame('sync', $message->connection);
        $this->assertSame('Rahim assigned you "Create Client List"', $message->data['message']);
    }

    public function test_a_failed_broadcast_does_not_stop_the_email_or_the_stored_notification(): void
    {
        Mail::fake();
        $this->mock(BroadcastManager::class, function ($mock) {
            $mock->shouldReceive('event')->andThrow(new \RuntimeException('Reverb is down'));
            $mock->shouldReceive('connection')->andThrow(new \RuntimeException('Reverb is down'));
        });

        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $karim->notify(new TaskAssigned($this->task($rahim), $rahim));

        $this->assertSame(1, $karim->notifications()->count());
    }

    public function test_the_notch_only_announces_notifications_that_arrived_after_the_page_loaded(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->task($rahim);

        $karim->notifyNow(new TaskAssigned($task, $rahim), ['database']);
        $this->travel(5)->seconds();

        $component = Livewire::actingAs($karim)->test('notifications.bell');

        $component->call('checkForNew')->assertNotDispatched('notification-flash');

        $this->travel(5)->seconds();
        $karim->notifyNow(new TaskAssigned($task, $rahim), ['database']);

        $component->call('checkForNew')->assertDispatched('notification-flash',
            count: 1,
            message: 'Rahim assigned you "Create Client List"',
        );

        // Already announced: the next check (another push, or the fallback poll) stays quiet.
        $component->call('checkForNew')->assertNotDispatched('notification-flash');
    }

    public function test_several_notifications_at_once_are_announced_together(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->task($rahim);

        $component = Livewire::actingAs($karim)->test('notifications.bell');

        $this->travel(5)->seconds();
        $karim->notifyNow(new TaskAssigned($task, $rahim), ['database']);
        $karim->notifyNow(new TaskAssigned($task, $rahim), ['database']);

        $component->call('checkForNew')->assertDispatched('notification-flash', count: 2, message: '2 new notifications');
    }
}
