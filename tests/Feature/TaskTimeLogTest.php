<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskTimeLogTest extends TestCase
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

    private function createTask(User $creator, User $assignee): Task
    {
        return app(TaskWorkflowService::class)->createTask(['title' => 'Multi-day task'], $creator, $assignee);
    }

    public function test_assignee_can_log_daily_time_and_total_is_summed(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '3')
            ->set('log_minutes', '0')
            ->call('logTime')
            ->assertHasNoErrors();

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-28')
            ->set('log_hours', '2')
            ->set('log_minutes', '30')
            ->call('logTime')
            ->assertHasNoErrors();

        $this->assertSame(2, TaskTimeLog::where('task_id', $task->id)->count());
        $this->assertEquals(5.5, $task->fresh()->total_logged_hours);
    }

    public function test_logging_time_twice_for_the_same_day_updates_that_entry_instead_of_adding_a_second_one(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '3')
            ->set('log_minutes', '0')
            ->call('logTime');

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '4')
            ->set('log_minutes', '0')
            ->call('logTime');

        $this->assertSame(1, TaskTimeLog::where('task_id', $task->id)->count());
        $this->assertEquals(4, TaskTimeLog::where('task_id', $task->id)->first()->hours);
    }

    public function test_re_logging_the_same_day_does_not_add_a_duplicate_activity_timeline_entry(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '3')
            ->set('log_minutes', '0')
            ->call('logTime');

        $countAfterFirstLog = $task->activities()->count();

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '4')
            ->set('log_minutes', '0')
            ->call('logTime');

        $this->assertSame($countAfterFirstLog, $task->activities()->count());
    }

    public function test_hours_must_be_within_range(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '30')
            ->call('logTime')
            ->assertHasErrors(['log_hours' => 'max']);
    }

    public function test_minutes_must_be_within_range(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '1')
            ->set('log_minutes', '70')
            ->call('logTime')
            ->assertHasErrors(['log_minutes' => 'max']);
    }

    public function test_zero_hours_and_minutes_is_rejected(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '0')
            ->set('log_minutes', '0')
            ->call('logTime')
            ->assertHasErrors('log_hours');

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_hours_and_minutes_combine_into_the_correct_decimal_total(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '5')
            ->set('log_minutes', '30')
            ->call('logTime')
            ->assertHasNoErrors();

        $this->assertEquals(5.5, TaskTimeLog::where('task_id', $task->id)->first()->hours);
    }

    public function test_future_dates_are_rejected(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', now()->addDay()->toDateString())
            ->set('log_hours', '2')
            ->call('logTime')
            ->assertHasErrors(['log_date' => 'before_or_equal']);
    }

    public function test_dates_before_the_tasks_start_date_are_rejected(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);
        $task->update(['start_date' => now()->subDays(3)]);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', now()->subDays(5)->toDateString())
            ->set('log_hours', '2')
            ->call('logTime')
            ->assertHasErrors(['log_date' => 'after_or_equal']);

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_the_tasks_start_date_itself_is_a_valid_log_date(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);
        $task->update(['start_date' => now()->subDays(3)]);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', now()->subDays(3)->toDateString())
            ->set('log_hours', '2')
            ->call('logTime')
            ->assertHasNoErrors();

        $this->assertSame(1, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_the_qa_reviewer_can_log_their_own_testing_time_once_it_reaches_qa_testing(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');
        $task = $this->createTask($rahim, $karim);
        $task->update(['qa_id' => $hasan->id, 'status' => TaskStatus::QaTesting]);

        Livewire::actingAs($hasan)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '1')
            ->set('log_minutes', '30')
            ->call('logTime')
            ->assertHasNoErrors();

        $this->assertSame(1, TaskTimeLog::where('task_id', $task->id)->where('user_id', $hasan->id)->count());
    }

    public function test_the_qa_reviewer_cannot_log_time_before_the_task_reaches_qa_testing(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');
        $task = $this->createTask($rahim, $karim);
        $task->update(['qa_id' => $hasan->id, 'status' => TaskStatus::InProgress]);

        Livewire::actingAs($hasan)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '1')
            ->set('log_minutes', '30')
            ->call('logTime')
            ->assertForbidden();

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_the_reporter_can_log_their_own_time_once_it_reaches_ready_to_deploy(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);
        $task->update(['status' => TaskStatus::ReadyToDeploy]);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '1')
            ->call('logTime')
            ->assertHasNoErrors();

        $this->assertSame(1, TaskTimeLog::where('task_id', $task->id)->where('user_id', $rahim->id)->count());
    }

    public function test_the_reporter_cannot_log_time_before_the_task_reaches_ready_to_deploy(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);
        $task->update(['status' => TaskStatus::QaTesting]);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '1')
            ->call('logTime')
            ->assertForbidden();

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_an_uninvolved_viewer_cannot_log_time_on_someone_elses_task(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);
        // A Manager can view any task (view-all-tasks) but isn't the
        // assignee, reporter, or QA/Reviewer on this one, and doesn't hold
        // reassign-task, so they're not "connected" to it either.
        $unrelatedManager = $this->manager('Nasrin');

        Livewire::actingAs($unrelatedManager)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '2')
            ->call('logTime')
            ->assertForbidden();

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_even_an_admin_cannot_log_time_on_a_task_theyre_not_connected_to(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);
        // A SuperAdmin has every permission, including reassign-task, but
        // there is no permission-based override for logging time — only
        // being the assignee, Reporter, or QA/Reviewer grants that.
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '2')
            ->call('logTime')
            ->assertForbidden();

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_the_reporter_cannot_log_time_on_behalf_of_the_assignee_before_ready_to_deploy_even_as_creator(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('log_date', '2026-09-27')
            ->set('log_hours', '2')
            ->call('logTime')
            ->assertForbidden();

        $this->assertSame(0, TaskTimeLog::where('task_id', $task->id)->count());
    }

    public function test_entry_owner_can_delete_their_own_time_log(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $task = $this->createTask($rahim, $karim);

        $log = app(TaskWorkflowService::class)->logTime($task, $karim, now()->subDay(), 2.0);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteTimeLog', $log->id);

        $this->assertSame(0, TaskTimeLog::where('id', $log->id)->count());
    }

    public function test_unrelated_team_member_cannot_delete_someone_elses_time_log(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');
        $task = $this->createTask($rahim, $karim);
        $task->update(['qa_id' => $hasan->id]);

        $log = app(TaskWorkflowService::class)->logTime($task, $karim, now()->subDay(), 2.0);

        Livewire::actingAs($hasan)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteTimeLog', $log->id)
            ->assertForbidden();

        $this->assertSame(1, TaskTimeLog::where('id', $log->id)->count());
    }
}
