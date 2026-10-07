<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppSetting;
use App\Models\User;
use App\Reports\ReportBuilder;
use App\Reports\ReportPeriod;
use App\Services\TaskWorkflowService;
use App\Support\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WorkScheduleAndAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        Carbon::setTestNow('2026-10-15 10:00:00'); // a Thursday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::role(Role::SuperAdmin->value)->firstOrFail();
    }

    public function test_the_defaults_keep_a_monday_week_with_weekends_off_and_an_eight_hour_day(): void
    {
        $this->assertSame([0, 6], WorkSchedule::offDays());
        $this->assertSame(1, WorkSchedule::weekStartsOn());
        $this->assertSame(8.0, WorkSchedule::dailyTarget());
        $this->assertSame('12 – 18 Oct 2026', ReportPeriod::fromInput('weekly', '2026-10-15')->label());
    }

    public function test_an_admin_can_save_a_friday_off_saturday_start_schedule(): void
    {
        Livewire::actingAs($this->admin())->test('settings')
            ->set('tab', 'schedule')
            ->call('setOffDays', 'fri')
            ->assertSet('off_days', ['5'])
            ->set('week_starts_on', '6')
            ->set('daily_hours_target', '7.5')
            ->call('saveSchedule')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Work schedule saved.', type: 'success');

        $this->assertSame([5], WorkSchedule::offDays());
        $this->assertSame(6, WorkSchedule::weekStartsOn());
        $this->assertSame(7.5, WorkSchedule::dailyTarget());

        // The week now runs Saturday → Friday everywhere.
        $this->assertSame('10 – 16 Oct 2026', ReportPeriod::fromInput('weekly', '2026-10-15')->label());
        $this->assertSame(['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'], array_column(WorkSchedule::weekdays(), 'short'));
        $this->assertTrue(WorkSchedule::isOffDay(CarbonImmutable::parse('2026-10-16')));
        $this->assertSame(6, WorkSchedule::workingDaysBetween(CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-10-16')));
    }

    public function test_the_schedule_is_validated(): void
    {
        Livewire::actingAs($this->admin())->test('settings')
            ->set('off_days', ['0', '1', '2', '3', '4', '5', '6'])
            ->set('daily_hours_target', '30')
            ->set('week_starts_on', '3')
            ->call('saveSchedule')
            ->assertHasErrors(['off_days' => 'max', 'daily_hours_target', 'week_starts_on']);

        Livewire::actingAs($this->admin())->test('settings')
            ->call('setOffDays', 'none')
            ->set('daily_hours_target', '')
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $this->assertSame([], WorkSchedule::offDays());
        $this->assertNull(WorkSchedule::dailyTarget());
    }

    public function test_the_calendar_starts_its_weeks_on_the_configured_day(): void
    {
        AppSetting::current()->update(['week_starts_on' => 6, 'off_days' => [5]]);

        Livewire::actingAs($this->admin())->test('calendar.index')
            ->assertViewHas('days', fn ($days) => $days->first()->isSaturday() && $days->last()->isFriday())
            ->assertSeeInOrder(['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri']);
    }

    public function test_a_person_report_compares_hours_with_the_target_over_elapsed_working_days(): void
    {
        $karim = User::factory()->create(['name' => 'Karim Hasan', 'status' => 'active']);
        $karim->assignRole(Role::TeamMember->value);
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Write the brief'], $karim, $karim);
        app(TaskWorkflowService::class)->logTime($task, $karim, CarbonImmutable::parse('2026-10-14'), 6);

        // Week of Mon 12 – Sun 18 Oct; today is Thursday 15th, so Mon–Thu = 4 working days × 8h = 32h.
        $person = (new ReportBuilder(ReportPeriod::fromInput('weekly', '2026-10-15')))->person($karim);

        $this->assertSame(4, $person['workingDays']);
        $this->assertSame(32.0, $person['targetHours']);
        $this->assertSame(19, $person['targetRate']);
    }

    public function test_the_look_is_kept_per_browser_with_glass_forest_and_system_as_the_defaults(): void
    {
        // Nothing about the look is rendered from the server: the page carries the presets and the
        // defaults, and the browser applies the person's own choice from localStorage.
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertSee('window.appearance', false)
            ->assertSee('localStorage.getItem(key)', false)
            ->assertSee("presets[theme] ? theme : 'forest'", false)
            ->assertSee("read('appearance.style') === 'static' ? 'static' : 'glass'", false)
            ->assertSee("mode === 'light' || mode === 'dark' ? mode : 'system'", false)
            ->assertDontSee('--brand-base: #', false)
            ->assertDontSee(' ui-static"', false);
    }

    public function test_everyone_can_open_settings_but_only_super_admins_get_the_app_wide_tabs(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $member->assignRole(Role::TeamMember->value);

        $this->actingAs($member)->get(route('settings'))
            ->assertOk()
            ->assertSee('Visual style')
            ->assertSee('Theme colour')
            ->assertSee('saved in this browser only')
            ->assertDontSee('Work Schedule')
            ->assertDontSee('Email / SMTP');

        // Asking for another tab still shows only the Look tab, and app-wide saves stay forbidden.
        Livewire::actingAs($member)->test('settings')
            ->set('tab', 'smtp')
            ->assertDontSee('Save SMTP settings')
            ->assertSee('Visual style');

        Livewire::actingAs($member)->test('settings')->call('saveSchedule')->assertForbidden();

        $this->actingAs($this->admin())->get(route('settings'))
            ->assertSee('Work Schedule')
            ->assertSee('Email / SMTP');
    }

    public function test_every_page_offers_light_dark_and_system_modes_but_the_print_report_stays_light(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertSee("classList.toggle('dark'", false)
            ->assertSee('aria-label="Colour mode"', false)
            ->assertSeeInOrder(['Light', 'Dark', 'System']);

        $this->actingAs($admin)->get(route('reports.print'))
            ->assertOk()
            ->assertDontSee("classList.toggle('dark'", false);
    }
}
