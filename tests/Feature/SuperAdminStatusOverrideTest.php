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

class SuperAdminStatusOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@handloft.test')->firstOrFail();
    }

    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    private function task(): Task
    {
        $rahim = $this->member('Rahim');

        return app(TaskWorkflowService::class)->createTask(['title' => 'Ship it'], $rahim, $this->member('Karim'));
    }

    public function test_a_super_admin_can_move_a_task_anywhere_on_the_board_including_backwards(): void
    {
        $task = $this->task();

        Livewire::actingAs($this->admin())->test('tasks.index')->call('moveTask', $task->id, 'done')->assertNotDispatched('notify');
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertTrue($task->activities()->where('description', 'Super Admin moved this from To Do to Done (Super Admin)')->exists());

        Livewire::actingAs($this->admin())->test('tasks.index')->call('moveTask', $task->id, 'in_progress');
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_a_super_admin_can_send_a_task_to_qa_without_a_reviewer_from_the_task_page(): void
    {
        $task = $this->task();

        $page = Livewire::actingAs($this->admin())->test('tasks.show', ['task' => $task])
            ->assertViewHas('availableStatuses', fn ($statuses) => count($statuses) === count(TaskStatus::cases()) - 1);

        $page->call('saveStatus', 'qa_testing')->assertSet('showSubmitQaModal', false)->assertDispatched('notify');

        $this->assertSame(TaskStatus::QaTesting, $task->fresh()->status);
    }

    public function test_a_super_admin_whose_turn_it_is_follows_the_normal_step(): void
    {
        $admin = $this->admin();
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Mine', 'qa_id' => $this->member('Hasan')->id], $this->member('Rahim'), $admin);
        $task->update(['status' => TaskStatus::InProgress]);

        // As the assignee, submitting for QA opens the usual submission window rather than overriding.
        Livewire::actingAs($admin)->test('tasks.show', ['task' => $task])->call('saveStatus', 'qa_testing')->assertSet('showSubmitQaModal', true);
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_everyone_else_still_follows_the_workflow(): void
    {
        $task = $this->task();
        $outsider = $this->member('Nadia');
        $outsider->givePermissionTo('view-all-tasks');

        Livewire::actingAs($outsider)->test('tasks.index')->call('moveTask', $task->id, 'done')->assertDispatched('notify', type: 'error');
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }
}
