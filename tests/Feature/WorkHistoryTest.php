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
        $history = $workflow->submitForQa($task, $karim, 3.5);

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
        $history = $workflow->submitForQa($task, $karim, 3.5);

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

    public function test_admin_can_compare_multiple_team_members_side_by_side(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task1 = $workflow->createTask(['title' => 'Task for Rahim'], $rahim, $rahim);
        $workflow->submitForQa($task1, $rahim, 3.0);
        $task2 = $workflow->createTask(['title' => 'Task for Karim'], $karim, $karim);
        $workflow->submitForQa($task2, $karim, 5.0);

        $component = Livewire::actingAs($admin)
            ->test('work-history.index')
            ->set('compareUserIds', [$rahim->id, $karim->id]);

        $component->assertSet('compareUserIds', [$rahim->id, $karim->id]);

        $comparison = $component->viewData('comparison');

        $this->assertCount(2, $comparison);
        $this->assertEqualsCanonicalizing(
            [$rahim->id, $karim->id],
            $comparison->pluck('user.id')->all()
        );
    }

    public function test_custom_date_range_filters_the_history_list_not_just_the_summary(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);

        $insideRangeTask = $workflow->createTask(['title' => 'Inside Range'], $rahim, $karim);
        $insideRangeHistory = $workflow->submitForQa($insideRangeTask, $karim, 2.0);
        $insideRangeHistory->update(['completed_date' => '2026-01-10']);

        $outsideRangeTask = $workflow->createTask(['title' => 'Outside Range'], $rahim, $karim);
        $outsideRangeHistory = $workflow->submitForQa($outsideRangeTask, $karim, 4.0);
        $outsideRangeHistory->update(['completed_date' => '2026-05-01']);

        $component = Livewire::actingAs($karim)
            ->test('work-history.index')
            ->set('range', 'custom')
            ->set('from', '2026-01-01')
            ->set('to', '2026-01-31');

        $histories = $component->viewData('histories');

        $this->assertCount(1, $histories);
        $this->assertSame($insideRangeHistory->id, $histories->first()->id);
        $component->assertViewHas('summaryHours', 2.0);
    }

    public function test_team_member_cannot_view_another_users_work_history_via_route_parameter(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $this->actingAs($karim)
            ->get(route('work-history.show', $rahim))
            ->assertForbidden();
    }
}
