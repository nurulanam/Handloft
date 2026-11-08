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

    public function test_each_board_column_starts_with_five_cards_and_loads_more_on_demand(): void
    {
        $this->seed(\Database\Seeders\RoleAndAdminSeeder::class);
        $admin = \App\Models\User::role(\App\Enums\Role::SuperAdmin->value)->first() ?? \App\Models\User::factory()->create();

        // Card 20 is the most recently touched, card 1 the oldest.
        foreach (range(1, 20) as $i) {
            Task::factory()->create(['title' => sprintf('Card-%02d', $i), 'status' => TaskStatus::Todo, 'updated_at' => now()->subMinutes(30 - $i)]);
        }

        $component = \Livewire\Livewire::actingAs($admin)->test('tasks.index')
            ->assertSee('Card-20')
            ->assertSee('Card-16')
            ->assertDontSee('Card-15')
            ->assertSee('Load more');

        $component->call('loadMoreInLane', 'todo')
            ->assertSee('Card-06')
            ->assertDontSee('Card-05')
            ->assertSee('Load more');

        $component->call('loadMoreInLane', 'todo')->assertSee('Card-01')->assertDontSee('Load more');
    }

    public function test_the_list_loads_more_rows_on_demand(): void
    {
        $this->seed(\Database\Seeders\RoleAndAdminSeeder::class);
        $admin = \App\Models\User::role(\App\Enums\Role::SuperAdmin->value)->first() ?? \App\Models\User::factory()->create();

        Task::factory()->count(25)->create();

        $component = \Livewire\Livewire::actingAs($admin)->test('tasks.index', ['view' => 'list'])
            ->assertSee('Showing 10 of 25');

        $component->call('loadMoreInList')->assertSee('Showing 20 of 25');
        $component->call('loadMoreInList')->assertDontSee('Load more');
    }
}
