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

/**
 * Personal reports (what Work History used to show) and correcting logged hours from Reports.
 */
class ReportHoursTest extends TestCase
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

    private function admin(): User
    {
        return User::where('email', 'admin@handloft.test')->firstOrFail();
    }

    public function test_everyone_sees_their_own_logged_time_whether_assignee_reporter_or_qa(): void
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

        Livewire::actingAs($karim)->test('reports.index')->assertSee('Assignee work')->assertDontSee('QA testing')->assertViewHas('personReport', fn ($r) => $r['hours'] === 2.0);
        Livewire::actingAs($hasan)->test('reports.index')->assertSee('QA testing')->assertViewHas('personReport', fn ($r) => $r['hours'] === 1.0);
        Livewire::actingAs($rahim)->test('reports.index')->assertSee('Sign-off review')->assertViewHas('personReport', fn ($r) => $r['hours'] === 0.5);
    }

    public function test_a_team_member_is_locked_to_their_own_report(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $workflow->logTime($task, $karim, now(), 2.0, 'Karim only');

        $this->actingAs($rahim)->get(route('reports.index', ['tab' => 'people', 'person' => $karim->id]))
            ->assertOk()
            ->assertSee('My report')
            ->assertDontSee('Karim only')
            ->assertDontSee('All people');

        Livewire::withQueryParams(['tab' => 'overview', 'person' => (string) $karim->id])
            ->actingAs($rahim)
            ->test('reports.index')
            ->assertSet('person', (string) $rahim->id)
            ->assertSet('tab', 'people')
            ->set('person', (string) $karim->id)
            ->assertSet('person', (string) $rahim->id)
            ->assertDontSee('Karim only');

        // They can print their own report, not anyone else's, and nothing whole-team.
        $this->actingAs($rahim)->get(route('reports.print', ['section' => 'person', 'user' => $rahim->id]))->assertOk();
        $this->actingAs($rahim)->get(route('reports.print', ['section' => 'person', 'user' => $karim->id]))->assertForbidden();
        $this->actingAs($rahim)->get(route('reports.print', ['section' => 'all']))->assertForbidden();
    }

    public function test_only_super_admins_can_correct_hours(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::actingAs($karim)->test('reports.index')->assertDontSee('Edit hours')->call('startEditLog', $log->id)->assertForbidden();
        Livewire::actingAs($karim)->test('reports.index')->call('deleteLog', $log->id)->assertForbidden();

        $this->assertSame(1, TaskTimeLog::whereKey($log->id)->count());
    }

    public function test_starting_an_edit_splits_the_stored_decimal_hours_into_hours_and_minutes(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::withQueryParams(['tab' => 'people', 'person' => (string) $karim->id])
            ->actingAs($this->admin())
            ->test('reports.index')
            ->assertSee('Edit hours')
            ->call('startEditLog', $log->id)
            ->assertSet('edit_hours', '3')
            ->assertSet('edit_minutes', '30');
    }

    public function test_an_admin_edit_needs_a_reason_persists_and_is_audit_logged_and_on_the_task_timeline(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, Carbon::parse('2026-09-27'), 3.5);
        $countBeforeEdit = $task->activities()->count();

        Livewire::actingAs($admin)
            ->test('reports.index')
            ->call('startEditLog', $log->id)
            ->set('edit_hours', '4')
            ->set('edit_minutes', '0')
            ->call('saveEditLog')
            ->assertHasErrors(['edit_reason'])
            ->set('edit_reason', 'Incorrect time entry')
            ->call('saveEditLog')
            ->assertHasNoErrors()
            ->assertSet('editingLogId', null);

        $this->assertEquals(4.0, $log->fresh()->hours);

        $activity = Activity::query()->where('description', 'Time log hours edited')->latest()->first();
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('3.50', $activity->properties['old_hours']);
        $this->assertSame('4', $activity->properties['new_hours']);
        $this->assertSame('Incorrect time entry', $activity->properties['reason']);

        $this->assertSame($countBeforeEdit + 1, $task->activities()->count());
        $this->assertSame('Super Admin updated the time log for 27 Sep 2026 to 4h', $task->activities()->reorder('id', 'desc')->first()->description);
    }

    public function test_an_admin_can_remove_someone_elses_time_entry(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Create Client List'], $rahim, $karim);
        $log = $workflow->logTime($task, $karim, now(), 3.5);

        Livewire::actingAs($this->admin())->test('reports.index')->call('deleteLog', $log->id);

        $this->assertSame(0, TaskTimeLog::whereKey($log->id)->count());
    }

    public function test_the_team_page_links_to_each_persons_report(): void
    {
        $karim = $this->teamMember('Karim');

        $this->actingAs($this->admin())->get(route('users.index'))
            ->assertSee(e(route('reports.index', ['tab' => 'people', 'person' => $karim->id])), false);
    }
}
