<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Reports\ReportBuilder;
use App\Reports\ReportExport;
use App\Reports\ReportPeriod;
use App\Services\TaskWorkflowService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        Carbon::setTestNow('2026-10-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name, Role $role = Role::TeamMember): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Mira (manager) creates two tasks for Karim in October: one finished on time with 3h logged,
     * one still open and overdue. One task was created in September with 2h logged then.
     */
    private function scenario(): array
    {
        $mira = $this->member('Mira Manager', Role::Manager);
        $karim = $this->member('Karim Hasan');
        $project = Project::factory()->create(['name' => 'Website', 'created_by' => $mira->id]);
        $workflow = app(TaskWorkflowService::class);

        Carbon::setTestNow('2026-09-10 09:00:00');
        $september = $workflow->createTask(['title' => 'September task', 'project_id' => $project->id], $mira, $karim);
        $workflow->logTime($september, $karim, now(), 2);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $done = $workflow->createTask(['title' => 'Finished on time', 'project_id' => $project->id, 'deadline' => '2026-10-10'], $mira, $karim);
        $late = $workflow->createTask(['title' => 'Still open', 'deadline' => '2026-10-08'], $mira, $karim);
        $workflow->logTime($done, $karim, now(), 3);

        Carbon::setTestNow('2026-10-09 09:00:00');
        $workflow->markDone($done, $mira);

        Carbon::setTestNow('2026-10-15 10:00:00');

        return compact('mira', 'karim', 'project', 'done', 'late', 'september');
    }

    public function test_periods_label_step_and_clamp(): void
    {
        $month = ReportPeriod::fromInput('monthly', '2026-10-15');
        $this->assertSame('October 2026', $month->label());
        $this->assertSame('September 2026', $month->previous()->label());
        $this->assertSame(31, count($month->buckets()));

        $this->assertSame('2026', ReportPeriod::fromInput('yearly', '2026-03-01')->label());
        $this->assertSame('month', ReportPeriod::fromInput('yearly', '2026-03-01')->granularity());
        $this->assertSame('12 – 18 Oct 2026', ReportPeriod::fromInput('weekly', '2026-10-15')->label());

        $custom = ReportPeriod::fromInput('custom', null, '2026-10-20', '2026-10-01');
        $this->assertSame('2026-10-01', $custom->start->toDateString(), 'from/to are swapped when reversed');
        $this->assertSame(20, $custom->days());
        $this->assertSame('2026-09-11', $custom->previous()->start->toDateString());

        $this->assertSame('monthly', ReportPeriod::fromInput('nonsense', 'not-a-date')->type);
    }

    public function test_overview_numbers_and_comparison(): void
    {
        $this->scenario();
        $overview = (new ReportBuilder(ReportPeriod::fromInput('monthly', '2026-10-15')))->overview();

        $this->assertSame(2, $overview['kpis']['created']);
        $this->assertSame(1, $overview['kpis']['completed']);
        $this->assertSame(3.0, $overview['kpis']['hours']);
        $this->assertSame(1, $overview['kpis']['contributors']);
        $this->assertSame(100, $overview['kpis']['onTimeRate']);
        $this->assertSame(1, $overview['previous']['created']);
        $this->assertSame(2.0, $overview['previous']['hours']);
        $this->assertSame(1, $overview['overdueNow']);

        $day = collect($overview['series'])->firstWhere('key', '2026-10-05');
        $this->assertSame([3.0, 2], [$day['hours'], $day['created']]);
        $this->assertSame(1, collect($overview['series'])->firstWhere('key', '2026-10-09')['completed']);
    }

    public function test_tasks_projects_and_people_sections(): void
    {
        ['karim' => $karim, 'project' => $project] = $this->scenario();
        $builder = new ReportBuilder(ReportPeriod::fromInput('monthly', '2026-10-15'));

        $titles = collect($builder->taskRows())->pluck(1)->sort()->values()->all();
        $this->assertSame(['Finished on time', 'Still open'], $titles, 'the September-only task is outside October');
        $this->assertSame(['Finished on time'], collect($builder->taskRows(['project' => (string) $project->id]))->pluck(1)->all());
        $this->assertSame(['Still open'], collect($builder->taskRows(['project' => 'none']))->pluck(1)->all());

        $website = $builder->projects()->first(fn ($row) => $row['project']->is($project));
        $this->assertSame(3.0, $website['hours']);
        $this->assertSame(1, (int) $website['project']->completed_in_period);
        $this->assertSame(50, $website['progress']);

        $karimRow = $builder->people()->first(fn ($row) => $row['user']->is($karim));
        // Open now counts the September task too (still open, still Karim's); only the October one is overdue.
        $this->assertSame([3.0, 1, 2, 1], [$karimRow['hours'], $karimRow['completed'], $karimRow['open'], $karimRow['overdue']]);
        $this->assertFalse($builder->people()->contains(fn ($row) => $row['user']->hasRole(Role::SuperAdmin->value)), 'idle Super Admins are left out');
    }

    public function test_the_csv_export_is_formula_safe(): void
    {
        ['mira' => $mira, 'karim' => $karim] = $this->scenario();
        app(TaskWorkflowService::class)->createTask(['title' => '=HYPERLINK("http://evil")'], $mira, $karim);

        $response = $this->actingAs($mira)->get(route('reports.export', ['section' => 'tasks', 'format' => 'csv', 'period' => 'monthly', 'date' => '2026-10-15']));

        $response->assertOk()->assertDownload('handloft-tasks-report-2026-10.csv');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Finished on time', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertSame("'=1+1", ReportExport::csvSafe('=1+1'));
        $this->assertSame(-5, ReportExport::csvSafe(-5), 'numbers are left alone');
    }

    public function test_the_excel_export_is_a_real_workbook_with_one_sheet_per_section(): void
    {
        ['mira' => $mira] = $this->scenario();

        $response = $this->actingAs($mira)->get(route('reports.export', ['section' => 'all', 'format' => 'xlsx', 'period' => 'monthly', 'date' => '2026-10-15']));
        $response->assertOk()->assertDownload('handloft-all-report-2026-10.xlsx');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $workbook = $zip->getFromName('xl/workbook.xml');
        foreach (['Summary', 'Trend', 'Tasks', 'Projects', 'People'] as $sheet) {
            $this->assertStringContainsString('name="'.$sheet.'"', $workbook);
        }
        $this->assertStringContainsString('Finished on time', implode('', array_map(fn ($i) => $zip->getFromIndex($i), range(0, $zip->numFiles - 1))));
        $this->assertNotFalse(simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml')), 'sheet XML is well-formed');
        $zip->close();
    }

    public function test_the_print_view_renders_the_section(): void
    {
        ['mira' => $mira] = $this->scenario();

        $this->actingAs($mira)->get(route('reports.print', ['section' => 'all', 'period' => 'monthly', 'date' => '2026-10-15', 'autoprint' => 1]))
            ->assertOk()
            ->assertSee('Handloft — Full report')
            ->assertSee('October 2026')
            ->assertSee('Finished on time')
            ->assertSee('window.print()', false);
    }

    public function test_the_reports_page_shows_each_tab(): void
    {
        ['mira' => $mira] = $this->scenario();

        $page = Livewire::actingAs($mira)->test('reports.index')
            ->set('period', 'monthly')->set('date', '2026-10-15')
            ->assertSee('October 2026')
            ->assertSee('Hours logged');

        $page->set('tab', 'tasks')->assertSee('Finished on time')->assertDontSee('September task');
        $page->set('tab', 'projects')->assertSee('Website');
        $page->set('tab', 'people')->assertSee('Karim Hasan');

        $page->call('shift', -1)->assertSet('date', '2026-09-01');
        $page->set('tab', 'tasks')->assertSee('September task')->assertDontSee('Finished on time');
        $page->call('setPeriod', 'custom')->assertSet('from', '2026-09-01')->assertSet('to', '2026-09-30');
    }

    public function test_a_person_report_has_daily_hours_logs_and_completed_tasks(): void
    {
        ['karim' => $karim, 'done' => $done, 'late' => $late] = $this->scenario();
        $workflow = app(TaskWorkflowService::class);
        $workflow->logTime($late, $karim, CarbonImmutable::parse('2026-10-06'), 1.5, 'Drafted the copy');

        $person = (new ReportBuilder(ReportPeriod::fromInput('monthly', '2026-10-15')))->person($karim);

        $this->assertSame(4.5, $person['hours']);
        $this->assertSame(2, $person['activeDays']);
        $this->assertSame(2.25, $person['avgPerActiveDay']);
        $this->assertSame(2, $person['tasksWorked']);
        $this->assertSame(1, $person['completedCount']);
        $this->assertSame(100, $person['onTimeRate']);
        $this->assertTrue($person['completed']->first()->is($done));
        $this->assertSame(['2026-10-06', '2026-10-05'], $person['logsByDay']->keys()->all(), 'newest day first');
        $this->assertSame('Drafted the copy', $person['logsByDay']['2026-10-06']->first()->note);
        $this->assertSame(1.5, collect($person['series'])->firstWhere('key', '2026-10-06')['hours']);
    }

    public function test_the_daily_hours_grid_has_a_cell_per_person_per_day(): void
    {
        ['karim' => $karim] = $this->scenario();
        $grid = (new ReportBuilder(ReportPeriod::fromInput('weekly', '2026-10-05')))->dailyHours();

        $this->assertCount(7, $grid['buckets']);
        $row = $grid['rows']->first(fn ($row) => $row['user']->is($karim));
        $this->assertSame(3.0, $row['cells']['2026-10-05']);
        $this->assertSame(3.0, $row['total']);
        $this->assertSame(3.0, $grid['totals']['2026-10-05']);

        $yearly = (new ReportBuilder(ReportPeriod::fromInput('yearly', '2026-01-01')))->dailyHours();
        $this->assertCount(12, $yearly['buckets'], 'a year is shown by month');
        $this->assertSame(2.0, $yearly['rows']->first(fn ($row) => $row['user']->is($karim))['cells']['2026-09']);
    }

    public function test_the_people_tab_drills_into_a_person_and_shows_the_daily_grid(): void
    {
        ['mira' => $mira, 'karim' => $karim] = $this->scenario();

        $page = Livewire::actingAs($mira)->test('reports.index')
            ->set('period', 'monthly')->set('date', '2026-10-15')->set('tab', 'people');

        $page->set('peopleView', 'daily')->assertSee('Daily hours')->assertSee('Karim Hasan');
        $page->set('person', (string) $karim->id)
            ->assertSee('Time by task')
            ->assertSee('Completed tasks')
            ->assertSee('Finished on time')
            ->assertSee(route('reports.export', ['section' => 'person', 'format' => 'xlsx', 'period' => 'monthly', 'date' => '2026-10-01', 'user' => $karim->id]));

        $page->set('tab', 'overview')->assertSet('person', '');
    }

    public function test_person_exports_and_the_people_daily_sheet(): void
    {
        ['mira' => $mira, 'karim' => $karim] = $this->scenario();
        $query = ['period' => 'monthly', 'date' => '2026-10-15'];

        $csv = $this->actingAs($mira)->get(route('reports.export', ['section' => 'person', 'format' => 'csv', 'user' => $karim->id] + $query));
        $csv->assertOk()->assertDownload('handloft-person-karim-hasan-report-2026-10.csv');
        $this->assertStringContainsString("Karim Hasan's report", $csv->streamedContent());
        $this->assertStringContainsString('Finished on time', $csv->streamedContent());

        $xlsx = $this->actingAs($mira)->get(route('reports.export', ['section' => 'person', 'format' => 'xlsx', 'user' => $karim->id] + $query));
        $zip = new ZipArchive;
        $zip->open($xlsx->getFile()->getPathname());
        foreach (['Summary', 'Daily hours', 'Time logs', 'Completed tasks'] as $sheet) {
            $this->assertStringContainsString('name="'.$sheet.'"', $zip->getFromName('xl/workbook.xml'));
        }
        $zip->close();

        $people = $this->actingAs($mira)->get(route('reports.export', ['section' => 'people', 'format' => 'xlsx'] + $query));
        $zip->open($people->getFile()->getPathname());
        $this->assertStringContainsString('name="Daily hours"', $zip->getFromName('xl/workbook.xml'));
        $zip->close();

        $this->actingAs($mira)->get(route('reports.print', ['section' => 'person', 'user' => $karim->id] + $query))
            ->assertOk()->assertSee('Karim Hasan&#039;s report', false)->assertSee('daily time logs');

        $this->actingAs($mira)->get(route('reports.export', ['section' => 'person', 'format' => 'csv', 'user' => 99999] + $query))->assertNotFound();
    }

    public function test_access_is_limited_to_report_permissions(): void
    {
        $karim = $this->member('Karim Hasan');

        // A team member has Reports, but only their own report: no team tabs, no team prints or exports.
        $this->actingAs($karim)->get(route('reports.index'))->assertOk()->assertSee('My report')->assertDontSee('>Overview<', false);
        $this->actingAs($karim)->get(route('reports.print'))->assertForbidden();
        $this->actingAs($karim)->get(route('reports.export', ['section' => 'tasks', 'format' => 'csv']))->assertForbidden();
        $this->actingAs($karim)->get(route('dashboard'))->assertSee('My Report');

        $mira = $this->member('Mira Manager', Role::Manager);
        $this->actingAs($mira)->get(route('reports.index'))->assertOk();
        $this->actingAs($mira)->get(route('reports.export', ['section' => 'all', 'format' => 'csv']))->assertNotFound();
    }
}
