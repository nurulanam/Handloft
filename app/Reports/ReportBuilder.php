<?php

namespace App\Reports;

use App\Enums\Role;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Support\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The one place report numbers are computed. The Reports page, the CSV and
 * Excel exports and the print view all read from here, so they always agree.
 *
 * Definitions (all within the period unless noted):
 *  - created     tasks whose created_at falls in the period
 *  - completed   tasks marked Done in the period (the "Completed" activity, which
 *                every route to Done records via TaskWorkflowService::markDone)
 *  - hours       time logged (task_time_logs), by logged_date
 *  - on time     completed tasks that had a deadline and were done on or before it
 *  - open/overdue  current state, as of now
 */
final class ReportBuilder
{
    public function __construct(public readonly ReportPeriod $period) {}

    /* ------------------------------------------------------------------ */
    /* Overview */
    /* ------------------------------------------------------------------ */

    public function overview(): array
    {
        $current = $this->headlineFor($this->period);
        $previous = $this->headlineFor($this->period->previous());

        return [
            'kpis' => $current,
            'previous' => $previous,
            'overdueNow' => $this->openTasks()->whereNotNull('deadline')->whereDate('deadline', '<', today())->count(),
            'openNow' => $this->openTasks()->count(),
            'series' => $this->series(),
            'statusBreakdown' => $this->statusBreakdown(),
            'topProjects' => $this->hoursByProject()->take(5)->values(),
            'topPeople' => $this->hoursByPerson()->take(5)->values(),
        ];
    }

    /**
     * @return array{created: int, completed: int, hours: float, contributors: int, onTimeRate: ?int, completedWithDeadline: int}
     */
    private function headlineFor(ReportPeriod $period): array
    {
        $completed = $this->completedActivities($period);

        $withDeadline = (clone $completed)->join('tasks', 'tasks.id', '=', 'task_activities.task_id')->whereNotNull('tasks.deadline');
        $withDeadlineCount = (clone $withDeadline)->distinct()->count('task_activities.task_id');
        $onTime = (clone $withDeadline)->whereRaw('date(task_activities.occurred_at) <= date(tasks.deadline)')->distinct()->count('task_activities.task_id');

        $logs = $this->logs($period);

        return [
            'created' => Task::query()->whereBetween('created_at', [$period->start, $period->end])->count(),
            'completed' => (clone $completed)->distinct()->count('task_id'),
            'hours' => (float) (clone $logs)->sum('hours'),
            'contributors' => (clone $logs)->distinct()->count('user_id'),
            'onTimeRate' => $withDeadlineCount > 0 ? (int) round($onTime / $withDeadlineCount * 100) : null,
            'completedWithDeadline' => $withDeadlineCount,
        ];
    }

    /**
     * Hours, created and completed per bucket (day or month).
     *
     * @return list<array{key: string, label: string, long: string, hours: float, created: int, completed: int}>
     */
    public function series(): array
    {
        $hours = $this->logs($this->period)
            ->selectRaw($this->period->bucketSql('logged_date').' as bucket, sum(hours) as total')
            ->groupBy('bucket')->toBase()->pluck('total', 'bucket');

        $created = Task::query()->whereBetween('created_at', [$this->period->start, $this->period->end])
            ->selectRaw($this->period->bucketSql('created_at').' as bucket, count(*) as total')
            ->groupBy('bucket')->toBase()->pluck('total', 'bucket');

        $completed = $this->completedActivities($this->period)
            ->selectRaw($this->period->bucketSql('occurred_at').' as bucket, count(distinct task_id) as total')
            ->groupBy('bucket')->toBase()->pluck('total', 'bucket');

        return array_map(fn (array $bucket) => $bucket + [
            'hours' => (float) ($hours[$bucket['key']] ?? 0),
            'created' => (int) ($created[$bucket['key']] ?? 0),
            'completed' => (int) ($completed[$bucket['key']] ?? 0),
        ], $this->period->buckets());
    }

    /**
     * Current status of the tasks created in the period.
     *
     * @return Collection<int, array{status: TaskStatus, count: int}>
     */
    public function statusBreakdown(): Collection
    {
        $counts = Task::query()->whereBetween('created_at', [$this->period->start, $this->period->end])
            ->selectRaw('status, count(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        return collect(TaskStatus::cases())->map(fn (TaskStatus $status) => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)]);
    }

    /**
     * @return Collection<int, array{name: string, hours: float}>
     */
    public function hoursByProject(): Collection
    {
        return $this->logs($this->period)
            ->join('tasks', 'tasks.id', '=', 'task_time_logs.task_id')
            ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->selectRaw("coalesce(projects.name, 'No project') as name, sum(task_time_logs.hours) as hours")
            ->groupBy('name')->orderByDesc('hours')->toBase()->get()
            ->map(fn ($row) => ['name' => $row->name, 'hours' => (float) $row->hours]);
    }

    /**
     * @return Collection<int, array{name: string, hours: float}>
     */
    public function hoursByPerson(): Collection
    {
        return $this->logs($this->period)
            ->join('users', 'users.id', '=', 'task_time_logs.user_id')
            ->selectRaw('users.name as name, sum(task_time_logs.hours) as hours')
            ->groupBy('users.id', 'users.name')->orderByDesc('hours')->toBase()->get()
            ->map(fn ($row) => ['name' => $row->name, 'hours' => (float) $row->hours]);
    }

    /* ------------------------------------------------------------------ */
    /* Tasks */
    /* ------------------------------------------------------------------ */

    /**
     * Every task with activity in the period: created, completed, due, or time logged in it.
     *
     * @param  array{project?: ?string, status?: ?string, assignee?: ?string, search?: ?string}  $filters
     */
    public function tasksQuery(array $filters = []): Builder
    {
        [$start, $end] = [$this->period->start, $this->period->end];

        return Task::query()
            ->with(['project', 'creator', 'currentAssignment.assignedTo'])
            ->where(fn (Builder $q) => $q
                ->whereBetween('created_at', [$start, $end])
                ->orWhereBetween('deadline', [$start->toDateString(), $end->toDateString()])
                ->orWhereHas('timeLogs', fn ($l) => $l->whereDate('logged_date', '>=', $start)->whereDate('logged_date', '<=', $end))
                ->orWhereHas('activities', fn ($a) => $a->where('type', TaskActivityType::Completed)->whereBetween('occurred_at', [$start, $end])))
            ->addSelect([
                'period_hours' => TaskTimeLog::query()->selectRaw('coalesce(sum(hours), 0)')
                    ->whereColumn('task_id', 'tasks.id')
                    ->whereDate('logged_date', '>=', $start)->whereDate('logged_date', '<=', $end),
                'completed_at' => TaskActivity::query()->selectRaw('max(occurred_at)')
                    ->whereColumn('task_id', 'tasks.id')->where('type', TaskActivityType::Completed),
            ])
            ->when(($filters['project'] ?? '') === 'none', fn ($q) => $q->whereNull('project_id'))
            ->when(ctype_digit((string) ($filters['project'] ?? '')), fn ($q) => $q->where('project_id', (int) $filters['project']))
            ->when(TaskStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($q, $status) => $q->where('status', $status))
            ->when(ctype_digit((string) ($filters['assignee'] ?? '')), fn ($q) => $q->whereHas('currentAssignment', fn ($a) => $a->where('assigned_to', (int) $filters['assignee'])))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function ($q) use ($filters) {
                $term = trim($filters['search']);
                $id = (int) preg_replace('/\D/', '', $term);
                $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->when($id > 0, fn ($w) => $w->orWhere('id', $id)));
            })
            ->latest('created_at')
            ->latest('id');
    }

    public const TASK_COLUMNS = ['Key', 'Title', 'Project', 'Status', 'Priority', 'Assignee', 'Reporter', 'Created', 'Deadline', 'Completed', 'Hours (period)', 'Overdue'];

    public function taskRow(Task $task): array
    {
        $overdue = $task->status !== TaskStatus::Done && $task->deadline && $task->deadline->lt(today());

        return [
            $task->task_key,
            $task->title,
            $task->project?->name ?? '—',
            $task->status->label(),
            $task->priority?->label() ?? '—',
            $task->currentAssignee()?->name ?? '—',
            $task->creator?->name ?? '—',
            $task->created_at?->format('Y-m-d'),
            $task->deadline?->format('Y-m-d') ?? '—',
            $task->status === TaskStatus::Done && $task->completed_at ? substr((string) $task->completed_at, 0, 10) : '—',
            round((float) $task->period_hours, 2),
            $overdue ? 'Yes' : 'No',
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    public function taskRows(array $filters = []): array
    {
        return $this->tasksQuery($filters)->get()->map(fn (Task $task) => $this->taskRow($task))->all();
    }

    /* ------------------------------------------------------------------ */
    /* Projects */
    /* ------------------------------------------------------------------ */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function projects(): Collection
    {
        [$start, $end] = [$this->period->start, $this->period->end];
        $today = today();

        return Project::query()
            ->with('coordinator')
            ->withCount([
                'topLevelTasks as total_tasks',
                'topLevelTasks as done_tasks' => fn ($q) => $q->where('status', TaskStatus::Done),
                'topLevelTasks as open_tasks' => fn ($q) => $q->where('status', '!=', TaskStatus::Done),
                'topLevelTasks as overdue_tasks' => fn ($q) => $q->where('status', '!=', TaskStatus::Done)->whereNotNull('deadline')->whereDate('deadline', '<', $today),
                'tasks as created_in_period' => fn ($q) => $q->whereBetween('created_at', [$start, $end]),
                'tasks as completed_in_period' => fn ($q) => $q->whereHas('activities', fn ($a) => $a->where('type', TaskActivityType::Completed)->whereBetween('occurred_at', [$start, $end])),
            ])
            ->addSelect([
                'period_hours' => TaskTimeLog::query()->selectRaw('coalesce(sum(task_time_logs.hours), 0)')
                    ->join('tasks', 'tasks.id', '=', 'task_time_logs.task_id')
                    ->whereColumn('tasks.project_id', 'projects.id')
                    ->whereDate('task_time_logs.logged_date', '>=', $start)->whereDate('task_time_logs.logged_date', '<=', $end),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'progress' => $project->total_tasks > 0 ? (int) round($project->done_tasks / $project->total_tasks * 100) : 0,
                'hours' => (float) $project->period_hours,
            ])
            ->sortByDesc(fn ($row) => [$row['hours'], $row['project']->completed_in_period])
            ->values();
    }

    public const PROJECT_COLUMNS = ['Project', 'Status', 'Coordinator', 'Deadline', 'Tasks', 'Done', 'Open', 'Overdue', 'Progress %', 'Created (period)', 'Completed (period)', 'Hours (period)'];

    /**
     * @return list<list<mixed>>
     */
    public function projectRows(): array
    {
        return $this->projects()->map(fn (array $row) => [
            $row['project']->name,
            $row['project']->status->label(),
            $row['project']->coordinator?->name ?? '—',
            $row['project']->deadline?->format('Y-m-d') ?? '—',
            (int) $row['project']->total_tasks,
            (int) $row['project']->done_tasks,
            (int) $row['project']->open_tasks,
            (int) $row['project']->overdue_tasks,
            $row['progress'],
            (int) $row['project']->created_in_period,
            (int) $row['project']->completed_in_period,
            round($row['hours'], 2),
        ])->all();
    }

    /* ------------------------------------------------------------------ */
    /* People */
    /* ------------------------------------------------------------------ */

    /**
     * Everyone on the team (Super Admins only when they logged time in the period), with their numbers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function people(): Collection
    {
        [$start, $end] = [$this->period->start, $this->period->end];
        $periodLogs = fn () => TaskTimeLog::query()->whereColumn('user_id', 'users.id')->whereDate('logged_date', '>=', $start)->whereDate('logged_date', '<=', $end);

        $users = User::query()
            ->with('roles')
            ->where(fn (Builder $q) => $q
                ->withoutRole(Role::SuperAdmin->value)
                ->orWhereIn('id', $this->logs($this->period)->select('user_id')))
            ->addSelect([
                'period_hours' => $periodLogs()->selectRaw('coalesce(sum(hours), 0)'),
                'tasks_worked' => $periodLogs()->selectRaw('count(distinct task_id)'),
                'active_days' => $periodLogs()->selectRaw('count(distinct date(logged_date))'),
                'assigned_in_period' => TaskAssignment::query()->selectRaw('count(*)')->whereColumn('assigned_to', 'users.id')->whereBetween('assigned_at', [$start, $end]),
            ])
            ->orderBy('name')
            ->get();

        // Current-assignee based counts, resolved with the real "latest assignment" relation.
        $completedBy = Task::query()
            ->whereHas('activities', fn ($a) => $a->where('type', TaskActivityType::Completed)->whereBetween('occurred_at', [$start, $end]))
            ->with('currentAssignment')->get()
            ->countBy(fn (Task $task) => $task->currentAssignment?->assigned_to);

        $open = $this->openTasks()->with('currentAssignment')->get(['id', 'deadline', 'status']);
        $openBy = $open->countBy(fn (Task $task) => $task->currentAssignment?->assigned_to);
        $overdueBy = $open->filter(fn (Task $task) => $task->deadline && $task->deadline->lt(today()))
            ->countBy(fn (Task $task) => $task->currentAssignment?->assigned_to);

        return $users->map(fn (User $user) => [
            'user' => $user,
            'role' => Role::tryFrom($user->getRoleNames()->first() ?? '')?->label() ?? '—',
            'hours' => (float) $user->period_hours,
            'tasksWorked' => (int) $user->tasks_worked,
            'activeDays' => (int) $user->active_days,
            'assigned' => (int) $user->assigned_in_period,
            'completed' => (int) ($completedBy[$user->id] ?? 0),
            'open' => (int) ($openBy[$user->id] ?? 0),
            'overdue' => (int) ($overdueBy[$user->id] ?? 0),
        ])->sortByDesc(fn ($row) => [$row['hours'], $row['completed']])->values();
    }

    public const PEOPLE_COLUMNS = ['Name', 'Role', 'Department', 'Status', 'Hours (period)', 'Avg / active day', 'Active days', 'Tasks worked on', 'Assigned (period)', 'Completed (period)', 'Open now', 'Overdue now'];

    /**
     * @return list<list<mixed>>
     */
    public function peopleRows(): array
    {
        return $this->people()->map(fn (array $row) => [
            $row['user']->name,
            $row['role'],
            $row['user']->department ?: '—',
            ucfirst($row['user']->status),
            round($row['hours'], 2),
            $row['activeDays'] > 0 ? round($row['hours'] / $row['activeDays'], 2) : 0,
            $row['activeDays'],
            $row['tasksWorked'],
            $row['assigned'],
            $row['completed'],
            $row['open'],
            $row['overdue'],
        ])->all();
    }

    /**
     * Hours per person per day (per month for long periods): one row per person in people(), one cell per bucket.
     *
     * @return array{buckets: list<array{key: string, label: string, long: string}>, rows: Collection<int, array{user: User, cells: array<string, float>, total: float}>, totals: array<string, float>}
     */
    public function dailyHours(): array
    {
        $buckets = $this->period->buckets();

        $byUser = $this->logs($this->period)
            ->selectRaw('user_id, '.$this->period->bucketSql('logged_date').' as bucket, sum(hours) as total')
            ->groupBy('user_id', 'bucket')->toBase()->get()
            ->groupBy('user_id');

        $rows = $this->people()->map(function (array $person) use ($buckets, $byUser) {
            $logged = collect($byUser->get($person['user']->id, []))->pluck('total', 'bucket');
            $cells = collect($buckets)->mapWithKeys(fn ($b) => [$b['key'] => (float) ($logged[$b['key']] ?? 0)])->all();

            return ['user' => $person['user'], 'cells' => $cells, 'total' => array_sum($cells)];
        })->values();

        $totals = collect($buckets)->mapWithKeys(fn ($b) => [$b['key'] => $rows->sum(fn ($row) => $row['cells'][$b['key']])])->all();

        return ['buckets' => $buckets, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * The person × day grid as a table. "headers" keep full dates (Y-m-d / Y-m) for CSV and Excel; "labels"
     * are the short column labels for print (01, 02 … within one month; 28/09 across months; Jan, Feb … by
     * month), and "scope" names the span those short labels belong to, e.g. "October 2026".
     *
     * @return array{headers: list<string>, labels: list<string>, scope: string, rows: list<list<mixed>>}
     */
    public function dailyHoursTable(): array
    {
        $grid = $this->dailyHours();
        $byDay = $this->period->granularity() === 'day';
        $oneMonth = $this->period->start->isSameMonth($this->period->end);

        $rows = $grid['rows']->map(fn ($row) => [
            $row['user']->name,
            ...array_map(fn ($hours) => round($hours, 2), array_values($row['cells'])),
            round($row['total'], 2),
        ])->all();
        $rows[] = ['Total', ...array_map(fn ($hours) => round($hours, 2), array_values($grid['totals'])), round(array_sum($grid['totals']), 2)];

        $labels = array_map(function (array $bucket) use ($byDay, $oneMonth) {
            $date = CarbonImmutable::parse($byDay ? $bucket['key'] : $bucket['key'].'-01');

            return match (true) {
                ! $byDay => $date->format('M'),
                $oneMonth => $date->format('d'),
                default => $date->format('d/m'),
            };
        }, $grid['buckets']);

        return [
            'headers' => ['Person', ...array_column($grid['buckets'], 'key'), 'Total'],
            'labels' => ['Person', ...$labels, 'Total'],
            'scope' => $byDay && $oneMonth ? $this->period->start->format('F Y') : $this->period->label(),
            'rows' => $rows,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* One person */
    /* ------------------------------------------------------------------ */

    /**
     * A single person's report for the period: headline numbers, hours per day, every time log, and the
     * tasks they completed (tasks marked done in the period where they are the current assignee).
     */
    public function person(User $user): array
    {
        [$start, $end] = [$this->period->start, $this->period->end];
        $logs = $this->logs($this->period)->where('user_id', $user->id);

        $timeLogs = (clone $logs)->with('task.project')->orderByDesc('logged_date')->orderByDesc('id')->get();

        $byBucket = (clone $logs)->selectRaw($this->period->bucketSql('logged_date').' as bucket, sum(hours) as total')
            ->groupBy('bucket')->toBase()->pluck('total', 'bucket');
        $series = array_map(fn ($b) => $b + ['hours' => (float) ($byBucket[$b['key']] ?? 0)], $this->period->buckets());

        $completed = Task::query()
            ->with(['project', 'currentAssignment'])
            ->whereHas('activities', fn ($a) => $a->where('type', TaskActivityType::Completed)->whereBetween('occurred_at', [$start, $end]))
            ->addSelect([
                'completed_at' => TaskActivity::query()->selectRaw('max(occurred_at)')->whereColumn('task_id', 'tasks.id')->where('type', TaskActivityType::Completed),
                'my_hours' => TaskTimeLog::query()->selectRaw('coalesce(sum(hours), 0)')->whereColumn('task_id', 'tasks.id')->where('user_id', $user->id),
            ])
            ->get()
            ->filter(fn (Task $task) => $task->currentAssignment?->assigned_to === $user->id)
            ->sortByDesc('completed_at')
            ->values();

        $hours = (float) $timeLogs->sum('hours');
        $activeDays = $timeLogs->map(fn ($log) => $log->logged_date->toDateString())->unique()->count();

        // Expected hours: the working days elapsed so far in the period (days off and future days don't count) × the daily target.
        $elapsedEnd = $end->lessThan(now()) ? $end : now()->endOfDay();
        $workingDays = $elapsedEnd->lessThan($start) ? 0 : WorkSchedule::workingDaysBetween($start, $elapsedEnd);
        $target = WorkSchedule::dailyTarget() !== null ? WorkSchedule::dailyTarget() * $workingDays : null;
        $withDeadline = $completed->filter(fn (Task $task) => $task->deadline);
        $onTime = $withDeadline->filter(fn (Task $task) => substr((string) $task->completed_at, 0, 10) <= $task->deadline->toDateString());

        return [
            'user' => $user,
            'role' => Role::tryFrom($user->getRoleNames()->first() ?? '')?->label() ?? '—',
            'hours' => $hours,
            'activeDays' => $activeDays,
            'workingDays' => $workingDays,
            'targetHours' => $target,
            'targetRate' => $target ? (int) round($hours / $target * 100) : null,
            'avgPerActiveDay' => $activeDays > 0 ? $hours / $activeDays : 0.0,
            'tasksWorked' => $timeLogs->pluck('task_id')->unique()->count(),
            'completedCount' => $completed->count(),
            'onTimeRate' => $withDeadline->isNotEmpty() ? (int) round($onTime->count() / $withDeadline->count() * 100) : null,
            'series' => $series,
            'logsByDay' => $timeLogs->groupBy(fn ($log) => $log->logged_date->toDateString()),
            'timeLogs' => $timeLogs,
            'completed' => $completed,
        ];
    }

    /**
     * Export tables for one person.
     *
     * @return list<array{name: string, title: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    public function personTables(User $user): array
    {
        $person = $this->person($user);

        return [
            [
                'name' => 'Summary',
                'title' => $user->name.' — summary',
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['Name', $user->name],
                    ['Role', $person['role']],
                    ['Department', $user->department ?: '—'],
                    ['Hours logged', round($person['hours'], 2)],
                    ['Active days', $person['activeDays']],
                    ['Working days (so far)', $person['workingDays']],
                    ['Target hours', $person['targetHours'] !== null ? round($person['targetHours'], 2) : '—'],
                    ['Of target %', $person['targetRate'] ?? '—'],
                    ['Average per active day', round($person['avgPerActiveDay'], 2)],
                    ['Tasks worked on', $person['tasksWorked']],
                    ['Tasks completed', $person['completedCount']],
                    ['On-time completion %', $person['onTimeRate'] ?? '—'],
                ],
            ],
            [
                'name' => 'Daily hours',
                'title' => $user->name.' — hours by '.$this->period->granularity(),
                'headers' => ['Date', 'Hours'],
                'rows' => array_map(fn ($b) => [$b['long'], round($b['hours'], 2)], $person['series']),
            ],
            [
                'name' => 'Time logs',
                'title' => $user->name.' — daily time logs',
                'headers' => ['Date', 'Task', 'Title', 'Project', 'Hours', 'Note'],
                'rows' => $person['timeLogs']->map(fn ($log) => [
                    $log->logged_date->format('Y-m-d'),
                    $log->task?->task_key ?? '—',
                    $log->task?->title ?? '(deleted task)',
                    $log->task?->project?->name ?? '—',
                    round((float) $log->hours, 2),
                    $log->note ?: '',
                ])->all(),
            ],
            [
                'name' => 'Completed tasks',
                'title' => $user->name.' — tasks completed',
                'headers' => ['Completed', 'Task', 'Title', 'Project', 'Deadline', 'On time', 'Hours logged (all time)'],
                'rows' => $person['completed']->map(fn (Task $task) => [
                    substr((string) $task->completed_at, 0, 10),
                    $task->task_key,
                    $task->title,
                    $task->project?->name ?? '—',
                    $task->deadline?->format('Y-m-d') ?? '—',
                    $task->deadline ? (substr((string) $task->completed_at, 0, 10) <= $task->deadline->toDateString() ? 'Yes' : 'No') : '—',
                    round((float) $task->my_hours, 2),
                ])->all(),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Summary rows (for CSV / Excel) */
    /* ------------------------------------------------------------------ */

    /**
     * @return list<list<mixed>>
     */
    public function summaryRows(array $overview): array
    {
        $k = $overview['kpis'];
        $p = $overview['previous'];

        return [
            ['Metric', 'This period', 'Previous period'],
            ['Tasks created', $k['created'], $p['created']],
            ['Tasks completed', $k['completed'], $p['completed']],
            ['Hours logged', round($k['hours'], 2), round($p['hours'], 2)],
            ['Contributors', $k['contributors'], $p['contributors']],
            ['On-time completion %', $k['onTimeRate'] ?? '—', $p['onTimeRate'] ?? '—'],
            ['Open tasks (now)', $overview['openNow'], '—'],
            ['Overdue tasks (now)', $overview['overdueNow'], '—'],
        ];
    }

    /* ------------------------------------------------------------------ */

    private function logs(ReportPeriod $period): Builder
    {
        return TaskTimeLog::query()
            ->whereDate('task_time_logs.logged_date', '>=', $period->start)
            ->whereDate('task_time_logs.logged_date', '<=', $period->end);
    }

    private function completedActivities(ReportPeriod $period): Builder
    {
        return TaskActivity::query()
            ->where('task_activities.type', TaskActivityType::Completed)
            ->whereBetween('task_activities.occurred_at', [$period->start, $period->end]);
    }

    private function openTasks(): Builder
    {
        return Task::query()->where('status', '!=', TaskStatus::Done);
    }
}
