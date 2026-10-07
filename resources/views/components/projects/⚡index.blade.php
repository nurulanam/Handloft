<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Projects')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' (all) or a ProjectStatus value. */
    #[Url(except: '')]
    public string $status = '';

    /** newest, deadline, progress-desc, progress-asc, name */
    #[Url(except: 'newest')]
    public string $sort = 'newest';

    /** 'grid' or 'list'. */
    #[Url(except: 'grid')]
    public string $view = 'grid';

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function with(): array
    {
        $statusCounts = Project::query()->selectRaw('status, count(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        $projects = Project::query()
            ->with(['creator', 'coordinator'])
            // Both counts are computed in this one query via withCount rather than per-project queries
            // (Project::taskCount()/completedCount() fall back to those only when not eager-loaded), so
            // this page stays a fixed number of queries no matter how many projects there are.
            ->withCount([
                'topLevelTasks as task_count',
                'topLevelTasks as completed_count' => fn ($q) => $q->where('status', TaskStatus::Done),
            ])
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->when(ProjectStatus::tryFrom($this->status), fn (Builder $q, ProjectStatus $status) => $q->where('status', $status))
            ->when($this->sort === 'deadline', fn (Builder $q) => $q->orderByRaw('deadline is null')->orderBy('deadline'))
            ->when($this->sort === 'name', fn (Builder $q) => $q->orderBy('name'))
            ->when(in_array($this->sort, ['progress-desc', 'progress-asc'], true), fn (Builder $q) => $q
                ->orderByRaw('case when task_count = 0 then 0 else completed_count * 1.0 / task_count end '.($this->sort === 'progress-desc' ? 'desc' : 'asc')))
            ->latest()
            ->paginate($this->view === 'list' ? 15 : 12);

        return [
            'projects' => $projects,
            'statusCounts' => $statusCounts,
            'totalProjects' => $statusCounts->sum(),
            'filtering' => trim($this->search) !== '' || $this->status !== '',
            'canCreate' => Gate::allows('create', Project::class),
        ];
    }
};
?>

@php
    $tiles = ['bg-brand/10 text-brand', 'bg-sky-100 text-sky-700', 'bg-violet-100 text-violet-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700', 'bg-teal-100 text-teal-700'];
    $tileFor = fn ($project) => $tiles[$project->id % count($tiles)];
    $due = function ($project) {
        if (! $project->deadline) {
            return ['No deadline', 'text-zinc-400', 'bg-zinc-50'];
        }
        if ($project->status !== \App\Enums\ProjectStatus::Active) {
            return [$project->deadline->format('d M Y'), 'text-zinc-500', 'bg-zinc-50'];
        }
        $days = (int) now()->startOfDay()->diffInDays($project->deadline->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => ['Overdue '.abs($days).'d', 'font-semibold text-red-600', 'bg-red-50'],
            $days === 0 => ['Due today', 'font-semibold text-amber-700', 'bg-amber-50'],
            $days <= 7 => ['Due in '.$days.'d', 'font-medium text-amber-700', 'bg-amber-50'],
            default => ['Due '.$project->deadline->format('d M'), 'text-zinc-600', 'bg-zinc-50'],
        };
    };
@endphp

<div class="space-y-4 sm:space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-semibold text-zinc-900 sm:text-2xl">
                Projects
                <span class="rounded-full bg-zinc-900/5 px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-600">{{ $totalProjects }}</span>
            </h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Group related tasks together and follow each project to the finish.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('projects.create') }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand/90">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                New project
            </a>
        @endif
    </div>

    {{-- Status chips --}}
    <div class="flex gap-2 overflow-x-auto pb-0.5 scrollbar-none sm:flex-wrap">
        @foreach (['' => ['All', $totalProjects, 'bg-zinc-400']] + collect(\App\Enums\ProjectStatus::cases())->mapWithKeys(fn ($case) => [$case->value => [$case->label(), (int) ($statusCounts[$case->value] ?? 0), match ($case) {
            \App\Enums\ProjectStatus::Active => 'bg-sky-500',
            \App\Enums\ProjectStatus::Completed => 'bg-brand',
            \App\Enums\ProjectStatus::Archived => 'bg-zinc-400',
        }]])->all() as $key => [$chipLabel, $chipCount, $dot])
            <button
                type="button"
                wire:click="$set('status', '{{ $key }}')"
                class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm transition {{ $status === $key ? 'border-brand bg-brand/5 text-brand' : 'border-zinc-200 bg-surface text-zinc-600 hover:border-zinc-300' }}"
            >
                @if ($key !== '')<span class="size-2 rounded-full {{ $dot }}"></span>@endif
                {{ $chipLabel }}
                <span class="font-semibold tabular-nums text-zinc-900">{{ $chipCount }}</span>
            </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-surface p-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search projects…" class="block w-full rounded-lg border border-zinc-300 bg-zinc-50/60 py-2 pl-9 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:bg-surface focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="sort" class="flex-1 rounded-lg border border-zinc-300 bg-surface py-2 pl-3 pr-8 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 sm:flex-none" aria-label="Sort">
                <option value="newest">Newest first</option>
                <option value="deadline">Deadline soonest</option>
                <option value="progress-desc">Most progress</option>
                <option value="progress-asc">Least progress</option>
                <option value="name">Name A–Z</option>
            </select>
            <div class="hidden rounded-lg border border-zinc-300 bg-surface p-0.5 sm:inline-flex" role="group" aria-label="Layout">
                @foreach (['grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>', 'list' => '<path d="M3 12h18M3 6h18M3 18h18"/>'] as $key => $glyph)
                    <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md p-1.5 {{ $view === $key ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-50' }}" title="{{ ucfirst($key) }} view">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">{!! $glyph !!}</svg>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-surface px-4 py-14 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><x-nav-icon name="projects" class="size-5" /></span>
            <p class="mt-3 text-sm font-medium text-zinc-900">{{ $filtering ? 'No projects match those filters' : 'No projects yet' }}</p>
            @if ($filtering)
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand hover:underline">Clear filters</button>
            @elseif ($canCreate)
                <a href="{{ route('projects.create') }}" wire:navigate class="mt-2 inline-block text-sm font-semibold text-brand hover:underline">Create the first project</a>
            @endif
        </div>
    @elseif ($view === 'list')
        {{-- List view (sm and up); phones always get cards. --}}
        <div class="hidden overflow-hidden rounded-xl border border-zinc-200 bg-surface sm:block">
            <table class="min-w-full divide-y divide-zinc-100">
                <thead class="bg-zinc-50/80">
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                        <th class="px-5 py-3">Project</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="w-56 px-4 py-3">Progress</th>
                        <th class="hidden px-4 py-3 lg:table-cell">Coordinator</th>
                        <th class="px-5 py-3">Deadline</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($projects as $project)
                        @php [$dueLabel, $dueTone] = $due($project); @endphp
                        <tr wire:key="row-{{ $project->id }}" class="transition-colors hover:bg-zinc-50/70">
                            <td class="px-5 py-3">
                                <a href="{{ route('projects.show', $project) }}" wire:navigate class="group flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold {{ $tileFor($project) }}">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-zinc-900 group-hover:text-brand">{{ $project->name }}</span>
                                        <span class="block truncate text-xs text-zinc-500">{{ $project->description ? \Illuminate\Support\Str::limit(strip_tags($project->description), 60) : 'No description' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $project->status->pillClasses() }}">{{ $project->status->label() }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-brand" style="width: {{ $project->progress_percent }}%"></div></div>
                                    <span class="w-16 text-right text-xs tabular-nums text-zinc-600">{{ $project->completed_count }}/{{ $project->task_count }} · {{ $project->progress_percent }}%</span>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                @if ($project->coordinator)
                                    <span class="flex items-center gap-2 text-sm text-zinc-700"><x-user-avatar :user="$project->coordinator" class="size-6 rounded-full text-[9px]" />{{ $project->coordinator->name }}</span>
                                @else
                                    <span class="text-sm text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-sm {{ $dueTone }}">{{ $dueLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Cards: the grid view from sm up, and always on phones. --}}
    @if ($projects->isNotEmpty())
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3 {{ $view === 'list' ? 'sm:hidden' : '' }}">
            @foreach ($projects as $project)
                @php [$dueLabel, $dueTone, $dueBg] = $due($project); @endphp
                <a wire:key="card-{{ $project->id }}" href="{{ route('projects.show', $project) }}" wire:navigate class="group flex flex-col rounded-2xl border border-zinc-200 bg-surface p-4 transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-lg hover:shadow-zinc-900/5 sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold {{ $tileFor($project) }}">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-semibold text-zinc-900 group-hover:text-brand">{{ $project->name }}</p>
                            <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium {{ $project->status->pillClasses() }}">{{ $project->status->label() }}</span>
                        </div>
                        {{-- Progress ring --}}
                        <div class="relative size-12 shrink-0" title="{{ $project->progress_percent }}% complete">
                            <svg viewBox="0 0 36 36" class="size-12 -rotate-90">
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="3.5" class="text-zinc-100"/>
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" class="{{ $project->progress_percent >= 100 ? 'text-brand' : 'text-emerald-500' }}" stroke-dasharray="97.4" stroke-dashoffset="{{ 97.4 - 97.4 * $project->progress_percent / 100 }}"/>
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-[11px] font-bold tabular-nums text-zinc-900">{{ $project->progress_percent }}%</span>
                        </div>
                    </div>

                    <p class="mt-3 line-clamp-2 min-h-10 text-sm text-zinc-500">{{ $project->description ? strip_tags($project->description) : 'No description yet.' }}</p>

                    <div class="mt-4 flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-50 px-2 py-1 text-zinc-600">
                            <x-nav-icon name="tasks" class="size-3.5 text-zinc-400" />
                            <span class="tabular-nums"><span class="font-semibold text-zinc-900">{{ $project->completed_count }}</span> of {{ $project->task_count }} done</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 {{ $dueBg }} {{ $dueTone }}">
                            <x-nav-icon name="calendar" class="size-3.5 opacity-70" />
                            {{ $dueLabel }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-2 border-t border-zinc-100 pt-3 text-xs text-zinc-500">
                        @if ($project->coordinator)
                            <x-user-avatar :user="$project->coordinator" class="size-6 rounded-full text-[9px]" />
                            <span class="truncate"><span class="text-zinc-400">Coordinator</span> {{ $project->coordinator->name }}</span>
                        @else
                            <x-user-avatar :user="$project->creator" class="size-6 rounded-full text-[9px]" />
                            <span class="truncate"><span class="text-zinc-400">Created by</span> {{ $project->creator->name }}</span>
                        @endif
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="ml-auto size-4 shrink-0 text-zinc-300 transition group-hover:translate-x-0.5 group-hover:text-brand"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{ $projects->links() }}
</div>
