<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_the_reporter_and_qa_reviewers_logged_time_shows_up_too_not_just_the_assignees(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List', 'qa_id' => $hasan->id], $rahim, $karim);
        $task->update(['status' => TaskStatus::QaTesting]);
        $workflow->logTime($task, $karim, now(), 2.0, 'Assignee work');
        $workflow->logTime($task, $hasan, now(), 1.0, 'QA testing');
        $task->update(['status' => TaskStatus::ReadyToDeploy]);
        $workflow->logTime($task, $rahim, now(), 0.5, 'Sign-off review');

        Livewire::actingAs($karim)->test('work-history.index')->assertSee('Assignee work')->assertViewHas('summaryHours', 2.0);
        Livewire::actingAs($hasan)->test('work-history.index')->assertSee('QA testing')->assertViewHas('summaryHours', 1.0);
        Livewire::actingAs($rahim)->test('work-history.index')->assertSee('Sign-off review')->assertViewHas('summaryHours', 0.5);
    }

    public function test_non_admin_cannot_edit_someone_elses_time_log(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::actingAs($hasan)
            ->test('work-history.index')
            ->call('startEdit', $log->id)
            ->assertForbidden();
    }

    public function test_the_entry_owner_can_edit_their_own_time_log(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::actingAs($karim)
            ->test('work-history.index')
            ->call('startEdit', $log->id)
            ->set('edit_hours', '4')
            ->set('edit_reason', 'Forgot some time')
            ->call('saveEdit');

        $this->assertEquals(4.0, $log->fresh()->hours);
    }

    public function test_admin_edit_persists_and_is_audit_logged(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::actingAs($admin)
            ->test('work-history.index')
            ->call('startEdit', $log->id)
            ->set('edit_hours', '4')
            ->set('edit_reason', 'Incorrect time entry')
            ->call('saveEdit');

        $log->refresh();

        $this->assertEquals(4.0, $log->hours);

        $activity = Activity::query()->where('description', 'Time log hours edited')->latest()->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('3.50', $activity->properties['old_hours']);
        $this->assertSame('4', $activity->properties['new_hours']);
        $this->assertSame('Incorrect time entry', $activity->properties['reason']);
    }

    public function test_admin_can_delete_someone_elses_time_log_entry(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::actingAs($admin)
            ->test('work-history.index')
            ->call('deleteLog', $log->id);

        $this->assertSame(0, TaskTimeLog::where('id', $log->id)->count());
    }

    public function test_admin_can_compare_multiple_team_members_side_by_side(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task1 = $workflow->createTask(['title' => 'Task for Rahim'], $rahim, $rahim);
        $workflow->logTime($task1, $rahim, now(), 3.0);
        $task2 = $workflow->createTask(['title' => 'Task for Karim'], $karim, $karim);
        $workflow->logTime($task2, $karim, now(), 5.0);

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
        $workflow->logTime($insideRangeTask, $karim, Carbon::parse('2026-01-10'), 2.0);

        $outsideRangeTask = $workflow->createTask(['title' => 'Outside Range'], $rahim, $karim);
        $workflow->logTime($outsideRangeTask, $karim, Carbon::parse('2026-05-01'), 4.0);

        $component = Livewire::actingAs($karim)
            ->test('work-history.index')
            ->set('range', 'custom')
            ->set('from', '2026-01-01')
            ->set('to', '2026-01-31');

        $tasks = $component->viewData('tasks');

        $this->assertCount(1, $tasks);
        $this->assertSame($insideRangeTask->id, $tasks->first()->id);
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
