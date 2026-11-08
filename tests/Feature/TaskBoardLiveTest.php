<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Events\TasksChanged;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskBoardLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_a_task_broadcasts_to_open_boards(): void
    {
        Event::fake([TasksChanged::class]);

        $task = Task::factory()->create();
        $task->update(['status' => TaskStatus::InProgress]);

        Event::assertDispatched(TasksChanged::class, fn ($e) => $e->taskId === $task->id);
    }

    public function test_the_board_shows_changes_made_elsewhere_after_a_tasks_changed_event(): void
    {
        $this->seed(\Database\Seeders\RoleAndAdminSeeder::class);
        $admin = \App\Models\User::role(\App\Enums\Role::SuperAdmin->value)->first() ?? \App\Models\User::factory()->create();
        $task = Task::factory()->create(['title' => 'Moving Card', 'status' => TaskStatus::Todo]);

        $component = \Livewire\Livewire::actingAs($admin)->test('tasks.index')->assertSee('Moving Card');

        $task->update(['title' => 'Renamed Card']);

        $component->dispatch('tasks-changed')->assertSee('Renamed Card');
    }
}
