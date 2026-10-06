<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationReadStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function teamMember(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    /**
     * Gives the user $count stored notifications, one second apart (newest last).
     *
     * @return array<int, DatabaseNotification>
     */
    private function notificationsFor(User $user, int $count): array
    {
        $sender = $this->teamMember('Sender '.$user->id);
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $sender, $sender);

        foreach (range(1, $count) as $i) {
            $this->travel(1)->seconds();
            $user->notifyNow(new TaskAssigned($task, $sender), ['database']);
        }

        return $user->notifications()->get()->all();
    }

    public function test_a_notification_can_be_toggled_between_read_and_unread_on_the_page(): void
    {
        $karim = $this->teamMember('Karim');
        [$notification] = $this->notificationsFor($karim, 1);

        $component = Livewire::actingAs($karim)->test('notifications.index');

        $component->call('toggleRead', $notification->id);
        $this->assertNotNull($notification->fresh()->read_at);

        $component->call('toggleRead', $notification->id);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_the_bell_can_toggle_a_notification_too(): void
    {
        $karim = $this->teamMember('Karim');
        [$notification] = $this->notificationsFor($karim, 1);

        Livewire::actingAs($karim)->test('notifications.bell')->call('toggleRead', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_selected_notifications_can_be_bulk_marked_read_then_unread(): void
    {
        $karim = $this->teamMember('Karim');
        [$first, $second, $third] = $this->notificationsFor($karim, 3);

        $component = Livewire::actingAs($karim)->test('notifications.index')
            ->set('selected', [$first->id, $second->id])
            ->call('markSelected', true)
            ->assertDispatched('notify', message: '2 notifications marked as read.', type: 'success')
            ->assertSet('selected', []);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($third->fresh()->read_at);

        $component->set('selected', [$first->id])->call('markSelected', false);

        $this->assertNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }

    public function test_bulk_marking_never_touches_another_users_notifications(): void
    {
        $karim = $this->teamMember('Karim');
        $rahim = $this->teamMember('Rahim');
        [$rahimsNotification] = $this->notificationsFor($rahim, 1);

        Livewire::actingAs($karim)->test('notifications.index')
            ->set('selected', [$rahimsNotification->id])
            ->call('markSelected', true)
            ->assertDispatched('notify', message: '0 notifications marked as read.');

        Livewire::actingAs($karim)->test('notifications.index')->call('toggleRead', $rahimsNotification->id);

        $this->assertNull($rahimsNotification->fresh()->read_at);
    }

    public function test_the_unread_filter_hides_read_notifications(): void
    {
        $karim = $this->teamMember('Karim');
        [$first, $second] = $this->notificationsFor($karim, 2);
        $first->markAsRead();

        Livewire::actingAs($karim)->test('notifications.index')
            ->set('filter', 'unread')
            ->assertViewHas('notifications', fn ($notifications) => $notifications->pluck('id')->all() === [$second->id]);
    }

    public function test_the_bell_panel_shows_at_most_five_notifications(): void
    {
        $karim = $this->teamMember('Karim');
        $this->notificationsFor($karim, 7);

        Livewire::actingAs($karim)->test('notifications.bell')
            ->assertViewHas('recent', fn ($recent) => $recent->count() === 5)
            ->assertViewHas('unreadCount', 7);
    }
}
