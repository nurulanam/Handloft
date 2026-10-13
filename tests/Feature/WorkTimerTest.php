<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\AppSetting;
use App\Models\Task;
use App\Models\TaskTimer;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WorkTimerTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    private User $assignee;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);

        $this->reporter = $this->member('Rahim');
        $this->assignee = $this->member('Karim');
        $this->reviewer = $this->member('Hasan');
    }

    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    private function task(string $title = 'Ship it', TaskStatus $status = TaskStatus::Todo): Task
    {
        $task = app(TaskWorkflowService::class)->createTask(['title' => $title, 'qa_id' => $this->reviewer->id], $this->reporter, $this->assignee);
        $task->update(['status' => $status]);

        return $task;
    }

    private function hoursLogged(Task $task, ?string $date = null): float
    {
        return (float) $task->timeLogs()
            ->where('user_id', $this->assignee->id)
            ->when($date, fn ($q) => $q->whereDate('logged_date', $date))
            ->sum('hours');
    }

    // Submit for QA: log time from the window

    public function test_the_qa_window_asks_for_time_when_the_assignee_has_none_and_logs_it_with_the_submission(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        Livewire::actingAs($this->assignee)->test('tasks.index')
            ->call('moveTask', $task->id, 'qa_testing')
            ->assertSee('logged any time on this task yet')
            ->set('qa_log_hours', '1')
            ->set('qa_log_minutes', '30')
            ->call('submitForQa')
            ->assertHasNoErrors()
            ->assertSet('submittingTaskId', null);

        $task->refresh();
        $this->assertSame(TaskStatus::QaTesting, $task->status);
        $this->assertSame(1.5, $this->hoursLogged($task, today()->toDateString()));
        $this->assertSame('1.50', (string) $task->workHistory->actual_hours);
    }

    public function test_the_qa_window_can_still_be_submitted_without_time(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'qa_testing')
            ->assertSet('showSubmitQaModal', true)
            ->call('submitForQa')
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::QaTesting, $task->fresh()->status);
        $this->assertSame(0.0, $this->hoursLogged($task));
    }

    public function test_the_qa_window_hides_the_time_fields_once_the_assignee_has_logged_time(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);
        app(TaskWorkflowService::class)->logTime($task, $this->assignee, today(), 2);

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'qa_testing')
            ->assertDontSee('logged any time on this task yet')
            ->assertSee('Time logged so far')
            // Ignored: the fields aren't offered.
            ->set('qa_log_hours', '5')
            ->call('submitForQa');

        $this->assertSame(2.0, $this->hoursLogged($task));
    }

    public function test_the_qa_window_validates_the_time_entered(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'qa_testing')
            ->set('qa_log_hours', '24')
            ->set('qa_log_minutes', '30')
            ->call('submitForQa')
            ->assertHasErrors('qa_log_hours')
            ->assertSet('showSubmitQaModal', true);

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_submitting_for_qa_stops_the_assignees_running_timer_and_counts_it(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])->call('startTimer');
        $this->travel(45)->minutes();

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->call('saveStatus', 'qa_testing')
            ->assertSee('Your timer is running')
            ->assertDontSee('logged any time on this task yet')
            ->call('submitForQa')
            ->assertDispatched('timer-changed');

        $this->assertDatabaseCount('task_timers', 0);
        $this->assertSame(0.75, $this->hoursLogged($task));
        $this->assertSame('0.75', (string) $task->fresh()->workHistory->actual_hours);
    }

    // The work timer

    public function test_the_assignee_starts_and_stops_a_timer_and_the_time_is_logged(): void
    {
        $task = $this->task();

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->assertSee('Start working')
            ->call('startTimer')
            ->assertDispatched('timer-changed')
            ->assertSee('Working');

        // Starting work on a To Do task moves it to In Progress.
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);

        $this->travel(90)->minutes();

        Livewire::actingAs($this->assignee)->test('tasks.timer')->call('stop')->assertDispatched('notify', message: 'Logged 1h 30m.');

        $this->assertDatabaseCount('task_timers', 0);
        $this->assertSame(1.5, $this->hoursLogged($task));
        $this->assertTrue($task->activities()->where('description', 'Karim logged 1h 30m with the work timer')->exists());
    }

    public function test_each_timer_session_is_its_own_entry(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);
        $workflow = app(TaskWorkflowService::class);
        $this->travelTo(Carbon::parse('2026-10-12 09:00'));
        $workflow->logTime($task, $this->assignee, today(), 2);

        $workflow->startTimer($task, $this->assignee);
        $this->travel(30)->minutes();
        $workflow->stopTimer($this->assignee->runningTimer()->first());

        $this->assertSame(2.5, $this->hoursLogged($task, '2026-10-12'));
        $this->assertSame(2, $task->timeLogs()->count());
        $this->assertSame('Work timer', $task->timeLogs()->first()->note);
    }

    public function test_a_timer_running_past_midnight_is_split_across_both_days(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);
        $workflow = app(TaskWorkflowService::class);

        $this->travelTo(Carbon::parse('2026-10-12 23:30'));
        $workflow->startTimer($task, $this->assignee);
        $this->travelTo(Carbon::parse('2026-10-13 01:00'));
        $workflow->stopTimer($this->assignee->runningTimer()->first());

        $this->assertSame(0.5, $this->hoursLogged($task, '2026-10-12'));
        $this->assertSame(1.0, $this->hoursLogged($task, '2026-10-13'));
    }

    public function test_starting_a_timer_on_another_task_stops_the_first_one(): void
    {
        $first = $this->task('First', TaskStatus::InProgress);
        $second = $this->task('Second', TaskStatus::InProgress);

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $first])->call('startTimer');
        $this->travel(20)->minutes();
        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $second])
            ->call('startTimer')
            ->assertDispatched('notify', message: "Timer started. {$first->task_key} was stopped and its time logged.");

        $this->assertSame(1, TaskTimer::count());
        $this->assertSame($second->id, $this->assignee->runningTimer()->value('task_id'));
        $this->assertSame(0.33, $this->hoursLogged($first));
    }

    public function test_under_a_minute_logs_nothing(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->call('startTimer')
            ->call('stopTimer')
            ->assertDispatched('notify', message: 'Under a minute, so nothing was logged.');

        $this->assertSame(0, $task->timeLogs()->count());
        $this->assertDatabaseCount('task_timers', 0);
    }

    public function test_someone_not_connected_to_the_task_cannot_start_a_timer(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        // The reviewer can only log time once the task reaches QA Testing.
        Livewire::actingAs($this->reviewer)->test('tasks.show', ['task' => $task])
            ->assertDontSee('Start working')
            ->call('startTimer')
            ->assertForbidden();

        $this->assertDatabaseCount('task_timers', 0);
    }

    public function test_a_forgotten_timer_is_stopped_at_the_limit_by_the_scheduler(): void
    {
        AppSetting::current()->update(['work_timer_max_hours' => 2]);
        $task = $this->task(status: TaskStatus::InProgress);
        app(TaskWorkflowService::class)->startTimer($task, $this->assignee);

        $this->travel(90)->minutes();
        $this->artisan('timers:stop-stale')->assertSuccessful();
        $this->assertDatabaseCount('task_timers', 1);

        $this->travel(5)->hours();
        $this->artisan('timers:stop-stale')->expectsOutput('Stopped 1 timer.')->assertSuccessful();

        $this->assertDatabaseCount('task_timers', 0);
        $this->assertSame(2.0, $this->hoursLogged($task));
        $this->assertTrue($task->activities()->where('description', 'Karim logged 2h with the work timer (stopped automatically after 2h)')->exists());
    }

    public function test_finishing_a_task_stops_its_timers(): void
    {
        $task = $this->task(status: TaskStatus::ReadyToDeploy);
        app(TaskWorkflowService::class)->startTimer($task, $this->assignee);
        $this->travel(10)->minutes();

        app(TaskWorkflowService::class)->markDone($task, $this->reporter);

        $this->assertDatabaseCount('task_timers', 0);
        $this->assertSame(0.17, $this->hoursLogged($task));
    }

    public function test_the_board_marks_tasks_someone_is_working_on_as_live(): void
    {
        $task = $this->task(status: TaskStatus::InProgress);

        Livewire::actingAs($this->reporter)->test('tasks.index')->assertDontSee('Karim working on this now');

        app(TaskWorkflowService::class)->startTimer($task, $this->assignee);

        Livewire::actingAs($this->reporter)->test('tasks.index')->assertSee('Karim working on this now');
        Livewire::actingAs($this->reporter)->test('tasks.show', ['task' => $task])->assertSee('Karim is working on this now');
    }

    // Settings

    public function test_turning_the_timer_off_hides_it_and_stops_running_timers(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();
        $task = $this->task(status: TaskStatus::InProgress);
        app(TaskWorkflowService::class)->startTimer($task, $this->assignee);
        $this->travel(30)->minutes();

        Livewire::actingAs($admin)->test('settings', ['tab' => 'schedule'])
            ->set('work_timer_enabled', false)
            ->call('saveSchedule')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Work schedule saved. 1 running timer stopped and logged.');

        $this->assertFalse(AppSetting::current()->work_timer_enabled);
        $this->assertDatabaseCount('task_timers', 0);
        $this->assertSame(0.5, $this->hoursLogged($task));

        Livewire::actingAs($this->assignee)->test('tasks.show', ['task' => $task])
            ->assertDontSee('Start working')
            ->call('startTimer')
            ->assertForbidden();
    }

    public function test_the_forgotten_timer_limit_is_saved_from_settings(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)->test('settings', ['tab' => 'schedule'])
            ->assertSet('work_timer_max_hours', '10')
            ->set('work_timer_max_hours', '')
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $this->assertNull(AppSetting::current()->work_timer_max_hours);
    }
}
