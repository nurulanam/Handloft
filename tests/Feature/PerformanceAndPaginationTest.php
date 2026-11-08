<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function manager(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::Manager->value);

        return $user;
    }

    public function test_projects_index_is_paginated(): void
    {
        $manager = $this->manager('Manager Mike');
        Project::factory()->count(15)->create(['created_by' => $manager->id]);

        $projects = Livewire::actingAs($manager)
            ->test('projects.index')
            ->viewData('projects');

        $this->assertSame(12, $projects->count());
        $this->assertSame(15, $projects->total());
    }

    public function test_projects_index_computes_task_counts_in_a_constant_number_of_queries(): void
    {
        $manager = $this->manager('Manager Mike');
        $teamMember = User::factory()->create(['status' => 'active']);
        $teamMember->assignRole(Role::TeamMember->value);

        foreach (range(1, 5) as $i) {
            $project = Project::factory()->create(['created_by' => $manager->id]);
            Task::factory()->count(3)->create(['created_by' => $manager->id, 'project_id' => $project->id]);
        }

        DB::enableQueryLog();

        $component = Livewire::actingAs($manager)->test('projects.index');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // A handful of fixed queries (auth, permissions, the paginated project
        // query + its count, eager-loaded creators) — never one that grows
        // per project, which is what withCount is there to prevent.
        $this->assertLessThan(10, $queryCount);

        $projects = $component->viewData('projects');
        $this->assertSame(3, $projects->first()->task_count);
    }

    public function test_kanban_board_loads_only_the_first_few_cards_per_column_but_counts_them_all(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Task::factory()->count(30)->create(['created_by' => $admin->id, 'status' => 'todo']);

        $component = Livewire::actingAs($admin)
            ->test('tasks.index')
            ->set('tab', 'all')
            ->set('view', 'board');

        $todo = $component->viewData('board')['todo'];

        $this->assertCount(5, $todo['tasks']);
        $this->assertSame(30, $todo['total']);
    }

    public function test_kanban_board_query_count_does_not_grow_with_the_number_of_tasks(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Task::factory()->count(40)->create(['created_by' => $admin->id]);

        DB::enableQueryLog();
        Livewire::actingAs($admin)->test('tasks.index')->set('tab', 'all')->set('view', 'board');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Fixed: totals + a short query (and its eager loads) per column, whatever the table holds.
        $this->assertLessThan(70, $queryCount);
    }

    public function test_starred_page_is_paginated(): void
    {
        $manager = $this->manager('Manager Mike');
        $tasks = Task::factory()->count(25)->create(['created_by' => $manager->id]);
        $manager->starredTasks()->attach($tasks->pluck('id'));

        $paginated = Livewire::actingAs($manager)
            ->test('starred')
            ->viewData('tasks');

        $this->assertSame(20, $paginated->count());
        $this->assertSame(25, $paginated->total());
    }
}
