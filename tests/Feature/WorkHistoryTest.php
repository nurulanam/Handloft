<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class WorkHistoryTest extends TestCase
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

    public function test_non_admin_cannot_edit_completed_hours(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $history = $workflow->completeTask($task, $karim, 3.5);

        Livewire::actingAs($karim)
            ->test('work-history.index')
            ->call('startEdit', $history->id)
            ->assertForbidden();
    }

    public function test_admin_edit_persists_and_is_audit_logged(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $history = $workflow->completeTask($task, $karim, 3.5);

        Livewire::actingAs($admin)
            ->test('work-history.index')
            ->call('startEdit', $history->id)
            ->set('edit_hours', '4')
            ->set('edit_reason', 'Incorrect time entry')
            ->call('saveEdit');

        $history->refresh();

        $this->assertEquals(4.0, $history->actual_hours);

        $activity = Activity::query()->where('description', 'Completed hours edited')->latest()->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('3.50', $activity->properties['old_hours']);
        $this->assertSame('4', $activity->properties['new_hours']);
        $this->assertSame('Incorrect time entry', $activity->properties['reason']);
    }
}
