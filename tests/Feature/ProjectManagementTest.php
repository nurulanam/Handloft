<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
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

    public function test_manager_can_create_a_project(): void
    {
        $manager = $this->manager('Rahim');

        Livewire::actingAs($manager)
            ->test('projects.create')
            ->set('name', 'Website Relaunch')
            ->set('description', 'Full redesign of the marketing site.')
            ->call('save');

        $project = Project::where('name', 'Website Relaunch')->firstOrFail();

        $this->assertSame($manager->id, $project->created_by);
    }

    public function test_team_member_cannot_create_a_project(): void
    {
        $teamMember = $this->teamMember('Karim');

        $this->actingAs($teamMember)
            ->get(route('projects.create'))
            ->assertForbidden();
    }

    public function test_the_create_project_nav_link_is_hidden_from_users_who_cant_create_one(): void
    {
        $teamMember = $this->teamMember('Karim');

        $response = $this->actingAs($teamMember)->get(route('dashboard'));

        $response->assertDontSee('Create Project');
        $response->assertSee('All Projects');
    }

    public function test_the_create_project_nav_link_is_visible_to_a_manager(): void
    {
        $manager = $this->manager('Rahim');

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertSee('Create Project');
    }

    public function test_the_project_list_can_be_searched_filtered_and_sorted(): void
    {
        $manager = $this->manager('Rahim');
        $alpha = Project::factory()->create(['name' => 'Alpha launch', 'created_by' => $manager->id, 'deadline' => now()->addDays(20)]);
        $beta = Project::factory()->create(['name' => 'Beta rebrand', 'created_by' => $manager->id, 'deadline' => now()->addDays(3), 'status' => ProjectStatus::Completed]);
        $gamma = Project::factory()->create(['name' => 'Gamma audit', 'created_by' => $manager->id, 'deadline' => null]);

        Task::factory()->create(['created_by' => $manager->id, 'project_id' => $beta->id, 'status' => TaskStatus::Done]);
        Task::factory()->count(2)->create(['created_by' => $manager->id, 'project_id' => $alpha->id, 'status' => TaskStatus::Todo]);

        $names = fn ($projects) => $projects->pluck('name')->all();

        Livewire::actingAs($manager)->test('projects.index')
            ->set('search', 'rebrand')
            ->assertViewHas('projects', fn ($p) => $names($p) === ['Beta rebrand'])
            ->set('search', '')
            ->set('status', ProjectStatus::Completed->value)
            ->assertViewHas('projects', fn ($p) => $names($p) === ['Beta rebrand'])
            ->call('clearFilters')
            ->set('sort', 'deadline')
            ->assertViewHas('projects', fn ($p) => $names($p) === ['Beta rebrand', 'Alpha launch', 'Gamma audit'])
            ->set('sort', 'progress-desc')
            ->assertViewHas('projects', fn ($p) => $names($p)[0] === 'Beta rebrand')
            ->set('sort', 'name')
            ->assertViewHas('projects', fn ($p) => $names($p) === ['Alpha launch', 'Beta rebrand', 'Gamma audit'])
            ->assertViewHas('statusCounts', fn ($counts) => (int) $counts['active'] === 2 && (int) $counts['completed'] === 1);
    }

    public function test_anyone_can_view_the_project_list_and_detail(): void
    {
        $teamMember = $this->teamMember('Karim');
        $manager = $this->manager('Rahim');
        $project = Project::factory()->create(['created_by' => $manager->id]);

        $this->actingAs($teamMember)->get(route('projects.index'))->assertOk();
        $this->actingAs($teamMember)->get(route('projects.show', $project))->assertOk();
    }

    public function test_project_progress_reflects_completed_tasks(): void
    {
        $manager = $this->manager('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');
        $project = Project::factory()->create(['created_by' => $manager->id]);

        $workflow = app(TaskWorkflowService::class);
        $taskOne = $workflow->createTask(['title' => 'Task One', 'project_id' => $project->id, 'qa_id' => $qa->id], $manager, $karim);
        $workflow->createTask(['title' => 'Task Two', 'project_id' => $project->id], $manager, $karim);

        $workflow->submitForQa($taskOne, $karim, 2.0);
        $taskOne->update(['status' => TaskStatus::ReadyToDeploy]);
        $workflow->markDone($taskOne, $manager);

        $project->refresh();

        $this->assertSame(2, $project->task_count);
        $this->assertSame(1, $project->completed_count);
        $this->assertSame(50, $project->progress_percent);
    }

    public function test_tasks_index_can_be_scoped_to_a_project(): void
    {
        $manager = $this->manager('Rahim');
        $karim = $this->teamMember('Karim');
        $projectA = Project::factory()->create(['created_by' => $manager->id]);
        $projectB = Project::factory()->create(['created_by' => $manager->id]);

        $workflow = app(TaskWorkflowService::class);
        $inProjectA = $workflow->createTask(['title' => 'In Project A', 'project_id' => $projectA->id], $manager, $karim);
        $workflow->createTask(['title' => 'In Project B', 'project_id' => $projectB->id], $manager, $karim);

        $component = Livewire::actingAs($manager)
            ->test('tasks.index', ['projectId' => $projectA->id])
            ->set('tab', 'all');

        $board = $component->viewData('board');
        $allTaskIds = collect($board)->flatten()->pluck('id');

        $this->assertTrue($allTaskIds->contains($inProjectA->id));
        $this->assertCount(1, $allTaskIds);
    }

    public function test_subtask_inherits_parent_tasks_project(): void
    {
        $manager = $this->manager('Rahim');
        $karim = $this->teamMember('Karim');
        $project = Project::factory()->create(['created_by' => $manager->id]);

        $workflow = app(TaskWorkflowService::class);
        $parent = $workflow->createTask(['title' => 'Parent Task', 'project_id' => $project->id], $manager, $karim);

        Livewire::actingAs($manager)
            ->test('tasks.show', ['task' => $parent])
            ->set('subtask_title', 'Child Task')
            ->set('subtask_assigned_to', (string) $karim->id)
            ->call('addSubtask');

        $child = Task::where('title', 'Child Task')->firstOrFail();

        $this->assertSame($project->id, $child->project_id);
        $this->assertSame($parent->id, $child->parent_task_id);
    }

    public function test_only_manage_projects_or_creator_can_update_a_project(): void
    {
        $manager = $this->manager('Rahim');
        $creatorTeamMember = $this->teamMember('Karim');
        $otherTeamMember = $this->teamMember('Hasan');

        $project = Project::factory()->create(['created_by' => $creatorTeamMember->id]);

        $this->assertTrue($creatorTeamMember->can('update', $project));
        $this->assertTrue($manager->can('update', $project));
        $this->assertFalse($otherTeamMember->can('update', $project));
    }
}
