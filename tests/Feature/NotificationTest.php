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
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_creating_a_task_notifies_the_assignee(): void
    {
        Notification::fake();

        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask(['title' => 'Create Client List'], $rahim, $karim);

        Notification::assertSentTo($karim, TaskAssigned::class);
        Notification::assertNotSentTo($rahim, TaskAssigned::class);
    }

    public function test_self_assigning_a_task_does_not_notify_yourself(): void
    {
        Notification::fake();

        $rahim = $this->teamMember('Rahim');

        app(TaskWorkflowService::class)->createTask(['title' => 'Create Client List'], $rahim, $rahim);

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

        Notification::assertSentTo($rahim, TaskReadyToDeploy::class);
    }

    public function test_the_qa_reviewer_does_not_notify_themself_when_they_are_also_the_reporter(): void
    {
        $qa = $this->teamMember('Qadir');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $qa, $karim);

        Notification::fake();

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'ready_to_deploy');

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

        Notification::assertNothingSent();
    }

    public function test_the_notification_bell_shows_the_unread_count_and_marking_one_as_read_decrements_it(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        $component = Livewire::actingAs($karim)->test('notifications.bell');
        $component->assertViewHas('unreadCount', 1);

        $notificationId = $karim->notifications()->first()->id;
        $component->call('openNotification', $notificationId);

        $this->assertNotNull($karim->notifications()->find($notificationId)->read_at);
    }

    public function test_notifications_index_lists_and_can_mark_all_as_read(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $workflow->createTask(['title' => 'Task One'], $rahim, $karim);
        $workflow->createTask(['title' => 'Task Two'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('notifications.index')
            ->assertSee('Task One')
            ->assertSee('Task Two')
            ->call('markAllAsRead');

        $this->assertSame(0, $karim->unreadNotifications()->count());
    }
}
