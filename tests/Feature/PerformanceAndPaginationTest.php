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

    public function test_kanban_board_caps_results_and_flags_when_truncated(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Task::factory()->count(210)->create(['created_by' => $admin->id]);

        $component = Livewire::actingAs($admin)
            ->test('tasks.index')
            ->set('tab', 'all')
            ->set('view', 'board');

        $this->assertTrue($component->viewData('boardTruncated'));
        $this->assertSame(210, $component->viewData('boardTotal'));
        $this->assertSame(200, $component->viewData('boardShown'));
        $component->assertSee('Showing the 200 most recent of 210 matching tasks');
    }

    public function test_kanban_board_does_not_flag_truncation_under_the_limit(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

        Task::factory()->count(5)->create(['created_by' => $admin->id]);

        $component = Livewire::actingAs($admin)
            ->test('tasks.index')
            ->set('tab', 'all')
            ->set('view', 'board');

        $this->assertFalse($component->viewData('boardTruncated'));
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
