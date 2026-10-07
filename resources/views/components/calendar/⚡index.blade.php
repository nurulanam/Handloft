<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\WorkSchedule;
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

    /**
     * Jump straight to a date picked on phones: "Y-m" from the month
     * picker (keeping the current day where it fits) or a full "Y-m-d".
     */
    public function jumpTo(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            [$year, $month] = array_map('intval', explode('-', $value));
            $anchor = Carbon::parse($this->cursor);

            $this->cursor = $anchor->setDate($year, $month, min($anchor->day, Carbon::create($year, $month, 1)->daysInMonth))->toDateString();

            return;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->cursor = Carbon::parse($value)->toDateString();
        }
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
                WorkSchedule::startOfWeek($anchor->copy()->startOfMonth()),
                WorkSchedule::endOfWeek($anchor->copy()->endOfMonth()),
            ],
            'week' => [
                WorkSchedule::startOfWeek($anchor),
                WorkSchedule::endOfWeek($anchor),
            ],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };
    }

    public function with(): array
    {
        [$start, $end] = $this->rangeBounds();
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

        $taskQuery->visibleTo(auth()->user());

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
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Calendar</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">
                @if ($dateType === 'deadline')
                    Task and project deadlines, at a glance.
                @else
                    Tasks and projects, by when they were created.
                @endif
            </p>
        </div>

        <div class="inline-flex shrink-0 rounded-lg border border-zinc-300 bg-surface p-0.5" role="group" aria-label="Show by">
            @foreach (['created' => 'Created', 'deadline' => 'Deadline'] as $key => $label)
                <button
                    type="button"
                    wire:click="setDateType('{{ $key }}')"
                    class="rounded-md px-2.5 py-1 text-xs font-medium sm:px-3 sm:text-sm {{ $dateType === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-surface p-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-4">
        <div class="flex items-center gap-1">
            <button type="button" wire:click="previous" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100" title="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
            </button>

            {{-- Phones: the range label is itself the jump-to control — the native month/date picker sits
                 invisibly over it (one tap opens it), replacing the month and year dropdowns. --}}
            <label class="relative flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 sm:flex-none sm:px-1">
                <span class="truncate text-sm font-semibold text-zinc-900">{{ $rangeLabel }}</span>
                @if ($view === 'day' && $anchor->isToday())
                    <span class="shrink-0 rounded-full bg-brand px-2 py-0.5 text-xs font-medium text-white">Today</span>
                @endif
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-zinc-400 sm:hidden"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                <input
                    type="{{ $view === 'month' ? 'month' : 'date' }}"
                    value="{{ $view === 'month' ? $anchor->format('Y-m') : $anchor->toDateString() }}"
                    wire:change="jumpTo($event.target.value)"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0 sm:hidden"
                    aria-label="Jump to date"
                    x-data @click="(() => { try { $el.showPicker() } catch (e) {} })()"
                >
            </label>

            <button type="button" wire:click="next" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100" title="Next">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" wire:click="goToToday" class="ml-1 shrink-0 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-50">Today</button>
        </div>

        <div class="flex items-center gap-2">
            <div class="grid flex-1 grid-cols-3 rounded-lg border border-zinc-300 bg-surface p-0.5 sm:inline-flex sm:flex-none">
                @foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $key => $label)
                    <button
                        type="button"
                        wire:click="setView('{{ $key }}')"
                        class="rounded-md px-3 py-1.5 text-sm font-medium sm:py-1 {{ $view === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <select wire:change="setMonth($event.target.value)" class="hidden rounded-lg border border-zinc-300 bg-surface px-2 py-1.5 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 sm:block" aria-label="Month">
                @foreach ($monthOptions as $value => $label)
                    <option value="{{ $value }}" @selected($anchor->month === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select wire:change="setYear($event.target.value)" class="hidden rounded-lg border border-zinc-300 bg-surface px-2 py-1.5 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 sm:block" aria-label="Year">
                @foreach ($yearOptions as $year)
                    <option value="{{ $year }}" @selected($anchor->year === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($view === 'day')
        <div class="rounded-lg border border-zinc-200 bg-surface p-5">
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
                    <div class="rounded-lg border bg-surface {{ $day->isToday() ? 'border-brand' : 'border-zinc-200' }}">
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

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-surface {{ $view === 'week' ? 'hidden sm:block' : '' }}">
            <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50">
                @foreach (WorkSchedule::weekdays() as $weekday)
                    <div class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 {{ $weekday['off'] ? 'text-zinc-400' : '' }}" title="{{ $weekday['off'] ? 'Day off' : '' }}">{{ $weekday['short'] }}</div>
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
                        class="min-h-16 cursor-pointer border-b border-r border-zinc-100 p-1 hover:bg-zinc-50 sm:min-h-28 sm:p-1.5 {{ ! $isCurrentMonth ? 'bg-zinc-50' : (WorkSchedule::isOffDay($day) ? 'bg-zinc-50/70' : 'bg-surface') }} {{ $isToday ? 'ring-2 ring-inset ring-brand' : '' }}"
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

    {{-- Day detail modal: frosted glass like the notification sheet — a full-screen sheet sliding up
         on phones, a centered card from sm up. It animates in on render, and closing plays the exit
         first, then tells the server. --}}
    @if ($showDayModal)
        <div
            x-data="{ show: false, close() { this.show = false; setTimeout(() => $wire.closeDayModal(), 250) } }"
            x-init="$nextTick(() => show = true)"
            @keydown.escape.window="close()"
            class="fixed inset-0 z-40 flex items-stretch justify-center sm:items-center sm:px-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ Carbon::parse($modalDate)->format('l, d F Y') }}"
        >
            <div
                x-show="show"
                x-transition:enter="transition-opacity duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity duration-250"
                x-transition:leave-end="opacity-0"
                @click="close()"
                class="absolute inset-0 bg-zinc-900/25 backdrop-blur-sm"
            ></div>

            <div
                x-show="show"
                x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                x-transition:leave="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] sm:duration-250"
                x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                class="relative flex h-dvh w-full flex-col overflow-hidden pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] sm:h-auto sm:max-h-[80vh] sm:max-w-lg sm:rounded-3xl sm:border sm:border-white/60 bg-surface/70 shadow-2xl shadow-zinc-900/20 backdrop-blur-xl backdrop-saturate-150"
            >
                @php $modalCount = $modalProjects->count() + $modalTasks->count(); @endphp
                <div class="flex items-center justify-between border-b border-zinc-900/5 px-5 pb-3 pt-4 sm:border-0">
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900">{{ Carbon::parse($modalDate)->format('l, d F') }}</h3>
                        <p class="text-xs text-zinc-500">{{ $modalCount === 0 ? 'Nothing scheduled' : $modalCount.' '.\Illuminate\Support\Str::plural('item', $modalCount).' · '.($dateType === 'deadline' ? 'by deadline' : 'by created date') }}</p>
                    </div>
                    <button type="button" @click="close()" class="rounded-full p-2 text-zinc-500 hover:bg-surface/60" title="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-3 pb-6 pt-3 sm:pb-4 sm:pt-0">
                    @if ($modalCount === 0)
                        <p class="py-8 text-center text-sm text-zinc-500">Nothing on this day.</p>
                    @endif

                    @foreach (['Projects' => $modalProjects, 'Tasks' => $modalTasks] as $heading => $group)
                        @continue($group->isEmpty())
                        <div>
                            <h4 class="px-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $heading }}</h4>
                            <div class="mt-2 space-y-2">
                                @foreach ($group as $event)
                                    <a href="{{ $event['url'] }}" wire:navigate class="flex items-center gap-3 rounded-2xl bg-surface/85 px-3 py-3 shadow-sm transition-colors hover:bg-surface active:bg-surface">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $event['type'] === 'project' ? 'bg-sky-100 text-sky-600' : 'bg-violet-100 text-violet-600' }}">
                                            <x-nav-icon :name="$event['type'] === 'project' ? 'projects' : 'tasks'" class="size-4" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-zinc-900">{{ $event['title'] }}</span>
                                            <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                                @if ($event['type'] === 'task')
                                                    <span class="font-mono text-[11px] font-semibold text-violet-700">{{ $event['icon_label'] }}</span>
                                                @endif
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $event['classes'] }}">{{ $event['status_label'] }}</span>
                                                @if ($event['is_overdue'])
                                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">Overdue</span>
                                                @endif
                                            </span>
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-zinc-400"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
