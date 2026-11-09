<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskRepeat;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskRepeated;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RepeatingTaskTest extends TestCase
{
    use RefreshDatabase;

    private function next(TaskRepeat $type, string $from, int $every = 1, ?array $on = null): string
    {
        return $type->nextAfter(Carbon::parse($from), $every, $on)->toDateString();
    }

    public function test_next_run_dates(): void
    {
        // 2026-11-09 is a Monday.
        $this->assertSame('2026-11-11', $this->next(TaskRepeat::Daily, '2026-11-09', 2));
        $this->assertSame('2026-11-16', $this->next(TaskRepeat::Weekly, '2026-11-09'));
        $this->assertSame('2026-11-11', $this->next(TaskRepeat::Weekly, '2026-11-09', 1, [1, 3, 5])); // Mon done → Wed
        $this->assertSame('2026-11-23', $this->next(TaskRepeat::Weekly, '2026-11-13', 2, [1, 3, 5])); // Fri done, every 2 weeks → Mon
        $this->assertSame('2026-11-15', $this->next(TaskRepeat::Monthly, '2026-11-09', 1, [15]));
        $this->assertSame('2026-12-15', $this->next(TaskRepeat::Monthly, '2026-11-20', 1, [15]));
        $this->assertSame('2027-02-28', $this->next(TaskRepeat::Monthly, '2026-12-31', 2, [31])); // short month → last day
        $this->assertSame('2026-12-30', $this->next(TaskRepeat::Monthly, '2026-11-30'));
    }

    public function test_finished_task_returns_to_todo_when_due_and_shifts_its_dates(): void
    {
        Notification::fake();
        $this->seed(RoleAndAdminSeeder::class);
        $admin = User::role(Role::SuperAdmin->value)->first();
        $worker = User::factory()->create();
        $task = Task::factory()->create(['repeat' => TaskRepeat::Weekly, 'status' => TaskStatus::Done, 'start_date' => '2026-11-01', 'deadline' => '2026-11-05']);
        $task->assignments()->create(['assigned_by' => $admin->id, 'assigned_to' => $worker->id, 'assigned_at' => now()]);
        $this->assertNotNull($task->fresh()->repeat_next_at);

        $this->artisan('tasks:reopen-repeating');
        $this->assertSame(TaskStatus::Done, $task->fresh()->status); // not due yet

        $this->travel(8)->days();
        $this->artisan('tasks:reopen-repeating');

        $task->refresh();
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame(1, $task->repeat_count);
        Notification::assertSentTo($worker, TaskRepeated::class);
        $this->assertEquals(4, $task->start_date->diffInDays($task->deadline)); // window length kept
    }

    public function test_series_ends_at_the_repeat_limit_and_the_end_date(): void
    {
        $limited = Task::factory()->create(['repeat' => TaskRepeat::Daily, 'repeat_max' => 1, 'repeat_count' => 1, 'status' => TaskStatus::Done]);
        $this->assertNull($limited->fresh()->repeat_next_at);

        $ended = Task::factory()->create(['repeat' => TaskRepeat::Weekly, 'repeat_until' => now()->addDays(3), 'status' => TaskStatus::Done]);
        $this->assertNull($ended->fresh()->repeat_next_at);
    }

    public function test_done_task_without_repeat_stays_done(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Done]);

        $this->travel(40)->days();
        $this->artisan('tasks:reopen-repeating');

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_repeat_rules_can_be_saved_on_a_task(): void
    {
        $this->seed(RoleAndAdminSeeder::class);
        $admin = User::role(Role::SuperAdmin->value)->first();
        $task = Task::factory()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)->test('tasks.show', ['task' => $task])
            ->set('repeat', 'weekly')
            ->set('repeat_every', '2')
            ->set('repeat_on', ['1', '3'])
            ->set('repeat_max', '5')
            ->call('saveRepeat')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertSame(TaskRepeat::Weekly, $task->repeat);
        $this->assertSame([1, 3], $task->repeat_on);
        $this->assertSame('Every 2 weeks on Mon, Wed · 5 left', $task->repeat_summary);
    }
}
