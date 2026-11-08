<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Services\TaskWorkflowService;
use Livewire\Livewire;

class QaQueueTest extends \Tests\TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleAndAdminSeeder::class);
    }

    private function teamMember(string $name): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(\App\Enums\Role::TeamMember->value);

        return $user;
    }

    public function test_reviewer_still_sees_a_rejected_task_in_assigned_to_me(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'status' => TaskStatus::Rejected, 'qa_id' => $qa->id], $rahim, $karim);

        Livewire::actingAs($qa)
            ->test('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list'])
            ->assertSee('Website Audit');
    }
}
