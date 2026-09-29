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

    public function test_assignee_can_move_todo_task_to_in_progress(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'in_progress')
            ->call('saveStatus');

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_non_assignee_cannot_move_todo_task_to_in_progress(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        // Rahim created the task (so he can view it) but Karim is the current
        // assignee, so Rahim must not be able to start work on it himself.
        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'in_progress')
            ->call('saveStatus')
            ->assertForbidden();

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    public function test_moving_to_qa_testing_requires_a_qa_reviewer_assigned(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::InProgress], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'qa_testing')
            ->call('saveStatus')
            ->assertDispatched('notify');

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_assignee_moving_to_qa_testing_logs_hours_and_creates_work_history(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List', 'status' => TaskStatus::InProgress, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'qa_testing')
            ->call('saveStatus')
            ->assertSet('showSubmitQaModal', true)
            ->set('actual_hours', '3.5')
            ->call('submitForQa');

        $task->refresh();

        $this->assertSame(TaskStatus::QaTesting, $task->status);
        $this->assertNotNull($task->workHistory);
        $this->assertSame($karim->id, $task->workHistory->user_id);
        $this->assertSame($rahim->id, $task->workHistory->assigned_by);
        $this->assertEquals(3.5, $task->workHistory->actual_hours);
    }

    public function test_qa_reviewer_can_reject_from_qa_testing(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'rejected')
            ->call('saveStatus');

        $this->assertSame(TaskStatus::Rejected, $task->fresh()->status);
    }

    public function test_qa_reviewer_can_mark_ready_to_deploy(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'ready_to_deploy')
            ->call('saveStatus');

        $this->assertSame(TaskStatus::ReadyToDeploy, $task->fresh()->status);
    }

    public function test_non_reviewer_cannot_approve_or_reject_qa_testing(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        // Karim is the assignee, not the QA/Reviewer, so he cannot approve/reject.
        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'ready_to_deploy')
            ->call('saveStatus')
            ->assertForbidden();

        $this->assertSame(TaskStatus::QaTesting, $task->fresh()->status);
    }

    public function test_assignee_can_resubmit_a_rejected_task_to_qa_testing_updating_hours(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::InProgress, 'qa_id' => $qa->id], $rahim, $karim);
        $workflow->submitForQa($task, $karim, 3.0);
        $task->update(['status' => TaskStatus::Rejected]);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'qa_testing')
            ->call('saveStatus')
            ->set('actual_hours', '5')
            ->call('submitForQa');

        $task->refresh();

        $this->assertSame(TaskStatus::QaTesting, $task->status);
        $this->assertSame(1, $task->workHistory()->count());
        $this->assertEquals(5.0, $task->workHistory->actual_hours);
    }

    public function test_qa_reviewer_can_correct_a_rejected_task_directly_to_ready_to_deploy(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::Rejected, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'ready_to_deploy')
            ->call('saveStatus');

        $this->assertSame(TaskStatus::ReadyToDeploy, $task->fresh()->status);
    }

    public function test_qa_reviewer_can_correct_a_ready_to_deploy_task_directly_to_rejected(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::ReadyToDeploy, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($qa)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'rejected')
            ->call('saveStatus');

        $this->assertSame(TaskStatus::Rejected, $task->fresh()->status);
    }

    public function test_assignee_cannot_correct_rejected_directly_to_ready_to_deploy(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::Rejected, 'qa_id' => $qa->id], $rahim, $karim);

        // Only the QA/Reviewer may skip straight to Ready to Deploy; the
        // assignee must go through QA testing again.
        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'ready_to_deploy')
            ->call('saveStatus')
            ->assertForbidden();

        $this->assertSame(TaskStatus::Rejected, $task->fresh()->status);
    }

    public function test_assigned_to_me_tab_surfaces_qa_testing_tasks_to_the_reviewer(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Needs Review', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        $component = Livewire::actingAs($qa)
            ->test('tasks.index')
            ->set('tab', 'assigned-to-me');

        $board = $component->viewData('board');
        $taskIds = collect($board)->flatten()->pluck('id');

        $this->assertTrue($taskIds->contains($task->id));
    }

    public function test_assigned_to_me_tab_surfaces_ready_to_deploy_tasks_to_the_reporter(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Needs Sign-off', 'status' => TaskStatus::ReadyToDeploy], $rahim, $karim);

        $component = Livewire::actingAs($rahim)
            ->test('tasks.index')
            ->set('tab', 'assigned-to-me');

        $board = $component->viewData('board');
        $taskIds = collect($board)->flatten()->pluck('id');

        $this->assertTrue($taskIds->contains($task->id));
    }

    public function test_reporter_can_mark_a_ready_to_deploy_task_as_done(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::ReadyToDeploy], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'done')
            ->call('saveStatus');

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_non_reporter_cannot_mark_a_task_done(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::ReadyToDeploy], $rahim, $karim);

        // Karim is the assignee, not the Reporter, so he cannot close it out.
        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('status_value', 'done')
            ->call('saveStatus')
            ->assertForbidden();

        $this->assertSame(TaskStatus::ReadyToDeploy, $task->fresh()->status);
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
            ->set('assignee_value', (string) $hasan->id)
            ->call('saveAssignee')
            ->assertForbidden();
    }

    public function test_assignee_dragging_a_kanban_card_to_in_progress_updates_status_and_logs_activity(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'in_progress');

        $task->refresh();

        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertTrue($task->activities()->where('type', 'status_changed')->exists());
    }

    public function test_non_assignee_dragging_a_kanban_card_to_in_progress_is_a_no_op(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'in_progress');

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    public function test_dragging_a_card_to_qa_testing_without_a_reviewer_shows_a_notification(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::InProgress], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'qa_testing')
            ->assertSet('submittingTaskId', null)
            ->assertDispatched('notify');

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_dragging_a_card_to_qa_testing_opens_the_hours_modal_in_place(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::InProgress, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'qa_testing')
            ->assertSet('submittingTaskId', $task->id)
            ->assertNoRedirect();

        $task->refresh();

        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertNull($task->workHistory);
    }

    public function test_submitting_the_qa_testing_modal_from_the_board_creates_work_history(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::InProgress, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'qa_testing')
            ->set('actual_hours', '2.5')
            ->call('submitForQa')
            ->assertSet('submittingTaskId', null);

        $task->refresh();

        $this->assertSame(TaskStatus::QaTesting, $task->status);
        $this->assertNotNull($task->workHistory);
        $this->assertEquals(2.5, $task->workHistory->actual_hours);
    }

    public function test_qa_reviewer_dragging_a_card_from_qa_testing_to_ready_to_deploy(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::QaTesting, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($qa)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'ready_to_deploy');

        $this->assertSame(TaskStatus::ReadyToDeploy, $task->fresh()->status);
    }

    public function test_reporter_dragging_a_card_from_ready_to_deploy_to_done(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'status' => TaskStatus::ReadyToDeploy], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.index')
            ->call('moveTask', $task->id, 'done');

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_all_tasks_tab_only_shows_tasks_the_team_member_is_connected_to(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $ownTask = $workflow->createTask(['title' => 'My Task'], $rahim, $karim);
        $unrelatedTask = $workflow->createTask(['title' => 'Unrelated Task'], $hasan, $hasan);

        $component = Livewire::actingAs($karim)
            ->test('tasks.index')
            ->set('tab', 'all');

        $taskIds = collect($component->viewData('board'))->flatten()->pluck('id');

        $this->assertTrue($taskIds->contains($ownTask->id));
        $this->assertFalse($taskIds->contains($unrelatedTask->id));
    }

    public function test_all_tasks_tab_shows_every_task_to_a_view_all_tasks_holder(): void
    {
        $manager = $this->manager('Manager Mike');
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $unrelatedTask = $workflow->createTask(['title' => 'Unrelated Task'], $rahim, $karim);

        $component = Livewire::actingAs($manager)
            ->test('tasks.index')
            ->set('tab', 'all');

        $taskIds = collect($component->viewData('board'))->flatten()->pluck('id');

        $this->assertTrue($taskIds->contains($unrelatedTask->id));
    }

    public function test_board_card_shows_a_sub_pill_for_subtasks_and_role_pills_for_the_viewer(): void
    {
        $rahim = $this->teamMember('Rahim');

        // Rahim both creates and self-assigns the parent, so his own board
        // card shows both the "Reporter" and "Assignee" role pills at once.
        $workflow = app(TaskWorkflowService::class);
        $parent = $workflow->createTask(['title' => 'Parent Task'], $rahim, $rahim);
        $workflow->createTask(['title' => 'Child Task', 'parent_task_id' => $parent->id], $rahim, $rahim);

        Livewire::actingAs($rahim)
            ->test('tasks.index')
            ->set('tab', 'all')
            ->assertSee("SUB-{$parent->id}")
            ->assertSee('Assignee')
            ->assertSee('Reporter');
    }

    private function manager(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::Manager->value);

        return $user;
    }
}
