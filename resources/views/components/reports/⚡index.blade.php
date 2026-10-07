<?php

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use App\Reports\ReportBuilder;
use App\Reports\ReportPeriod;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Reports')] class extends Component
{
    use WithPagination;

    /** overview | tasks | projects | people */
    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** daily | weekly | monthly | yearly | custom */
    #[Url(except: 'monthly')]
    public string $period = 'monthly';

    /** Anchor date for day/week/month/year periods (Y-m-d). */
    #[Url(except: '')]
    public string $date = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    // Task filters
    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $assignee = '';

    #[Url(except: '')]
    public string $q = '';

    /** People tab: 'summary' table or 'daily' hours grid. */
    #[Url(except: 'summary')]
    public string $peopleView = 'summary';

    /** People tab: the id of the person drilled into ('' = everyone). */
    #[Url(except: '')]
    public string $person = '';

    public function mount(): void
    {
        Gate::authorize('view-reports');
    }

    private function current(): ReportPeriod
    {
        return ReportPeriod::fromInput($this->period, $this->date, $this->from, $this->to);
    }

    private function apply(ReportPeriod $period): void
    {
        $query = $period->toQuery();
        $this->period = $query['period'];
        $this->date = $query['date'] ?? '';
        $this->from = $query['from'] ?? '';
        $this->to = $query['to'] ?? '';
        $this->resetPage();
    }

    public function setPeriod(string $type): void
    {
        if (! in_array($type, ReportPeriod::TYPES, true)) {
            return;
        }

        $current = $this->current();

        if ($type === 'custom') {
            $this->apply(ReportPeriod::fromInput('custom', null, $current->start->toDateString(), $current->end->toDateString()));

            return;
        }

        // Keep the context: switching month → week lands on today's week if this month contains today, else its first week.
        $anchor = $current->containsToday() ? today()->toDateString() : $current->start->toDateString();
        $this->apply(ReportPeriod::fromInput($type, $anchor));
    }

    public function shift(int $direction): void
    {
        $current = $this->current();
        $this->apply($direction < 0 ? $current->previous() : $current->next());
    }

    public function goToCurrent(): void
    {
        $this->apply($this->period === 'custom'
            ? ReportPeriod::fromInput('custom', null, today()->startOfMonth()->toDateString(), today()->toDateString())
            : ReportPeriod::fromInput($this->period, today()->toDateString()));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'project', 'status', 'assignee', 'q', 'tab'], true)) {
            $this->resetPage();
        }

        if ($property === 'tab') {
            $this->person = '';
        }
    }

    public function clearTaskFilters(): void
    {
        $this->reset('project', 'status', 'assignee', 'q');
        $this->resetPage();
    }

    public function with(): array
    {
        $period = $this->current();
        $builder = new ReportBuilder($period);
        $filters = ['project' => $this->project, 'status' => $this->status, 'assignee' => $this->assignee, 'search' => $this->q];

        return [
            'range' => $period,
            'overview' => $this->tab === 'overview' ? $builder->overview() : null,
            'tasks' => $this->tab === 'tasks' ? $builder->tasksQuery($filters)->paginate(25) : null,
            'projectRows' => $this->tab === 'projects' ? $builder->projects() : null,
            'personReport' => $personReport = $this->tab === 'people' && ctype_digit($this->person) ? $builder->person(User::findOrFail((int) $this->person)) : null,
            'peopleRows' => $this->tab === 'people' && ! $personReport && $this->peopleView !== 'daily' ? $builder->people() : null,
            'dailyGrid' => $this->tab === 'people' && ! $personReport && $this->peopleView === 'daily' ? $builder->dailyHours() : null,
            'projectOptions' => $this->tab === 'tasks' ? Project::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'userOptions' => $this->tab === 'tasks' ? User::query()->withoutRole(Role::SuperAdmin->value)->orderBy('name')->get(['id', 'name']) : collect(),
            'exportQuery' => $period->toQuery()
                + ($this->tab === 'tasks' ? array_filter(['project' => $this->project, 'status' => $this->status, 'assignee' => $this->assignee, 'q' => $this->q]) : [])
                + ($personReport ? ['user' => $personReport['user']->id] : []),
            'exportSection' => $personReport ? 'person' : $this->tab,
            'canExport' => auth()->user()->can('export-data'),
            'taskFiltering' => $this->project !== '' || $this->status !== '' || $this->assignee !== '' || trim($this->q) !== '',
        ];
    }
};
?>

@php
    $h = fn (float $hours) => \App\Support\Duration::forHumans($hours);
    // Change vs the previous period: [text, tone]. "Higher is better" metrics go green up / red down.
    $delta = function ($now, $before, bool $higherIsBetter = true) {
        if ($now === null || $before === null) {
            return null;
        }
        if ((float) $before === 0.0) {
            return (float) $now === 0.0 ? ['No change', 'text-zinc-400'] : ['New', 'text-zinc-500'];
        }
        $pct = (int) round(($now - $before) / $before * 100);
        if ($pct === 0) {
            return ['No change', 'text-zinc-400'];
        }
        $good = $higherIsBetter ? $pct > 0 : $pct < 0;

        return [($pct > 0 ? '▲ ' : '▼ ').abs($pct).'%', $good ? 'text-emerald-700' : 'text-red-600'];
    };
    $tabs = ['overview' => 'Overview', 'tasks' => 'Tasks', 'projects' => 'Projects', 'people' => 'People'];
    $periods = ['daily' => 'Day', 'weekly' => 'Week', 'monthly' => 'Month', 'yearly' => 'Year', 'custom' => 'Custom'];
    $sectionLabel = $personReport ? $personReport['user']->name : $tabs[$tab];
@endphp

<div class="space-y-4 sm:space-y-6">
    {{-- Header + export --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Reports</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Throughput, time and workload across tasks, projects and people.</p>
        </div>

        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button type="button" @click="open = ! open" class="inline-flex items-center gap-2 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand/90" :aria-expanded="open">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
                Export
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 opacity-70"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
            </button>
            <div
                x-show="open"
                x-cloak
                @click.outside="open = false"
                x-transition:enter="transition duration-150 ease-out"
                x-transition:enter-start="-translate-y-1 scale-95 opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                class="absolute right-0 z-30 mt-2 w-72 origin-top-right overflow-hidden rounded-2xl border border-white/70 bg-white/85 p-1.5 shadow-2xl shadow-zinc-900/15 backdrop-blur-xl"
            >
                <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ $sectionLabel }} · {{ $range->label() }}</p>
                @if ($canExport)
                    @foreach ([
                        ['csv', $exportSection, 'CSV', 'Spreadsheet-friendly text file', 'bg-zinc-100 text-zinc-600'],
                        ['xlsx', $exportSection, 'Excel', $personReport ? 'Summary, daily hours, logs, completed tasks' : 'This tab as an .xlsx workbook', 'bg-emerald-100 text-emerald-700'],
                        ['xlsx', 'all', 'Excel — full report', 'Every section, one sheet each', 'bg-emerald-100 text-emerald-700'],
                    ] as [$format, $section, $label, $hint, $tone])
                        <a href="{{ route('reports.export', ['section' => $section, 'format' => $format] + $exportQuery) }}" @click="open = false" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-white">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold uppercase {{ $tone }}">{{ $format === 'xlsx' ? 'XLS' : 'CSV' }}</span>
                            <span><span class="block text-sm font-medium text-zinc-900">{{ $label }}</span><span class="block text-xs text-zinc-500">{{ $hint }}</span></span>
                        </a>
                    @endforeach
                    <div class="my-1 border-t border-zinc-900/5"></div>
                @endif
                @foreach ([[$exportSection, $personReport ? 'Print '.$personReport['user']->name."'s report" : 'Print this tab'], ['all', 'Print full report']] as [$section, $label])
                    <a href="{{ route('reports.print', ['section' => $section, 'autoprint' => 1] + $exportQuery) }}" target="_blank" rel="noopener" @click="open = false" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-white">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
                        </span>
                        <span><span class="block text-sm font-medium text-zinc-900">{{ $label }}</span><span class="block text-xs text-zinc-500">Print-ready A4 · save as PDF</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Period bar --}}
    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid grid-cols-5 rounded-lg border border-zinc-300 bg-white p-0.5 lg:inline-flex">
            @foreach ($periods as $key => $label)
                <button type="button" wire:click="setPeriod('{{ $key }}')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $period === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}">{{ $label }}</button>
            @endforeach
        </div>

        @if ($period === 'custom')
            <div class="grid grid-cols-2 gap-2 lg:flex lg:items-center">
                @foreach (['from' => 'From', 'to' => 'To'] as $field => $label)
                    <label class="relative block">
                        <span class="pointer-events-none absolute left-3 top-1.5 text-[10px] font-medium uppercase tracking-wide text-zinc-400">{{ $label }}</span>
                        <input wire:model.live="{{ $field }}" type="date" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 pb-1.5 pt-5 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 lg:w-40">
                    </label>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-1">
            <button type="button" wire:click="shift(-1)" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100" title="Previous period">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
            </button>
            <span class="min-w-0 flex-1 truncate px-1 text-center text-sm font-semibold text-zinc-900 lg:min-w-48">{{ $range->label() }}</span>
            <button type="button" wire:click="shift(1)" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100" title="Next period">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            </button>
            @unless ($range->containsToday())
                <button type="button" wire:click="goToCurrent" class="ml-1 shrink-0 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-50">{{ ['daily' => 'Today', 'weekly' => 'This week', 'monthly' => 'This month', 'yearly' => 'This year', 'custom' => 'This month'][$period] }}</button>
            @endunless
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 overflow-x-auto border-b border-zinc-200 scrollbar-none">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div wire:loading.class="opacity-60" class="transition-opacity">
    @if ($tab === 'overview')
        @php $k = $overview['kpis']; $p = $overview['previous']; $series = collect($overview['series']); @endphp

        {{-- Headline numbers vs the previous period --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            @foreach ([
                ['Tasks created', $k['created'], $delta($k['created'], $p['created']), null, true],
                ['Tasks completed', $k['completed'], $delta($k['completed'], $p['completed']), null, true],
                ['Hours logged', $h($k['hours']), $delta($k['hours'], $p['hours']), $k['contributors'].' '.\Illuminate\Support\Str::plural('contributor', $k['contributors']), true],
                ['On-time completion', $k['onTimeRate'] !== null ? $k['onTimeRate'].'%' : '—', $delta($k['onTimeRate'], $p['onTimeRate']), $k['completedWithDeadline'] > 0 ? 'of '.$k['completedWithDeadline'].' with a deadline' : 'no deadlines due', true],
            ] as [$label, $value, $change, $hint])
                <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                    <p class="text-xs font-medium text-zinc-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ $value }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs">
                        @if ($change)<span class="font-semibold {{ $change[1] }}">{{ $change[0] }}</span><span class="text-zinc-400">vs previous</span>@endif
                        @if ($hint)<span class="w-full text-zinc-400">{{ $hint }}</span>@endif
                    </p>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-sm sm:mt-4">
            <span class="rounded-xl border border-zinc-200 bg-white px-3 py-1.5 text-zinc-600">Open now <b class="ml-1 tabular-nums text-zinc-900">{{ $overview['openNow'] }}</b></span>
            <span class="rounded-xl border px-3 py-1.5 {{ $overview['overdueNow'] > 0 ? 'border-red-200 bg-red-50 text-red-700' : 'border-zinc-200 bg-white text-zinc-600' }}">Overdue now <b class="ml-1 tabular-nums">{{ $overview['overdueNow'] }}</b></span>
        </div>

        @if ($range->type === 'daily')
            <p class="mt-4 rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-6 text-center text-sm text-zinc-500">Trends need more than one day. Pick <button type="button" wire:click="setPeriod('weekly')" class="font-semibold text-brand hover:underline">a week</button>, <button type="button" wire:click="setPeriod('monthly')" class="font-semibold text-brand hover:underline">a month</button> or a year.</p>
        @else
            @php
                $maxHours = max($series->max('hours'), 0.01);
                $maxCount = max($series->max('created'), $series->max('completed'), 1);
                $labelEvery = (int) ceil(count($series) / 16);
            @endphp
            <div class="mt-4 grid gap-4 sm:mt-6 sm:gap-6 lg:grid-cols-2">
                {{-- Hours logged: one series. Tooltips are out of the layout until hover (so they never widen the page)
                     and anchor inward near the chart's edges. --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold text-zinc-900">Hours logged</h2>
                        <span class="text-xs text-zinc-500">by {{ $range->granularity() }} · total <b class="text-zinc-900">{{ $h($k['hours']) }}</b></span>
                    </div>
                    <div class="relative mt-6 h-44 border-b border-zinc-200">
                        <span class="absolute -top-4 left-0 text-[10px] text-zinc-400">{{ $h($maxHours) }}</span>
                        <div class="absolute inset-x-0 top-0 border-t border-dashed border-zinc-100"></div>
                        <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-zinc-100"></div>
                        <div class="relative flex h-full items-end gap-0.5">
                            @foreach ($series as $bucket)
                                <div class="group relative flex h-full flex-1 items-end">
                                    <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-[11px] text-white shadow-lg group-hover:block {{ $loop->index < $loop->count / 3 ? 'left-0' : ($loop->index >= $loop->count * 2 / 3 ? 'right-0' : 'left-1/2 -translate-x-1/2') }}">
                                        <b>{{ $h($bucket['hours']) }}</b> · {{ $bucket['long'] }}
                                    </div>
                                    <div class="w-full rounded-t-[4px] bg-[#16a34a] transition-opacity group-hover:opacity-80" style="height: {{ $bucket['hours'] > 0 ? max(2, $bucket['hours'] / $maxHours * 100) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-1.5 flex gap-0.5">
                        @foreach ($series as $i => $bucket)
                            <span class="flex-1 text-center text-[10px] text-zinc-400">{{ $i % $labelEvery === 0 ? $bucket['label'] : '' }}</span>
                        @endforeach
                    </div>
                </div>

                {{-- Created vs completed: two series, legend + direct tooltips --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold text-zinc-900">Created vs completed</h2>
                        <div class="flex items-center gap-3 text-xs text-zinc-600">
                            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#0284c7]"></span>Created <b class="tabular-nums text-zinc-900">{{ $k['created'] }}</b></span>
                            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#16a34a]"></span>Completed <b class="tabular-nums text-zinc-900">{{ $k['completed'] }}</b></span>
                        </div>
                    </div>
                    <div class="relative mt-6 h-44 border-b border-zinc-200">
                        <span class="absolute -top-4 left-0 text-[10px] text-zinc-400">{{ $maxCount }}</span>
                        <div class="absolute inset-x-0 top-0 border-t border-dashed border-zinc-100"></div>
                        <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-zinc-100"></div>
                        <div class="relative flex h-full items-end gap-1">
                            @foreach ($series as $bucket)
                                <div class="group relative flex h-full flex-1 items-end gap-0.5">
                                    <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-[11px] text-white shadow-lg group-hover:block {{ $loop->index < $loop->count / 3 ? 'left-0' : ($loop->index >= $loop->count * 2 / 3 ? 'right-0' : 'left-1/2 -translate-x-1/2') }}">
                                        {{ $bucket['long'] }} · <b>{{ $bucket['created'] }}</b> created · <b>{{ $bucket['completed'] }}</b> completed
                                    </div>
                                    <div class="w-1/2 rounded-t-[4px] bg-[#0284c7]" style="height: {{ $bucket['created'] > 0 ? max(2, $bucket['created'] / $maxCount * 100) : 0 }}%"></div>
                                    <div class="w-1/2 rounded-t-[4px] bg-[#16a34a]" style="height: {{ $bucket['completed'] > 0 ? max(2, $bucket['completed'] / $maxCount * 100) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-1.5 flex gap-1">
                        @foreach ($series as $i => $bucket)
                            <span class="flex-1 text-center text-[10px] text-zinc-400">{{ $i % $labelEvery === 0 ? $bucket['label'] : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-4 grid gap-4 sm:mt-6 sm:gap-6 lg:grid-cols-3">
            @php $statusMax = max($overview['statusBreakdown']->max('count'), 1); @endphp
            <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                <h2 class="text-sm font-semibold text-zinc-900">Where new tasks are now</h2>
                <p class="text-xs text-zinc-500">Current status of the {{ $k['created'] }} created this period</p>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($overview['statusBreakdown'] as $row)
                        <li class="text-xs">
                            <div class="flex justify-between"><span class="text-zinc-600">{{ $row['status']->label() }}</span><b class="tabular-nums text-zinc-900">{{ $row['count'] }}</b></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-[#16a34a]" style="width: {{ $row['count'] / $statusMax * 100 }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @foreach ([['Top projects by hours', $overview['topProjects'], 'No time logged against projects.'], ['Top people by hours', $overview['topPeople'], 'No time logged this period.']] as [$title, $rows, $empty])
                @php $rowMax = max($rows->max('hours'), 0.01); @endphp
                <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
                    <ul class="mt-4 space-y-2.5">
                        @forelse ($rows as $row)
                            <li class="text-xs">
                                <div class="flex justify-between gap-2"><span class="truncate text-zinc-600">{{ $row['name'] }}</span><b class="shrink-0 tabular-nums text-zinc-900">{{ $h($row['hours']) }}</b></div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-[#16a34a]" style="width: {{ $row['hours'] / $rowMax * 100 }}%"></div></div>
                            </li>
                        @empty
                            <li class="py-6 text-center text-xs text-zinc-400">{{ $empty }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    @elseif ($tab === 'tasks')
        <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-3 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input wire:model.live.debounce.300ms="q" type="search" placeholder="Search title or key…" class="block w-full rounded-lg border border-zinc-300 bg-zinc-50/60 py-2 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-brand focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
            </div>
            <div class="grid grid-cols-3 gap-2 lg:flex">
                <select wire:model.live="project" class="rounded-lg border border-zinc-300 bg-white py-2 pl-3 pr-8 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40" aria-label="Project">
                    <option value="">All projects</option>
                    <option value="none">No project</option>
                    @foreach ($projectOptions as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
                <select wire:model.live="status" class="rounded-lg border border-zinc-300 bg-white py-2 pl-3 pr-8 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\TaskStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                </select>
                <select wire:model.live="assignee" class="rounded-lg border border-zinc-300 bg-white py-2 pl-3 pr-8 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40" aria-label="Assignee">
                    <option value="">Anyone</option>
                    @foreach ($userOptions as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-sm text-zinc-500">
            <span><b class="text-zinc-900">{{ $tasks->total() }}</b> {{ \Illuminate\Support\Str::plural('task', $tasks->total()) }} with activity in {{ $range->label() }}</span>
            @if ($taskFiltering)<button type="button" wire:click="clearTaskFilters" class="font-semibold text-brand hover:underline">Clear filters</button>@endif
        </div>

        @if ($tasks->isEmpty())
            <p class="mt-3 rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-12 text-center text-sm text-zinc-500">No tasks were created, completed, due or worked on in this period{{ $taskFiltering ? ' with these filters' : '' }}.</p>
        @else
            <div class="mt-3 hidden overflow-x-auto rounded-xl border border-zinc-200 bg-white md:block">
                <table class="min-w-full divide-y divide-zinc-100 text-sm">
                    <thead class="bg-zinc-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Task</th><th class="px-3 py-3">Project</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Assignee</th>
                            <th class="px-3 py-3">Created</th><th class="px-3 py-3">Deadline</th><th class="px-3 py-3">Completed</th><th class="px-4 py-3 text-right">Hours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($tasks as $task)
                            @php $overdue = $task->status !== \App\Enums\TaskStatus::Done && $task->deadline && $task->deadline->lt(today()); @endphp
                            <tr wire:key="t-{{ $task->id }}" class="hover:bg-zinc-50/70">
                                <td class="max-w-72 px-4 py-2.5">
                                    <a href="{{ route('tasks.show', $task) }}" wire:navigate class="group flex items-center gap-2">
                                        <span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                        <span class="truncate font-medium text-zinc-900 group-hover:text-brand">{{ $task->title }}</span>
                                    </a>
                                </td>
                                <td class="max-w-40 truncate px-3 py-2.5 text-zinc-600">{{ $task->project?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span></td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-zinc-600">{{ $task->currentAssignee()?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-zinc-500">{{ $task->created_at->format('d M') }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 {{ $overdue ? 'font-semibold text-red-600' : 'text-zinc-500' }}">{{ $task->deadline?->format('d M') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-zinc-500">{{ $task->status === \App\Enums\TaskStatus::Done && $task->completed_at ? \Illuminate\Support\Carbon::parse($task->completed_at)->format('d M') : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-zinc-900">{{ (float) $task->period_hours > 0 ? $h((float) $task->period_hours) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 space-y-2 md:hidden">
                @foreach ($tasks as $task)
                    @php $overdue = $task->status !== \App\Enums\TaskStatus::Done && $task->deadline && $task->deadline->lt(today()); @endphp
                    <a wire:key="tm-{{ $task->id }}" href="{{ route('tasks.show', $task) }}" wire:navigate class="block rounded-xl border border-zinc-200 bg-white p-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <span class="min-w-0"><span class="font-mono text-[11px] font-semibold text-violet-700">{{ $task->task_key }}</span><span class="block truncate text-sm font-medium text-zinc-900">{{ $task->title }}</span></span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500">
                            <span>{{ $task->project?->name ?? 'No project' }}</span>
                            <span>{{ $task->currentAssignee()?->name ?? 'Unassigned' }}</span>
                            @if ($task->deadline)<span class="{{ $overdue ? 'font-semibold text-red-600' : '' }}">Due {{ $task->deadline->format('d M') }}</span>@endif
                            @if ((float) $task->period_hours > 0)<span class="font-semibold text-zinc-700">{{ $h((float) $task->period_hours) }}</span>@endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-4">{{ $tasks->links() }}</div>
        @endif
    @elseif ($tab === 'projects')
        @if ($projectRows->isEmpty())
            <p class="rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-12 text-center text-sm text-zinc-500">No projects yet.</p>
        @else
            <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 bg-white md:block">
                <table class="min-w-full divide-y divide-zinc-100 text-sm">
                    <thead class="bg-zinc-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Project</th><th class="px-3 py-3">Status</th><th class="w-48 px-3 py-3">Progress</th>
                            <th class="px-3 py-3 text-right">Created</th><th class="px-3 py-3 text-right">Completed</th><th class="px-3 py-3 text-right">Open</th><th class="px-3 py-3 text-right">Overdue</th><th class="px-4 py-3 text-right">Hours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($projectRows as $row)
                            @php $project = $row['project']; @endphp
                            <tr wire:key="p-{{ $project->id }}" class="hover:bg-zinc-50/70">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="font-medium text-zinc-900 hover:text-brand">{{ $project->name }}</a>
                                    <span class="block text-xs text-zinc-500">{{ $project->coordinator?->name ?? 'No coordinator' }}{{ $project->deadline ? ' · due '.$project->deadline->format('d M Y') : '' }}</span>
                                </td>
                                <td class="px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $project->status->pillClasses() }}">{{ $project->status->label() }}</span></td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-[#16a34a]" style="width: {{ $row['progress'] }}%"></div></div>
                                        <span class="w-20 text-right text-xs tabular-nums text-zinc-600">{{ $project->done_tasks }}/{{ $project->total_tasks }} · {{ $row['progress'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $project->created_in_period }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $project->completed_in_period }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $project->open_tasks }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums {{ $project->overdue_tasks > 0 ? 'font-semibold text-red-600' : 'text-zinc-700' }}">{{ $project->overdue_tasks }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-medium text-zinc-900">{{ $row['hours'] > 0 ? $h($row['hours']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-2 md:hidden">
                @foreach ($projectRows as $row)
                    @php $project = $row['project']; @endphp
                    <a wire:key="pm-{{ $project->id }}" href="{{ route('projects.show', $project) }}" wire:navigate class="block rounded-xl border border-zinc-200 bg-white p-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-zinc-900">{{ $project->name }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $project->status->pillClasses() }}">{{ $project->status->label() }}</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-[#16a34a]" style="width: {{ $row['progress'] }}%"></div></div><span class="text-xs tabular-nums text-zinc-600">{{ $row['progress'] }}%</span></div>
                        <div class="mt-2 grid grid-cols-4 gap-1 text-center text-[11px] text-zinc-500">
                            <span><b class="block text-sm text-zinc-900">{{ $project->created_in_period }}</b>Created</span>
                            <span><b class="block text-sm text-zinc-900">{{ $project->completed_in_period }}</b>Done</span>
                            <span><b class="block text-sm {{ $project->overdue_tasks > 0 ? 'text-red-600' : 'text-zinc-900' }}">{{ $project->overdue_tasks }}</b>Overdue</span>
                            <span><b class="block text-sm text-zinc-900">{{ $h($row['hours']) }}</b>Hours</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @elseif ($tab === 'people')
        @if ($personReport)
            @php $pr = $personReport; $series = collect($pr['series']); $prMax = max($series->max('hours'), 0.01); $labelEvery = (int) ceil(count($series) / 16); @endphp

            {{-- One person's report --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" wire:click="$set('person', '')" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
                    All people
                </button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5">
                <x-user-avatar :user="$pr['user']" class="size-14 rounded-2xl text-base" />
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-lg font-semibold text-zinc-900">{{ $pr['user']->name }}</h2>
                    <p class="text-sm text-zinc-500">{{ $pr['role'] }}{{ $pr['user']->department ? ' · '.$pr['user']->department : '' }} · {{ $range->label() }}</p>
                </div>
                <a href="{{ route('work-history.show', $pr['user']) }}" wire:navigate class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:w-auto">
                    <x-nav-icon name="work-history" class="size-4" /> Work history
                </a>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:mt-4 sm:gap-4 lg:grid-cols-5">
                @foreach ([
                    ['Hours logged', $h($pr['hours']), $pr['targetHours'] ? 'of '.$h($pr['targetHours']).' target · '.$pr['targetRate'].'%' : null],
                    ['Active days', $pr['activeDays'], 'of '.$pr['workingDays'].' working '.\Illuminate\Support\Str::plural('day', $pr['workingDays']).' so far'],
                    ['Avg / active day', $pr['activeDays'] > 0 ? $h($pr['avgPerActiveDay']) : '—', null],
                    ['Tasks worked on', $pr['tasksWorked'], 'with time logged'],
                    ['Tasks completed', $pr['completedCount'], $pr['onTimeRate'] !== null ? $pr['onTimeRate'].'% on time' : null],
                ] as [$label, $value, $hint])
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 {{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }}">
                        <p class="text-xs font-medium text-zinc-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900">{{ $value }}</p>
                        @if ($hint)<p class="text-xs text-zinc-400">{{ $hint }}</p>@endif
                    </div>
                @endforeach
            </div>

            @if ($range->type !== 'daily')
                <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 sm:mt-6 sm:p-5">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-zinc-900">Hours by {{ $range->granularity() }}</h3>
                        <span class="text-xs text-zinc-500">total <b class="text-zinc-900">{{ $h($pr['hours']) }}</b></span>
                    </div>
                    <div class="relative mt-6 h-40 border-b border-zinc-200">
                        <span class="absolute -top-4 left-0 text-[10px] text-zinc-400">{{ $h($prMax) }}</span>
                        <div class="absolute inset-x-0 top-0 border-t border-dashed border-zinc-100"></div>
                        <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-zinc-100"></div>
                        <div class="relative flex h-full items-end gap-0.5">
                            @foreach ($series as $bucket)
                                <div class="group relative flex h-full flex-1 items-end">
                                    <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-[11px] text-white shadow-lg group-hover:block {{ $loop->index < $loop->count / 3 ? 'left-0' : ($loop->index >= $loop->count * 2 / 3 ? 'right-0' : 'left-1/2 -translate-x-1/2') }}">
                                        <b>{{ $h($bucket['hours']) }}</b> · {{ $bucket['long'] }}
                                    </div>
                                    <div class="w-full rounded-t-[4px] bg-[#16a34a] transition-opacity group-hover:opacity-80" style="height: {{ $bucket['hours'] > 0 ? max(2, $bucket['hours'] / $prMax * 100) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-1.5 flex gap-0.5">
                        @foreach ($series as $i => $bucket)
                            <span class="flex-1 text-center text-[10px] text-zinc-400">{{ $i % $labelEvery === 0 ? $bucket['label'] : '' }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-4 grid gap-4 sm:mt-6 sm:gap-6 lg:grid-cols-3 lg:items-start">
                {{-- Daily time logs, grouped by day --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white lg:col-span-2">
                    <div class="flex items-baseline justify-between gap-2 border-b border-zinc-100 px-4 py-3.5 sm:px-5">
                        <h3 class="text-sm font-semibold text-zinc-900">Daily time logs</h3>
                        <span class="text-xs text-zinc-500">{{ $pr['timeLogs']->count() }} {{ \Illuminate\Support\Str::plural('entry', $pr['timeLogs']->count()) }}</span>
                    </div>
                    @forelse ($pr['logsByDay']->take(60) as $day => $logs)
                        <div wire:key="day-{{ $day }}">
                            <div class="flex items-center justify-between bg-zinc-50/80 px-4 py-2 text-xs sm:px-5">
                                <span class="font-semibold text-zinc-700">{{ \Illuminate\Support\Carbon::parse($day)->format('l, d M Y') }}</span>
                                <span class="font-semibold tabular-nums text-zinc-900">{{ $h((float) $logs->sum('hours')) }}</span>
                            </div>
                            <ul class="divide-y divide-zinc-100">
                                @foreach ($logs as $log)
                                    <li class="flex items-start gap-3 px-4 py-2.5 sm:px-5">
                                        <div class="min-w-0 flex-1">
                                            @if ($log->task)
                                                <a href="{{ route('tasks.show', $log->task) }}" wire:navigate class="group flex items-center gap-2 text-sm">
                                                    <span class="shrink-0 font-mono text-[11px] font-semibold text-violet-700">{{ $log->task->task_key }}</span>
                                                    <span class="truncate font-medium text-zinc-900 group-hover:text-brand">{{ $log->task->title }}</span>
                                                </a>
                                            @else
                                                <span class="text-sm text-zinc-400">(deleted task)</span>
                                            @endif
                                            <p class="mt-0.5 text-xs text-zinc-500">{{ $log->task?->project?->name ?? 'No project' }}@if ($log->note) · <span class="text-zinc-600">{{ $log->note }}</span>@endif</p>
                                        </div>
                                        <span class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900">{{ $h((float) $log->hours) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-zinc-500">No time logged in {{ $range->label() }}.</p>
                    @endforelse
                    @if ($pr['logsByDay']->count() > 60)
                        <p class="border-t border-zinc-100 px-4 py-3 text-center text-xs text-zinc-500 sm:px-5">Showing the latest 60 days with logs — export to see all {{ $pr['logsByDay']->count() }}.</p>
                    @endif
                </div>

                {{-- Completed tasks --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <div class="flex items-baseline justify-between gap-2 border-b border-zinc-100 px-4 py-3.5 sm:px-5">
                        <h3 class="text-sm font-semibold text-zinc-900">Completed tasks</h3>
                        <span class="text-xs text-zinc-500">{{ $pr['completedCount'] }}</span>
                    </div>
                    <ul class="divide-y divide-zinc-100">
                        @forelse ($pr['completed'] as $task)
                            @php $done = substr((string) $task->completed_at, 0, 10); $late = $task->deadline && $done > $task->deadline->toDateString(); @endphp
                            <li>
                                <a href="{{ route('tasks.show', $task) }}" wire:navigate class="block px-4 py-2.5 hover:bg-zinc-50 sm:px-5">
                                    <span class="flex items-center gap-2 text-sm">
                                        <span class="shrink-0 font-mono text-[11px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                        <span class="truncate font-medium text-zinc-900">{{ $task->title }}</span>
                                    </span>
                                    <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                                        <span>Done {{ \Illuminate\Support\Carbon::parse($done)->format('d M') }}</span>
                                        @if ($task->deadline)<span class="{{ $late ? 'font-semibold text-red-600' : 'text-emerald-700' }}">{{ $late ? 'Late' : 'On time' }}</span>@endif
                                        @if ((float) $task->my_hours > 0)<span>{{ $h((float) $task->my_hours) }} logged</span>@endif
                                    </span>
                                </a>
                            </li>
                        @empty
                            <li class="px-4 py-10 text-center text-sm text-zinc-500">Nothing completed in {{ $range->label() }}.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @else
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg border border-zinc-300 bg-white p-0.5" role="group" aria-label="People view">
                    @foreach (['summary' => 'Summary', 'daily' => 'Daily hours'] as $key => $label)
                        <button type="button" wire:click="$set('peopleView', '{{ $key }}')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $peopleView === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="text-xs text-zinc-500">Select a person for their daily logs and completed tasks.</p>
            </div>

            @if ($peopleView === 'daily')
                @php
                    $grid = $dailyGrid;
                    $cellMax = max(collect($grid['rows'])->flatMap(fn ($row) => $row['cells'])->max() ?? 0, 0.01);
                    $tone = fn (float $v) => match (true) {
                        $v <= 0 => '',
                        $v / $cellMax <= 0.25 => 'bg-emerald-50',
                        $v / $cellMax <= 0.5 => 'bg-emerald-100',
                        $v / $cellMax <= 0.75 => 'bg-emerald-200',
                        default => 'bg-emerald-300',
                    };
                    $byDay = $range->granularity() === 'day';
                @endphp
                {{-- Hours per person per day (per month for long periods), shaded light → dark by hours. --}}
                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                    <table class="min-w-full border-separate border-spacing-0 text-xs">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 border-b border-zinc-200 bg-zinc-50 px-4 py-2.5 text-left font-semibold text-zinc-600">Person</th>
                                @foreach ($grid['buckets'] as $bucket)
                                    @php $weekend = $byDay && \App\Support\WorkSchedule::isOffDay(\Illuminate\Support\Carbon::parse($bucket['key'])); @endphp
                                    <th class="min-w-11 border-b border-zinc-200 px-1 py-2 text-center font-medium {{ $weekend ? 'bg-zinc-100 text-zinc-400' : 'bg-zinc-50 text-zinc-600' }}" title="{{ $bucket['long'] }}">
                                        @if ($byDay)
                                            <span class="block text-[10px] font-normal">{{ \Illuminate\Support\Carbon::parse($bucket['key'])->format('D') }}</span>{{ \Illuminate\Support\Carbon::parse($bucket['key'])->format('j') }}
                                        @else
                                            {{ $bucket['label'] }}
                                        @endif
                                    </th>
                                @endforeach
                                <th class="sticky right-0 border-b border-l border-zinc-200 bg-zinc-50 px-3 py-2.5 text-right font-semibold text-zinc-700">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid['rows'] as $row)
                                <tr wire:key="g-{{ $row['user']->id }}" class="group cursor-pointer" wire:click="$set('person', '{{ $row['user']->id }}')">
                                    <td class="sticky left-0 z-10 border-b border-zinc-100 bg-white px-4 py-2 group-hover:bg-zinc-50">
                                        <span class="flex items-center gap-2 whitespace-nowrap">
                                            <x-user-avatar :user="$row['user']" class="size-6 rounded-full text-[9px]" />
                                            <span class="font-medium text-zinc-900 group-hover:text-brand">{{ $row['user']->name }}</span>
                                        </span>
                                    </td>
                                    @foreach ($grid['buckets'] as $bucket)
                                        @php $v = $row['cells'][$bucket['key']]; @endphp
                                        <td class="border-b border-zinc-100 px-1 py-2 text-center tabular-nums {{ $tone($v) }} {{ $v > 0 ? 'font-medium text-zinc-900' : 'text-zinc-300' }}" title="{{ $row['user']->name }} · {{ $bucket['long'] }} · {{ $h($v) }}">{{ $v > 0 ? rtrim(rtrim(number_format($v, 1), '0'), '.') : '·' }}</td>
                                    @endforeach
                                    <td class="sticky right-0 whitespace-nowrap border-b border-l border-zinc-100 bg-white px-3 py-2 text-right font-semibold tabular-nums text-zinc-900 group-hover:bg-zinc-50">{{ $h($row['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="sticky left-0 z-10 bg-zinc-50 px-4 py-2 font-semibold text-zinc-700">Total</td>
                                @foreach ($grid['buckets'] as $bucket)
                                    <td class="bg-zinc-50 px-1 py-2 text-center font-semibold tabular-nums text-zinc-700">{{ $grid['totals'][$bucket['key']] > 0 ? rtrim(rtrim(number_format($grid['totals'][$bucket['key']], 1), '0'), '.') : '' }}</td>
                                @endforeach
                                <td class="sticky right-0 whitespace-nowrap border-l border-zinc-200 bg-zinc-50 px-3 py-2 text-right font-semibold tabular-nums text-zinc-900">{{ $h(array_sum($grid['totals'])) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-2 text-xs text-zinc-500">Hours per {{ $range->granularity() }} (decimal). Darker cells mean more hours.{{ $byDay ? ' Days off are shaded.' : '' }}</p>
            @else
            @php $peopleMax = max($peopleRows->max('hours'), 0.01); @endphp
            @if ($peopleRows->isEmpty())
                <p class="rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-12 text-center text-sm text-zinc-500">No team members yet.</p>
            @else
            <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 bg-white md:block">
                <table class="min-w-full divide-y divide-zinc-100 text-sm">
                    <thead class="bg-zinc-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Person</th><th class="w-52 px-3 py-3">Hours</th><th class="px-3 py-3 text-right">Avg / day</th><th class="px-3 py-3 text-right">Tasks worked</th>
                            <th class="px-3 py-3 text-right">Assigned</th><th class="px-3 py-3 text-right">Completed</th><th class="px-3 py-3 text-right">Open</th><th class="px-4 py-3 text-right">Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($peopleRows as $row)
                            <tr wire:key="u-{{ $row['user']->id }}" wire:click="$set('person', '{{ $row['user']->id }}')" class="group cursor-pointer hover:bg-zinc-50/70">
                                <td class="px-4 py-2.5">
                                    <span class="flex items-center gap-2.5">
                                        <x-user-avatar :user="$row['user']" class="size-8 rounded-full text-[10px]" />
                                        <span class="min-w-0"><span class="block truncate font-medium text-zinc-900 group-hover:text-brand">{{ $row['user']->name }}</span><span class="block truncate text-xs text-zinc-500">{{ $row['role'] }}{{ $row['user']->department ? ' · '.$row['user']->department : '' }}</span></span>
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-[#16a34a]" style="width: {{ $row['hours'] / $peopleMax * 100 }}%"></div></div>
                                        <span class="w-16 text-right text-xs font-semibold tabular-nums text-zinc-900">{{ $h($row['hours']) }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-600">{{ $row['activeDays'] > 0 ? $h($row['hours'] / $row['activeDays']) : '—' }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $row['tasksWorked'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $row['assigned'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $row['completed'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-zinc-700">{{ $row['open'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $row['overdue'] > 0 ? 'font-semibold text-red-600' : 'text-zinc-700' }}">{{ $row['overdue'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-2 md:hidden">
                @foreach ($peopleRows as $row)
                    <button type="button" wire:key="um-{{ $row['user']->id }}" wire:click="$set('person', '{{ $row['user']->id }}')" class="block w-full rounded-xl border border-zinc-200 bg-white p-3.5 text-left">
                        <div class="flex items-center gap-2.5">
                            <x-user-avatar :user="$row['user']" class="size-9 rounded-full text-xs" />
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-zinc-900">{{ $row['user']->name }}</span><span class="block text-xs text-zinc-500">{{ $row['role'] }}</span></span>
                            <span class="text-sm font-semibold tabular-nums text-zinc-900">{{ $h($row['hours']) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-[#16a34a]" style="width: {{ $row['hours'] / $peopleMax * 100 }}%"></div></div>
                        <div class="mt-2 grid grid-cols-4 gap-1 text-center text-[11px] text-zinc-500">
                            <span><b class="block text-sm text-zinc-900">{{ $row['tasksWorked'] }}</b>Worked</span>
                            <span><b class="block text-sm text-zinc-900">{{ $row['completed'] }}</b>Done</span>
                            <span><b class="block text-sm text-zinc-900">{{ $row['open'] }}</b>Open</span>
                            <span><b class="block text-sm {{ $row['overdue'] > 0 ? 'text-red-600' : 'text-zinc-900' }}">{{ $row['overdue'] }}</b>Overdue</span>
                        </div>
                    </button>
                @endforeach
            </div>
            @endif
            @endif
        @endif
    @endif
    </div>
</div>
