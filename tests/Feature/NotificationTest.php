<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectCoordinatorAssigned;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskReadyToDeploy;
use App\Notifications\TaskRejected;
use App\Notifications\TaskSubmittedForQa;
use App\Services\TaskWorkflowService;
use App\Support\DeferredNotification;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as NotificationBase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    private function manager(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::Manager->value);

        return $user;
    }

    /**
     * Notifications are dispatched via dispatch(...)->afterResponse() rather
     * than queued, so nothing actually runs until the HTTP kernel's
     * terminate() callbacks fire — which a real request does automatically,
     * but a Livewire component test or a direct service call does not.
     * Triggering it manually here is the test-only equivalent of "the
     * response finished flushing".
     *
     * A real request's Application instance is discarded after terminate()
     * runs once, but a single test method reuses the same instance across
     * several actions — and terminate() never clears its callback list, so
     * a later flush would silently replay every earlier one too. Draining
     * the list here keeps each flush scoped to what's pending right now.
     */
    private function flushDeferredNotifications(): void
    {
        $this->app->terminate();

        (function () {
            $this->terminatingCallbacks = [];
        })->call($this->app);
    }

    public function test_creating_a_task_notifies_the_assignee(): void
    {
        Notification::fake();

        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $this->flushDeferredNotifications();

        Notification::assertSentTo($karim, TaskAssigned::class);
        Notification::assertNotSentTo($rahim, TaskAssigned::class);
    }

    public function test_self_assigning_a_task_does_not_notify_yourself(): void
    {
        Notification::fake();

        $rahim = $this->teamMember('Rahim');

        app(TaskWorkflowService::class)->createTask(['title' => 'Create Client List'], $rahim, $rahim);
        $this->flushDeferredNotifications();

        Notification::assertNothingSent();
    }

    public function test_reassigning_a_task_notifies_the_new_assignee(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Notification::fake();

        $workflow->reassignTask($task, $hasan, $admin);
        $this->flushDeferredNotifications();

        Notification::assertSentTo($hasan, TaskAssigned::class);
    }

    public function test_submitting_for_qa_notifies_the_reviewer(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::InProgress, 'qa_id' => $qa->id], $rahim, $karim);

        Notification::fake();

        $workflow->submitForQa($task, $karim, 3.0);
        $this->flushDeferredNotifications();

        Notification::assertSentTo($qa, TaskSubmittedForQa::class);
    }

    public function test_rejecting_a_task_notifies_the_assignee(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        Notification::fake();

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'rejected');
        $this->flushDeferredNotifications();

        Notification::assertSentTo($karim, TaskRejected::class);
    }

    public function test_rejecting_a_task_from_the_board_notifies_the_assignee(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        Notification::fake();

        Livewire::actingAs($qa)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'rejected');
        $this->flushDeferredNotifications();

        Notification::assertSentTo($karim, TaskRejected::class);
    }

    public function test_marking_ready_to_deploy_notifies_the_reporter(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        Notification::fake();

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'ready_to_deploy');
        $this->flushDeferredNotifications();

        Notification::assertSentTo($rahim, TaskReadyToDeploy::class);
    }

    public function test_the_qa_reviewer_does_not_notify_themself_when_they_are_also_the_reporter(): void
    {
        $qa = $this->teamMember('Qadir');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $qa, $karim);
        $this->flushDeferredNotifications();

        Notification::fake();

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'ready_to_deploy');
        $this->flushDeferredNotifications();

        Notification::assertNothingSent();
    }

    public function test_creating_a_project_with_a_coordinator_notifies_them(): void
    {
        Notification::fake();

        $manager = $this->manager('Rahim');
        $coordinator = $this->teamMember('Karim');

        Livewire::actingAs($manager)
            ->test('projects.create')
            ->set('name', 'Website Relaunch')
            ->set('coordinator_id', $coordinator->id)
            ->call('save');
        $this->flushDeferredNotifications();

        Notification::assertSentTo($coordinator, ProjectCoordinatorAssigned::class);
    }

    public function test_assigning_a_coordinator_later_notifies_them(): void
    {
        $manager = $this->manager('Rahim');
        $coordinator = $this->teamMember('Karim');

        $project = Project::create(['name' => 'Website Relaunch', 'created_by' => $manager->id, 'status' => ProjectStatus::Active]);

        Notification::fake();

        Livewire::actingAs($manager)
            ->test('projects.show', ['project' => $project])
            ->call('saveCoordinator', $coordinator->id);
        $this->flushDeferredNotifications();

        Notification::assertSentTo($coordinator, ProjectCoordinatorAssigned::class);
    }

    public function test_changing_the_coordinator_to_yourself_does_not_notify_yourself(): void
    {
        $manager = $this->manager('Rahim');

        $project = Project::create(['name' => 'Website Relaunch', 'created_by' => $manager->id, 'status' => ProjectStatus::Active]);

        Notification::fake();

        Livewire::actingAs($manager)
            ->test('projects.show', ['project' => $project])
            ->call('saveCoordinator', $manager->id);
        $this->flushDeferredNotifications();

        Notification::assertNothingSent();
    }

    public function test_the_notification_bell_shows_the_unread_count_and_marking_one_as_read_decrements_it(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $this->flushDeferredNotifications();

        $component = Livewire::actingAs($karim)->test('notifications.bell');
        $component->assertViewHas('unreadCount', 1);

        $notificationId = $karim->notifications()->first()->id;
        $component->call('openNotification', $notificationId);

        $this->assertNotNull($karim->notifications()->find($notificationId)->read_at);
    }

    public function test_a_failed_send_is_reported_instead_of_crashing_the_deferred_flush(): void
    {
        $rahim = $this->teamMember('Rahim');

        DeferredNotification::send($rahim, new ThrowingTestNotification);

        // Flushing must not let the channel's exception escape — it's
        // caught and reported, not left to crash PHP's termination phase
        // (where nothing would otherwise be around to catch it).
        $this->flushDeferredNotifications();

        $this->assertTrue(true);
    }

    public function test_notifications_index_lists_and_can_mark_all_as_read(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $workflow->createTask(['title' => 'Task One'], $rahim, $karim);
        $workflow->createTask(['title' => 'Task Two'], $rahim, $karim);
        $this->flushDeferredNotifications();

        Livewire::actingAs($karim)
            ->test('notifications.index')
            ->assertSee('Task One')
            ->assertSee('Task Two')
            ->call('markAllAsRead');

        $this->assertSame(0, $karim->unreadNotifications()->count());
    }
}

/**
 * A minimal, deliberately-failing notification used only to prove
 * DeferredNotification::send() catches and reports a channel failure
 * instead of letting it crash PHP's termination phase. It must be a real,
 * named class (not an inline anonymous one) — the dispatched closure
 * captures it as a use() variable, and PHP cannot serialize anonymous
 * classes, which Laravel's queued-closure dispatch requires even for
 * afterResponse() delivery.
 */
class ThrowingTestNotification extends NotificationBase
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        throw new \RuntimeException('Simulated notification failure');
    }
}
