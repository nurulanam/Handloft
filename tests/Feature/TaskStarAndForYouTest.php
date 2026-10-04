<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskStarAndForYouTest extends TestCase
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

    public function test_user_can_star_and_unstar_a_task_from_its_detail_page(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('toggleStar');

        $this->assertTrue($karim->starredTasks()->where('tasks.id', $task->id)->exists());

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('toggleStar');

        $this->assertFalse($karim->fresh()->starredTasks()->where('tasks.id', $task->id)->exists());
    }

    public function test_starred_page_only_shows_the_current_users_starred_tasks(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $taskA = $workflow->createTask(['title' => 'Task A'], $rahim, $karim);
        $taskB = $workflow->createTask(['title' => 'Task B'], $rahim, $karim);

        $karim->starredTasks()->attach($taskA->id);
        $rahim->starredTasks()->attach($taskB->id);

        $component = Livewire::actingAs($karim)->test('starred');

        $tasks = $component->viewData('tasks');

        $this->assertCount(1, $tasks);
        $this->assertSame($taskA->id, $tasks->first()->id);
    }

    public function test_starred_page_can_unstar_a_task(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Task A'], $rahim, $karim);
        $karim->starredTasks()->attach($task->id);

        Livewire::actingAs($karim)
            ->test('starred')
            ->call('toggleStar', $task->id);

        $this->assertFalse($karim->fresh()->starredTasks()->where('tasks.id', $task->id)->exists());
    }

    public function test_for_you_page_shows_active_tasks_connected_to_the_user_grouped_by_status(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $assigned = $workflow->createTask(['title' => 'My Active Work'], $rahim, $karim);
        $reviewing = $workflow->createTask(['title' => 'Needs My Review', 'status' => TaskStatus::QaTesting, 'qa_id' => $karim->id], $rahim, $qa);
        $unrelated = $workflow->createTask(['title' => 'Not Mine'], $rahim, $qa);

        $component = Livewire::actingAs($karim)->test('for-you');

        $sections = $component->viewData('sections');
        $allIds = $sections->flatMap(fn ($s) => $s['tasks'])->pluck('id');

        $this->assertTrue($allIds->contains($assigned->id));
        $this->assertTrue($allIds->contains($reviewing->id));
        $this->assertFalse($allIds->contains($unrelated->id));
    }

    public function test_for_you_page_excludes_done_tasks(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $done = $workflow->createTask(['title' => 'Finished Work', 'status' => TaskStatus::Done], $rahim, $karim);

        $component = Livewire::actingAs($karim)->test('for-you');

        $sections = $component->viewData('sections');
        $allIds = $sections->flatMap(fn ($s) => $s['tasks'])->pluck('id');

        $this->assertFalse($allIds->contains($done->id));
    }

    public function test_a_section_only_shows_five_cards_until_load_more_is_clicked(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        collect(range(1, 7))->each(
            fn (int $i) => $workflow->createTask(['title' => "Todo task {$i}"], $rahim, $karim)
        );

        $component = Livewire::actingAs($karim)->test('for-you');

        $todoSection = $component->viewData('sections')->firstWhere('status', TaskStatus::Todo);
        $this->assertCount(7, $todoSection['tasks']);
        $this->assertCount(5, $todoSection['visibleTasks']);
        $this->assertTrue($todoSection['hasMore']);

        $component->call('loadMore', TaskStatus::Todo->value);

        $todoSection = $component->viewData('sections')->firstWhere('status', TaskStatus::Todo);
        $this->assertCount(7, $todoSection['visibleTasks']);
        $this->assertFalse($todoSection['hasMore']);
    }
}
