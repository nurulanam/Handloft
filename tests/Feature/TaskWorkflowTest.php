<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
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

    public function test_creating_a_task_records_creator_and_initial_assignment(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($rahim)
            ->test('tasks.create')
            ->set('title', 'Create Client List')
            ->set('assigned_to', $karim->id)
            ->set('priority', 'medium')
            ->call('save');

        $task = Task::firstOrFail();

        $this->assertSame($rahim->id, $task->created_by);
        $this->assertSame($karim->id, $task->currentAssignee()->id);
        $this->assertSame(1, $task->assignments()->count());
        $this->assertSame(2, $task->activities()->count());
    }

    public function test_reassignment_preserves_full_history_across_three_hops(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        // Only Admin has reassign-task permission by default (SRS §41).
        $workflow->reassignTask($task, $hasan, $admin);
        $workflow->reassignTask($task, $rahim, $admin);

        $task->refresh();

        $this->assertSame(3, $task->assignments()->count());
        $this->assertSame($rahim->id, $task->currentAssignee()->id);

        // None of the historical rows were overwritten.
        $history = $task->assignments()->orderBy('assigned_at')->pluck('assigned_to')->all();
        $this->assertSame([$karim->id, $hasan->id, $rahim->id], $history);

        $this->assertSame(4, $task->activities()->count());
    }

    public function test_completing_a_task_automatically_creates_work_history(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('actual_hours', '3.5')
            ->call('complete');

        $task->refresh();

        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->workHistory);
        $this->assertSame($karim->id, $task->workHistory->user_id);
        $this->assertSame($rahim->id, $task->workHistory->assigned_by);
        $this->assertEquals(3.5, $task->workHistory->actual_hours);
    }

    public function test_only_current_assignee_can_complete_the_task(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        // Rahim created the task (so he can view it) but Karim is the current
        // assignee, so Rahim must not be able to complete it himself.
        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('actual_hours', '2')
            ->call('complete')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_reassign_without_permission(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('reassign_to', $hasan->id)
            ->call('reassign')
            ->assertForbidden();
    }
}
