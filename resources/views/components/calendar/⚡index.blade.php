<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Calendar')] class extends Component
{
    #[Url]
    public string $view = 'month';

    #[Url(as: 'date')]
    public string $cursor = '';

    /**
     * Which date drives what shows up on the calendar: when a task/project
     * was created, or its deadline. Defaults to "created" so the calendar
     * reads as an activity feed by default, with deadlines available as an
     * opt-in view rather than the default.
     */
    #[Url]
    public string $dateType = 'created';

    public bool $showDayModal = false;

    public string $modalDate = '';

    public function mount(): void
    {
        if (! $this->cursor) {
            $this->cursor = now()->toDateString();
        }
    }

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    public function setDateType(string $dateType): void
    {
        $this->dateType = $dateType;
    }

    public function openDay(string $date): void
    {
        $this->modalDate = $date;
        $this->showDayModal = true;
    }

    public function closeDayModal(): void
    {
        $this->showDayModal = false;
    }

    public function previous(): void
    {
        $this->cursor = match ($this->view) {
            'month' => Carbon::parse($this->cursor)->subMonthNoOverflow()->toDateString(),
            'week' => Carbon::parse($this->cursor)->subWeek()->toDateString(),
            default => Carbon::parse($this->cursor)->subDay()->toDateString(),
        };
    }

    public function next(): void
    {
        $this->cursor = match ($this->view) {
            'month' => Carbon::parse($this->cursor)->addMonthNoOverflow()->toDateString(),
            'week' => Carbon::parse($this->cursor)->addWeek()->toDateString(),
            default => Carbon::parse($this->cursor)->addDay()->toDateString(),
        };
    }

    public function goToToday(): void
    {
        $this->cursor = now()->toDateString();
    }

    public function setMonth(int $month): void
    {
        $anchor = Carbon::parse($this->cursor);
        $daysInMonth = Carbon::create($anchor->year, $month, 1)->daysInMonth;

        $this->cursor = $anchor->setDate($anchor->year, $month, min($anchor->day, $daysInMonth))->toDateString();
    }

    public function setYear(int $year): void
    {
        $anchor = Carbon::parse($this->cursor);
        $daysInMonth = Carbon::create($year, $anchor->month, 1)->daysInMonth;

        $this->cursor = $anchor->setDate($year, $anchor->month, min($anchor->day, $daysInMonth))->toDateString();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeBounds(): array
    {
        $anchor = Carbon::parse($this->cursor);

        return match ($this->view) {
            'month' => [
                $anchor->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
                $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY),
            ],
            'week' => [
                $anchor->copy()->startOfWeek(Carbon::SUNDAY),
                $anchor->copy()->endOfWeek(Carbon::SATURDAY),
            ],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };
    }

    public function with(): array
    {
        [$start, $end] = $this->rangeBounds();
        $userId = auth()->id();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $dateTypeLabel = $this->dateType === 'deadline' ? 'Deadline' : 'Created';

        $taskQuery = Task::query();

        if ($this->dateType === 'deadline') {
            $taskQuery->whereNotNull('deadline')
                ->whereDate('deadline', '>=', $startDate)
                ->whereDate('deadline', '<=', $endDate);
        } else {
            $taskQuery->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
        }

        // Mirrors TaskPolicy::view() — everything for a view-all-tasks
        // holder, otherwise only tasks the viewer is connected to.
        if (! auth()->user()->can('view-all-tasks')) {
            $taskQuery->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('currentAssignment', fn ($q2) => $q2->where('assigned_to', $userId))
                    ->orWhere('qa_id', $userId);
            });
        }

        $tasks = $taskQuery->get();

        // Project visibility is open to everyone (ProjectPolicy::viewAny).
        $projectQuery = Project::query();

        if ($this->dateType === 'deadline') {
            $projectQuery->whereNotNull('deadline')
                ->whereDate('deadline', '>=', $startDate)
                ->whereDate('deadline', '<=', $endDate);
        } else {
            $projectQuery->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
        }

        $projects = $projectQuery->get();

        $taskDate = fn (Task $task) => $this->dateType === 'deadline' ? $task->deadline : $task->created_at;

        $events = $tasks->map(fn (Task $task) => [
            'date' => $taskDate($task)->toDateString(),
            'type' => 'task',
            'icon_label' => $task->task_key,
            'title' => $task->title,
            'url' => route('tasks.show', $task),
            'classes' => $task->status->pillClasses(),
            'status_label' => $task->status->label(),
            'date_type_label' => $dateTypeLabel,
            'is_overdue' => $this->dateType === 'deadline' && $task->isOverdue(),
            'is_qa' => $task->status === \App\Enums\TaskStatus::QaTesting,
        ])->concat($projects->map(fn (Project $project) => [
            'date' => ($this->dateType === 'deadline' ? $project->deadline : $project->created_at)->toDateString(),
            'type' => 'project',
            'icon_label' => 'Project',
            'title' => $project->name,
            'url' => route('projects.show', $project),
            'classes' => $project->status->pillClasses(),
            'status_label' => $project->status->label(),
            'date_type_label' => $dateTypeLabel,
            'is_overdue' => $this->dateType === 'deadline'
                && $project->deadline->isPast()
                && ! in_array($project->status, [ProjectStatus::Completed, ProjectStatus::Archived], true),
            'is_qa' => false,
        ]))->sortBy('title')->values();

        $eventsByDate = $events->groupBy('date');

        $days = collect();

        if ($this->view !== 'day') {
            $cursorDate = $start->copy();

            while ($cursorDate->lte($end)) {
                $days->push($cursorDate->copy());
                $cursorDate->addDay();
            }
        }

        $anchor = Carbon::parse($this->cursor);
        $modalEvents = $this->showDayModal ? $eventsByDate->get($this->modalDate, collect()) : collect();

        return [
            'days' => $days,
            'eventsByDate' => $eventsByDate,
            'dayEvents' => $eventsByDate->get($anchor->toDateString(), collect()),
            'modalProjects' => $modalEvents->where('type', 'project'),
            'modalTasks' => $modalEvents->where('type', 'task'),
            'anchor' => $anchor,
            'monthOptions' => collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => Carbon::create(2000, $m, 1)->format('F')]),
            'yearOptions' => range(now()->year - 5, now()->year + 5),
            'rangeLabel' => match ($this->view) {
                'month' => $anchor->format('F Y'),
                'week' => $start->format('d M').' – '.$end->format('d M Y'),
                default => $anchor->format('l, d F Y'),
            },
        ];
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Calendar</h1>
            <p class="text-sm text-zinc-500">
                @if ($dateType === 'deadline')
                    Task and project deadlines, at a glance.
                @else
                    Tasks and projects, by when they were created.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="inline-flex rounded-lg border border-zinc-300 bg-white p-0.5">
                @foreach (['created' => 'Created', 'deadline' => 'Deadline'] as $key => $label)
                    <button
                        type="button"
                        wire:click="setDateType('{{ $key }}')"
                        class="rounded-md px-3 py-1 text-sm font-medium {{ $dateType === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="inline-flex rounded-lg border border-zinc-300 bg-white p-0.5">
                @foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $key => $label)
                    <button
                        type="button"
                        wire:click="setView('{{ $key }}')"
                        class="rounded-md px-3 py-1 text-sm font-medium {{ $view === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="previous" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100" title="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" wire:click="goToToday" class="rounded-lg border border-zinc-300 px-3 py-1 text-sm font-medium text-zinc-600 hover:bg-zinc-50">Today</button>
            <button type="button" wire:click="next" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100" title="Next">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            </button>
        </div>

        <h2 class="flex items-center gap-2 text-sm font-semibold text-zinc-900">
            {{ $rangeLabel }}
            @if ($view === 'day' && $anchor->isToday())
                <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-medium text-white">Today</span>
            @endif
        </h2>

        <div class="flex items-center gap-2">
            <select wire:change="setMonth($event.target.value)" class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                @foreach ($monthOptions as $value => $label)
                    <option value="{{ $value }}" @selected($anchor->month === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select wire:change="setYear($event.target.value)" class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                @foreach ($yearOptions as $year)
                    <option value="{{ $year }}" @selected($anchor->year === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($view === 'day')
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            @forelse ($dayEvents as $event)
                <a href="{{ $event['url'] }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-100 px-3 py-2.5 hover:border-brand/40 {{ ! $loop->last ? 'mb-2' : '' }}">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-700">{{ $event['icon_label'] }}</span>
                        <span class="truncate text-sm font-medium text-zinc-900">{{ $event['title'] }}</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500">{{ $event['date_type_label'] }}</span>
                        @if ($event['is_overdue'])
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">Overdue</span>
                        @endif
                        @if ($event['is_qa'])
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">QA</span>
                        @endif
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $event['classes'] }}">{{ $event['status_label'] }}</span>
                    </div>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-zinc-400">Nothing on this day.</p>
            @endforelse
        </div>
    @else
        {{-- Phones: week view as a vertical agenda — seven 50px columns can't
             fit readable items. Month view keeps the grid, with dots instead
             of chips (below). --}}
        @if ($view === 'week')
            <div class="space-y-2 sm:hidden">
                @foreach ($days as $day)
                    @php $agendaEvents = $eventsByDate->get($day->toDateString(), collect()); @endphp
                    <div class="rounded-lg border bg-white {{ $day->isToday() ? 'border-brand' : 'border-zinc-200' }}">
                        <div class="flex items-center justify-between px-3 py-2 {{ $agendaEvents->isNotEmpty() ? 'border-b border-zinc-100' : '' }}">
                            <span class="text-sm font-semibold {{ $day->isToday() ? 'text-brand' : 'text-zinc-900' }}">{{ $day->format('D, d M') }}</span>
                            @if ($day->isToday())
                                <span class="rounded-full bg-brand px-2 py-0.5 text-[10px] font-medium text-white">Today</span>
                            @elseif ($agendaEvents->isEmpty())
                                <span class="text-xs text-zinc-400">Nothing</span>
                            @endif
                        </div>
                        @foreach ($agendaEvents as $event)
                            <a href="{{ $event['url'] }}" wire:navigate class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-50 {{ ! $loop->last ? 'border-b border-zinc-100' : '' }}">
                                <span class="size-2 shrink-0 rounded-full {{ $event['type'] === 'project' ? 'bg-sky-400' : 'bg-violet-400' }}"></span>
                                <span class="min-w-0 flex-1 truncate text-sm text-zinc-900">{{ $event['title'] }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium {{ $event['classes'] }}">{{ $event['status_label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white {{ $view === 'week' ? 'hidden sm:block' : '' }}">
            <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                    <div class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    @php
                        $dayEventsForCell = $eventsByDate->get($day->toDateString(), collect());
                        $isCurrentMonth = $view !== 'month' || $day->isSameMonth($anchor);
                        $isToday = $day->isToday();
                        $visibleLimit = $view === 'week' ? 6 : 3;
                    @endphp
                    <div
                        wire:click="openDay('{{ $day->toDateString() }}')"
                        class="min-h-16 cursor-pointer border-b border-r border-zinc-100 p-1 hover:bg-zinc-50 sm:min-h-28 sm:p-1.5 {{ $isCurrentMonth ? 'bg-white' : 'bg-zinc-50' }} {{ $isToday ? 'ring-2 ring-inset ring-brand' : '' }}"
                        title="Show everything on {{ $day->format('d M Y') }}"
                    >
                        <span class="inline-flex size-6 items-center justify-center rounded-full text-xs font-medium {{ $isToday ? 'bg-brand text-white' : ($isCurrentMonth ? 'text-zinc-700' : 'text-zinc-300') }}">
                            {{ $day->format('j') }}
                        </span>

                        @if ($dayEventsForCell->isNotEmpty())
                            <div class="mt-1 flex flex-wrap items-center gap-1 px-0.5 sm:hidden">
                                @foreach ($dayEventsForCell->take(4) as $event)
                                    <span class="size-1.5 rounded-full {{ $event['type'] === 'project' ? 'bg-sky-400' : 'bg-violet-400' }}"></span>
                                @endforeach
                                @if ($dayEventsForCell->count() > 4)
                                    <span class="text-[9px] font-medium leading-none text-zinc-400">+{{ $dayEventsForCell->count() - 4 }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-1 hidden space-y-1 sm:block">
                            @foreach ($dayEventsForCell->take($visibleLimit) as $event)
                                <a href="{{ $event['url'] }}" wire:navigate @click.stop class="block truncate rounded px-1.5 py-0.5 text-[11px] font-medium {{ $event['classes'] }}" title="{{ $event['icon_label'] }} — {{ $event['title'] }}">
                                    {{ $event['title'] }}
                                </a>
                            @endforeach

                            @if ($dayEventsForCell->count() > $visibleLimit)
                                <span class="block text-[11px] font-medium text-zinc-400">
                                    +{{ $dayEventsForCell->count() - $visibleLimit }} more
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-4 text-xs text-zinc-500">
        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-violet-400"></span> Task</span>
        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-sky-400"></span> Project</span>
        <span class="sm:hidden">{{ $view === 'month' ? 'Tap a day to see everything on it.' : 'Tap an item to open it.' }}</span>
        <span class="hidden sm:inline">Colors reflect status — click any item to open it.</span>
    </div>

    {{-- Day detail modal --}}
    @if ($showDayModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4" wire:click.self="closeDayModal">
            <div class="max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-zinc-900">{{ Carbon::parse($modalDate)->format('l, d F Y') }}</h3>
                    <button type="button" wire:click="closeDayModal" class="rounded-full p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                    </button>
                </div>

                @if ($modalProjects->isEmpty() && $modalTasks->isEmpty())
                    <p class="mt-4 text-sm text-zinc-400">Nothing on this day.</p>
                @endif

                @if ($modalProjects->isNotEmpty())
                    <div class="mt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Projects</h4>
                        <div class="mt-2 space-y-2">
                            @foreach ($modalProjects as $event)
                                <a href="{{ $event['url'] }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-100 px-3 py-2.5 hover:border-brand/40">
                                    <span class="truncate text-sm font-medium text-zinc-900">{{ $event['title'] }}</span>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500">{{ $event['date_type_label'] }}</span>
                                        @if ($event['is_overdue'])
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">Overdue</span>
                                        @endif
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $event['classes'] }}">{{ $event['status_label'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($modalTasks->isNotEmpty())
                    <div class="mt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tasks</h4>
                        <div class="mt-2 space-y-2">
                            @foreach ($modalTasks as $event)
                                <a href="{{ $event['url'] }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-100 px-3 py-2.5 hover:border-brand/40">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-700">{{ $event['icon_label'] }}</span>
                                        <span class="truncate text-sm font-medium text-zinc-900">{{ $event['title'] }}</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500">{{ $event['date_type_label'] }}</span>
                                        @if ($event['is_overdue'])
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">Overdue</span>
                                        @endif
                                        @if ($event['is_qa'])
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">QA</span>
                                        @endif
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $event['classes'] }}">{{ $event['status_label'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
