<?php

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Support\WorkSchedule;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Personal first: what's on the viewer's plate, what's waiting on them, and
 * their own logged hours. Holders of view-all-tasks also get a team overview
 * (pipeline, overdue, throughput), and view-all-work-history holders see who
 * logged the most time this week. Hours come from the per-day time logs, the
 * same source as the Work History page.
 */
new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    public function with(): array
    {
        $user = auth()->user();
        $today = now()->startOfDay();
        $weekStart = WorkSchedule::startOfWeek(now());
        $weekEnd = WorkSchedule::endOfWeek(now());

        $mine = fn (): Builder => Task::query()
            ->where('status', '!=', TaskStatus::Done)
            ->whereHas('currentAssignment', fn ($q) => $q->where('assigned_to', $user->id));

        $myLogs = fn (): Builder => TaskTimeLog::query()->where('user_id', $user->id);

        return [
            'greeting' => match (true) {
                now()->hour < 12 => 'Good morning',
                now()->hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'firstName' => \Illuminate\Support\Str::before($user->name.' ', ' '),
            'canCreateTask' => $user->can('create', Task::class),
            'canCreateProject' => $user->can('create', Project::class),
            'my' => [
                'open' => $mine()->count(),
                'dueThisWeek' => $mine()->whereNotNull('deadline')->whereDate('deadline', '>=', $today)->whereDate('deadline', '<=', $weekEnd)->count(),
                'overdue' => $mine()->whereNotNull('deadline')->whereDate('deadline', '<', $today)->count(),
                'hoursWeek' => (float) $myLogs()->whereDate('logged_date', '>=', $weekStart)->whereDate('logged_date', '<=', $weekEnd)->sum('hours'),
                'hoursToday' => (float) $myLogs()->whereDate('logged_date', $today)->sum('hours'),
                // The week's target: working days this week × the daily target (null when no target is set).
                'weekTarget' => WorkSchedule::dailyTarget() !== null ? WorkSchedule::dailyTarget() * WorkSchedule::workingDaysBetween($weekStart, $weekEnd) : null,
            ],
            'attention' => $this->attention($user, $today),
            'chart' => $this->hoursByDay($myLogs()),
            'team' => $user->can('view-all-tasks') ? $this->team($today, $weekStart, $weekEnd) : null,
            'contributors' => $user->can('view-all-work-history') ? $this->contributors($weekStart, $weekEnd) : null,
        ];
    }

    /**
     * The viewer's action queue: tasks assigned to them that are still theirs to
     * move (to do, in progress, rejected), tasks waiting on their QA review, and
     * tasks they reported that are ready for their sign-off. Overdue first.
     */
    private function attention(User $user, \Carbon\CarbonInterface $today): array
    {
        $query = Task::query()
            ->with('project')
            ->where(function (Builder $q) use ($user) {
                $q->where(fn (Builder $q) => $q
                    ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Rejected])
                    ->whereHas('currentAssignment', fn ($a) => $a->where('assigned_to', $user->id)))
                    ->orWhere(fn (Builder $q) => $q->where('qa_id', $user->id)->where('status', TaskStatus::QaTesting))
                    ->orWhere(fn (Builder $q) => $q->where('created_by', $user->id)->where('status', TaskStatus::ReadyToDeploy));
            });

        $total = (clone $query)->count();

        $items = $query
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (Task $task) use ($user, $today) {
                $days = $task->deadline ? (int) $today->diffInDays($task->deadline->copy()->startOfDay(), false) : null;

                return [
                    'task' => $task,
                    'reason' => match (true) {
                        $task->status === TaskStatus::QaTesting => 'Review',
                        $task->status === TaskStatus::ReadyToDeploy && $task->created_by === $user->id => 'Sign off',
                        $task->status === TaskStatus::Rejected => 'Fix',
                        default => null,
                    },
                    'due' => match (true) {
                        $days === null => ['No due date', 'text-zinc-400'],
                        $days < 0 => ['Overdue '.abs($days).'d', 'font-semibold text-red-600'],
                        $days === 0 => ['Due today', 'font-semibold text-amber-600'],
                        $days === 1 => ['Due tomorrow', 'text-amber-600'],
                        default => ['Due in '.$days.'d', 'text-zinc-500'],
                    },
                ];
            });

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Hours per day for the last 14 days, from one grouped query.
     */
    private function hoursByDay(Builder $logs): array
    {
        $from = now()->subDays(13)->startOfDay();

        $byDate = $logs->whereDate('logged_date', '>=', $from)
            ->selectRaw('date(logged_date) as day, sum(hours) as total')
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        $days = collect(range(13, 0))->map(function (int $daysAgo) use ($byDate) {
            $date = now()->subDays($daysAgo);

            return ['date' => $date, 'hours' => (float) ($byDate[$date->toDateString()] ?? 0)];
        });

        return ['days' => $days, 'max' => max($days->max('hours'), 1), 'total' => $days->sum('hours')];
    }

    private function team(\Carbon\CarbonInterface $today, \Carbon\CarbonInterface $weekStart, \Carbon\CarbonInterface $weekEnd): array
    {
        $counts = Task::query()->selectRaw('status, count(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        $pipeline = collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
            'status' => $status,
            'count' => (int) ($counts[$status->value] ?? 0),
            'bar' => match ($status) {
                TaskStatus::Todo => 'bg-zinc-300',
                TaskStatus::InProgress => 'bg-sky-400',
                TaskStatus::QaTesting => 'bg-amber-400',
                TaskStatus::Rejected => 'bg-red-400',
                TaskStatus::ReadyToDeploy => 'bg-lime-400',
                TaskStatus::Done => 'bg-brand',
            },
        ]);

        return [
            'active' => $pipeline->reject(fn ($row) => $row['status'] === TaskStatus::Done)->sum('count'),
            'overdue' => Task::query()->where('status', '!=', TaskStatus::Done)->whereNotNull('deadline')->whereDate('deadline', '<', $today)->count(),
            'completedWeek' => TaskActivity::query()
                ->where('type', TaskActivityType::Completed)
                ->whereBetween('occurred_at', [$weekStart, $weekEnd])
                ->distinct()
                ->count('task_id'),
            'hoursWeek' => (float) TaskTimeLog::query()->whereDate('logged_date', '>=', $weekStart)->whereDate('logged_date', '<=', $weekEnd)->sum('hours'),
            'pipeline' => $pipeline,
            'pipelineTotal' => max($pipeline->sum('count'), 1),
        ];
    }

    private function contributors(\Carbon\CarbonInterface $weekStart, \Carbon\CarbonInterface $weekEnd): \Illuminate\Support\Collection
    {
        $rows = TaskTimeLog::query()
            ->whereDate('logged_date', '>=', $weekStart)
            ->whereDate('logged_date', '<=', $weekEnd)
            ->selectRaw('user_id, sum(hours) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->toBase()
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');
        $max = max((float) $rows->max('total'), 0.01);

        return $rows
            ->filter(fn ($row) => $users->has($row->user_id))
            ->map(fn ($row) => ['user' => $users[$row->user_id], 'hours' => (float) $row->total, 'share' => (float) $row->total / $max * 100])
            ->values();
    }
};
?>

<div class="space-y-4 sm:space-y-6">
    {{-- Greeting + quick actions --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">{{ now()->format('l, d F') }}</p>
            <h1 class="mt-0.5 text-xl font-semibold text-zinc-900 sm:text-2xl">{{ $greeting }}, {{ $firstName }}</h1>
        </div>
        <div class="flex items-center gap-2">
            @if ($canCreateProject)
                <a href="{{ route('projects.create') }}" wire:navigate class="hidden items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:inline-flex">
                    <x-nav-icon name="projects" class="size-4" />
                    New project
                </a>
            @endif
            @if ($canCreateTask)
                <a href="{{ route('tasks.create') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand/90">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                    New task
                </a>
            @endif
        </div>
    </div>

    {{-- My work --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ([
            ['Assigned to me', $my['open'], 'open tasks', route('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list']), 'bg-brand/10 text-brand', 'tasks', false],
            ['Due this week', $my['dueThisWeek'], 'still open', route('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list']), 'bg-amber-100 text-amber-600', 'calendar', false],
            ['Overdue', $my['overdue'], $my['overdue'] > 0 ? 'needs attention' : 'all on track', route('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list']), $my['overdue'] > 0 ? 'bg-red-100 text-red-600' : 'bg-zinc-100 text-zinc-400', 'notifications', $my['overdue'] > 0],
            ['My hours this week', \App\Support\Duration::forHumans($my['hoursWeek']), ($my['weekTarget'] ? 'of '.\App\Support\Duration::forHumans($my['weekTarget']).' target · ' : '').\App\Support\Duration::forHumans($my['hoursToday']).' today', route('work-history.index'), 'bg-brand-lime/25 text-brand', 'work-history', false],
        ] as [$label, $value, $hint, $href, $tone, $icon, $alert])
            <a href="{{ $href }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-brand/30 hover:shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-zinc-500">{{ $label }}</span>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tone }}">
                        <x-nav-icon :name="$icon" class="size-4" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums {{ $alert ? 'text-red-600' : 'text-zinc-900' }}">{{ $value }}</p>
                <p class="text-xs text-zinc-400">{{ $hint }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
        {{-- Needs your attention --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-3.5 sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900">Needs your attention</h2>
                    <p class="text-xs text-zinc-500">Assigned to you, awaiting your review, or ready for your sign-off.</p>
                </div>
                @if ($attention['total'] > 0)
                    <a href="{{ route('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list']) }}" wire:navigate class="shrink-0 text-xs font-semibold text-brand hover:underline">View all{{ $attention['total'] > 6 ? ' ('.$attention['total'].')' : '' }}</a>
                @endif
            </div>

            <ul class="divide-y divide-zinc-100">
                @forelse ($attention['items'] as ['task' => $task, 'reason' => $reason, 'due' => [$dueLabel, $dueTone]])
                    <li>
                        <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-zinc-50 sm:px-5">
                            <span class="hidden shrink-0 rounded-full bg-violet-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-violet-700 sm:inline">{{ $task->task_key }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-900">{{ $task->title }}</span>
                                <span class="mt-0.5 flex items-center gap-1.5 text-xs">
                                    <span class="{{ $dueTone }}">{{ $dueLabel }}</span>
                                    @if ($task->project)
                                        <span class="text-zinc-300">·</span>
                                        <span class="truncate text-zinc-500">{{ $task->project->name }}</span>
                                    @endif
                                </span>
                            </span>
                            @if ($reason)
                                <span class="shrink-0 rounded-full bg-zinc-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">{{ $reason }}</span>
                            @endif
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center sm:px-5">
                        <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-brand/10 text-brand">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        </span>
                        <p class="mt-3 text-sm font-medium text-zinc-900">You're all caught up</p>
                        <p class="text-xs text-zinc-500">Nothing is waiting on you right now.</p>
                    </li>
                @endforelse
            </ul>
        </div>

        {{-- My hours, last 14 days --}}
        <div class="flex flex-col rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
            <div class="flex items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-zinc-900">My hours</h2>
                <span class="text-xs text-zinc-500">last 14 days · <span class="font-semibold text-zinc-900">{{ \App\Support\Duration::forHumans($chart['total']) }}</span></span>
            </div>
            <div class="mt-6 flex min-h-36 flex-1 items-stretch gap-1">
                @foreach ($chart['days'] as $day)
                    <div class="group relative flex flex-1 flex-col items-center justify-end">
                        <div class="pointer-events-none absolute -top-7 z-10 whitespace-nowrap rounded bg-zinc-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 transition-opacity group-hover:opacity-100">
                            {{ \App\Support\Duration::forHumans($day['hours']) }} · {{ $day['date']->format('j M') }}
                        </div>
                        <div
                            class="w-full rounded-t-[3px] transition-colors {{ $day['date']->isToday() ? 'bg-brand-lime group-hover:bg-lime-400' : 'bg-brand group-hover:bg-brand-lime' }}"
                            style="height: {{ $day['hours'] > 0 ? max(4, round($day['hours'] / $chart['max'] * 100)) : 2 }}%"
                        ></div>
                        <span class="mt-1.5 shrink-0 text-[10px] {{ $day['date']->isToday() ? 'font-semibold text-zinc-900' : 'text-zinc-400' }}">{{ $day['date']->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Team overview: holders of view-all-tasks only --}}
    @if ($team)
        <div class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Team overview</h2>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                @foreach ([
                    ['Active tasks', $team['active'], 'text-zinc-900'],
                    ['Overdue', $team['overdue'], $team['overdue'] > 0 ? 'text-red-600' : 'text-zinc-900'],
                    ['Completed this week', $team['completedWeek'], 'text-zinc-900'],
                    ['Team hours this week', \App\Support\Duration::forHumans($team['hoursWeek']), 'text-zinc-900'],
                ] as [$label, $value, $tone])
                    <div class="rounded-xl border border-zinc-200 bg-white p-4">
                        <p class="text-xs font-medium text-zinc-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums {{ $tone }}">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-4 sm:gap-6 {{ $contributors !== null ? 'lg:grid-cols-3' : '' }}">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 {{ $contributors !== null ? 'lg:col-span-2' : '' }}">
                    <h3 class="text-sm font-semibold text-zinc-900">Task pipeline</h3>
                    <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-zinc-100">
                        @foreach ($team['pipeline'] as $row)
                            @if ($row['count'] > 0)
                                <div class="{{ $row['bar'] }} h-full border-r-2 border-white last:border-r-0" style="width: {{ $row['count'] / $team['pipelineTotal'] * 100 }}%" title="{{ $row['status']->label() }}: {{ $row['count'] }}"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                        @foreach ($team['pipeline'] as $row)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="size-2.5 shrink-0 rounded-full {{ $row['bar'] }}"></span>
                                <span class="text-zinc-600">{{ $row['status']->label() }}</span>
                                <span class="ml-auto font-semibold tabular-nums text-zinc-900">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($contributors !== null)
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                        <h3 class="text-sm font-semibold text-zinc-900">Top contributors <span class="font-normal text-zinc-400">· this week</span></h3>
                        <ul class="mt-4 space-y-3">
                            @forelse ($contributors as $row)
                                <li>
                                    <a href="{{ route('work-history.show', $row['user']) }}" wire:navigate class="group flex items-center gap-3">
                                        <x-user-avatar :user="$row['user']" class="size-8 rounded-full text-[11px]" />
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-baseline justify-between gap-2">
                                                <span class="truncate text-sm font-medium text-zinc-900 group-hover:text-brand">{{ $row['user']->name }}</span>
                                                <span class="shrink-0 text-xs font-semibold tabular-nums text-zinc-700">{{ \App\Support\Duration::forHumans($row['hours']) }}</span>
                                            </span>
                                            <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-zinc-100">
                                                <span class="block h-full rounded-full bg-brand" style="width: {{ $row['share'] }}%"></span>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            @empty
                                <li class="py-4 text-center text-xs text-zinc-500">No time logged this week yet.</li>
                            @endforelse
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
