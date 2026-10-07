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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function member(string $name, Role $role = Role::TeamMember): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_a_team_member_sees_their_own_work_but_no_team_overview(): void
    {
        $rahim = $this->member('Rahim Uddin');
        $karim = $this->member('Karim Hasan');
        app(TaskWorkflowService::class)->createTask(['title' => 'Write the brief', 'deadline' => now()->subDays(2)], $rahim, $karim);

        Livewire::actingAs($karim)->test('dashboard')
            ->assertSee('Karim')
            ->assertSee('Needs your attention')
            ->assertSee('Write the brief')
            ->assertSee('Overdue 2d')
            ->assertDontSee('Team overview')
            ->assertDontSee('Top contributors')
            ->assertViewHas('my', fn ($my) => $my['open'] === 1 && $my['overdue'] === 1);
    }

    public function test_the_attention_list_includes_qa_reviews_and_sign_offs_but_not_others_work(): void
    {
        $rahim = $this->member('Rahim Uddin');
        $karim = $this->member('Karim Hasan');
        $workflow = app(TaskWorkflowService::class);

        $review = $workflow->createTask(['title' => 'Review this', 'qa_id' => $karim->id], $rahim, $rahim);
        $review->update(['status' => TaskStatus::QaTesting]);

        $signOff = $workflow->createTask(['title' => 'Sign this off'], $karim, $rahim);
        $signOff->update(['status' => TaskStatus::ReadyToDeploy]);

        $workflow->createTask(['title' => 'Rahims own work'], $rahim, $rahim);

        Livewire::actingAs($karim)->test('dashboard')
            ->assertSee('Review this')
            ->assertSee('Sign this off')
            ->assertDontSee('Rahims own work')
            ->assertViewHas('attention', fn ($attention) => $attention['total'] === 2
                && $attention['items']->pluck('reason')->sort()->values()->all() === ['Review', 'Sign off']);
    }

    public function test_hours_come_from_the_time_logs(): void
    {
        $karim = $this->member('Karim Hasan');
        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Log some time'], $karim, $karim);

        $workflow->logTime($task, $karim, now(), 2.5);
        $workflow->logTime($task, $karim, now()->subDays(20), 4);

        Livewire::actingAs($karim)->test('dashboard')
            ->assertViewHas('my', fn ($my) => $my['hoursToday'] === 2.5)
            ->assertViewHas('chart', fn ($chart) => $chart['total'] === 2.5 && $chart['days']->last()['hours'] === 2.5);
    }

    public function test_a_manager_sees_the_team_overview_and_top_contributors(): void
    {
        $manager = $this->member('Mira Manager', Role::Manager);
        $karim = $this->member('Karim Hasan');
        $workflow = app(TaskWorkflowService::class);

        $done = $workflow->createTask(['title' => 'Finished thing'], $manager, $karim);
        $workflow->markDone($done, $manager);
        $workflow->createTask(['title' => 'Late thing', 'deadline' => now()->subDay()], $manager, $karim);
        $workflow->logTime($done, $karim, now(), 3);

        Livewire::actingAs($manager)->test('dashboard')
            ->assertSee('Team overview')
            ->assertSee('Task pipeline')
            ->assertSee('Top contributors')
            ->assertViewHas('team', fn ($team) => $team['active'] === 1 && $team['overdue'] === 1 && $team['completedWeek'] === 1 && $team['hoursWeek'] === 3.0)
            ->assertViewHas('contributors', fn ($rows) => $rows->first()['user']->is($karim) && $rows->first()['hours'] === 3.0);
    }

    public function test_a_team_members_manage_section_holds_only_settings(): void
    {
        // Settings is open to everyone (for their own look), so Manage stays, without Team.
        $this->actingAs($this->member('Karim Hasan'))->get(route('dashboard'))
            ->assertSee('Workspace')
            ->assertSee('Insights')
            ->assertSee('>Manage<', false)
            ->assertSee(route('settings'), false)
            ->assertDontSee(route('users.index'), false);

        $admin = User::role(Role::SuperAdmin->value)->firstOrFail();
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('>Manage<', false);
    }
}
