<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarTest extends TestCase
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

    private function manager(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::Manager->value);

        return $user;
    }

    public function test_a_task_deadline_this_month_shows_up_in_month_view(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask([
            'title' => 'Ship the landing page',
            'deadline' => now()->startOfMonth()->addDays(5),
        ], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->assertSee('Ship the landing page')
            ->assertSee($task->task_key);
    }

    public function test_a_task_deadline_outside_the_visible_range_is_not_shown(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Far future task',
            'deadline' => now()->addMonths(3),
        ], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->assertDontSee('Far future task');
    }

    public function test_a_project_deadline_this_month_shows_up(): void
    {
        $manager = $this->manager('Rahim');

        Project::create([
            'name' => 'Website Relaunch',
            'created_by' => $manager->id,
            'status' => ProjectStatus::Active,
            'deadline' => now()->startOfMonth()->addDays(10),
        ]);

        Livewire::actingAs($manager)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->assertSee('Website Relaunch');
    }

    public function test_a_team_member_only_sees_tasks_they_are_connected_to(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Not my task',
            'deadline' => now()->startOfMonth()->addDays(2),
        ], $rahim, $karim);

        Livewire::actingAs($hasan)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->assertDontSee('Not my task');
    }

    public function test_a_view_all_tasks_holder_sees_every_tasks_deadline(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Someone elses task',
            'deadline' => now()->startOfMonth()->addDays(3),
        ], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->assertSee('Someone elses task');
    }

    public function test_switching_to_day_view_shows_only_that_days_deadlines(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Today task',
            'deadline' => now(),
        ], $rahim, $karim);

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Tomorrow task',
            'deadline' => now()->addDay(),
        ], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->call('setView', 'day')
            ->assertSee('Today task')
            ->assertDontSee('Tomorrow task');
    }

    public function test_day_view_shows_a_today_badge_only_when_viewing_the_current_day(): void
    {
        $karim = $this->teamMember('Karim');

        $component = Livewire::actingAs($karim)
            ->test('calendar.index')
            ->call('setView', 'day');

        $component->assertSeeHtml('bg-brand px-2 py-0.5 text-xs font-medium text-white">Today</span>');

        $component->call('next');
        $component->assertDontSeeHtml('bg-brand px-2 py-0.5 text-xs font-medium text-white">Today</span>');
    }

    public function test_navigating_to_the_next_month_changes_the_visible_range(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Next month task',
            'deadline' => now()->addMonthNoOverflow()->startOfMonth()->addDays(2),
        ], $rahim, $karim);

        $component = Livewire::actingAs($karim)->test('calendar.index');
        $component->set('dateType', 'deadline');

        $component->assertDontSee('Next month task');

        $component->call('next')->assertSee('Next month task');
    }

    public function test_today_button_returns_to_the_current_month(): void
    {
        $karim = $this->teamMember('Karim');

        $component = Livewire::actingAs($karim)->test('calendar.index');

        $component->call('next')->call('next');
        $component->call('goToToday');

        $component->assertSet('cursor', now()->toDateString());
    }

    public function test_todays_day_is_marked_with_a_ring_in_month_view(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->assertSeeHtml('ring-2 ring-inset ring-brand');
    }

    public function test_navigating_to_another_month_does_not_mark_any_day_with_a_ring(): void
    {
        $karim = $this->teamMember('Karim');

        // Only the real "today" cell gets the ring — jumping to a different
        // month must not keep marking the same day-of-month as today.
        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->call('next')
            ->assertDontSeeHtml('ring-2 ring-inset ring-brand');
    }

    public function test_day_view_has_no_grid_so_no_ring_markup(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->call('setView', 'day')
            ->assertDontSeeHtml('ring-2 ring-inset ring-brand');
    }

    public function test_the_month_dropdown_jumps_to_the_chosen_month_in_the_same_year(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('cursor', '2026-03-15')
            ->call('setMonth', 7)
            ->assertSet('cursor', '2026-07-15');
    }

    public function test_the_year_dropdown_jumps_to_the_chosen_year_in_the_same_month(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('cursor', '2026-03-15')
            ->call('setYear', 2028)
            ->assertSet('cursor', '2028-03-15');
    }

    public function test_jumping_to_a_shorter_month_clamps_the_day(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('cursor', '2026-01-31')
            ->call('setMonth', 2)
            ->assertSet('cursor', '2026-02-28');
    }

    public function test_jumping_to_february_29_in_a_non_leap_year_clamps_the_day(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('cursor', '2024-02-29')
            ->call('setYear', 2026)
            ->assertSet('cursor', '2026-02-28');
    }

    public function test_guests_cannot_view_the_calendar(): void
    {
        $this->get(route('calendar.index'))->assertRedirect(route('login'));
    }

    public function test_clicking_a_day_opens_a_modal_categorized_by_project_and_task(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $manager = $this->manager('Nasrin');

        $deadline = now()->startOfMonth()->addDays(6);

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Modal task',
            'deadline' => $deadline,
        ], $rahim, $karim);

        Project::create([
            'name' => 'Modal project',
            'created_by' => $manager->id,
            'status' => ProjectStatus::Active,
            'deadline' => $deadline,
        ]);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->set('dateType', 'deadline')
            ->call('openDay', $deadline->toDateString())
            ->assertSet('showDayModal', true)
            ->assertSee('Projects')
            ->assertSee('Modal project')
            ->assertSee('Tasks')
            ->assertSee('Modal task');
    }

    public function test_the_modal_marks_an_overdue_task_and_a_qa_task_with_pills(): void
    {
        // Frozen mid-month so both an "a few days ago" and "a few days from
        // now" deadline are guaranteed to land in the same default month
        // view, regardless of what day this test actually runs on.
        Carbon::setTestNow('2026-06-15');

        try {
            $rahim = $this->teamMember('Rahim');
            $karim = $this->teamMember('Karim');
            $qa = $this->teamMember('Qadir');
            $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();

            $workflow = app(TaskWorkflowService::class);

            $overdueDate = Carbon::parse('2026-06-10');
            $workflow->createTask(['title' => 'Late task', 'deadline' => $overdueDate], $rahim, $karim);

            $qaDate = Carbon::parse('2026-06-20');
            $workflow->createTask([
                'title' => 'QA task',
                'deadline' => $qaDate,
                'status' => TaskStatus::QaTesting,
                'qa_id' => $qa->id,
            ], $rahim, $karim);

            $component = Livewire::actingAs($admin)->test('calendar.index');
            $component->set('dateType', 'deadline');

            $component->call('openDay', $overdueDate->toDateString())
                ->assertSee('Overdue');

            $component->call('closeDayModal')
                ->call('openDay', $qaDate->toDateString())
                ->assertSee('QA');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_default_date_type_is_created_and_shows_a_newly_created_task_today(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask([
            'title' => 'Brand new task',
            // Deliberately far in the future — should NOT matter under the
            // default "created" view, proving it isn't reading the deadline.
            'deadline' => now()->addMonths(6),
        ], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->assertSet('dateType', 'created')
            ->assertSee('Brand new task')
            ->assertSee($task->task_key);
    }

    public function test_the_overdue_pill_only_applies_to_the_deadline_view(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        // Created today, but with a deadline that's already in the past —
        // "overdue" is a deadline concept, so it has no meaning while
        // viewing by creation date.
        app(TaskWorkflowService::class)->createTask([
            'title' => 'Created today overdue task',
            'deadline' => now()->subDays(3),
        ], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->assertSet('dateType', 'created')
            ->call('openDay', now()->toDateString())
            ->assertDontSee('Overdue');
    }

    public function test_the_qa_pill_shows_regardless_of_the_active_date_type(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $qa = $this->teamMember('Qadir');

        app(TaskWorkflowService::class)->createTask([
            'title' => 'Created today QA task',
            'status' => TaskStatus::QaTesting,
            'qa_id' => $qa->id,
        ], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->assertSet('dateType', 'created')
            ->call('openDay', now()->toDateString())
            ->assertSee('QA');
    }

    public function test_closing_the_modal_hides_it(): void
    {
        $karim = $this->teamMember('Karim');

        Livewire::actingAs($karim)
            ->test('calendar.index')
            ->call('openDay', now()->toDateString())
            ->assertSet('showDayModal', true)
            ->call('closeDayModal')
            ->assertSet('showDayModal', false);
    }
}
