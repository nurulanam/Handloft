<?php

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Team')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' (all), or a Role value other than super-admin. */
    #[Url(except: '')]
    public string $role = '';

    /** '' (all), 'active' or 'inactive'. */
    #[Url(except: '')]
    public string $status = '';

    /** 'grid' or 'list'. */
    #[Url(except: 'grid')]
    public string $view = 'grid';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role', 'status');
        $this->resetPage();
    }

    /**
     * Super Admin accounts are system owners, not team members, so they're left off the page entirely.
     */
    private function team(): Builder
    {
        return User::query()->withoutRole(Role::SuperAdmin->value);
    }

    public function with(): array
    {
        $users = $this->team()
            ->with('roles')
            ->when(trim($this->search) !== '', function (Builder $query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('user_id', 'like', $term)
                    ->orWhere('department', 'like', $term));
            })
            ->when(in_array($this->role, [Role::Manager->value, Role::TeamMember->value], true), fn (Builder $q) => $q->role($this->role))
            ->when(in_array($this->status, ['active', 'inactive'], true), fn (Builder $q) => $q->where('status', $this->status))
            ->orderBy('name')
            ->paginate($this->view === 'list' ? 15 : 12);

        $ids = $users->pluck('id');

        // Open tasks currently assigned to each person on this page (current assignment = latest one).
        $openTasks = Task::query()
            ->where('status', '!=', TaskStatus::Done)
            ->whereHas('currentAssignment', fn ($q) => $q->whereIn('assigned_to', $ids))
            ->with('currentAssignment')
            ->get()
            ->countBy(fn (Task $task) => $task->currentAssignment->assigned_to);

        $hoursThisWeek = TaskTimeLog::query()
            ->whereIn('user_id', $ids)
            ->whereDate('logged_date', '>=', \App\Support\WorkSchedule::startOfWeek(now()))
            ->whereDate('logged_date', '<=', \App\Support\WorkSchedule::endOfWeek(now()))
            ->selectRaw('user_id, sum(hours) as total')
            ->groupBy('user_id')
            ->toBase()
            ->pluck('total', 'user_id');

        return [
            'users' => $users,
            'openTasks' => $openTasks,
            'hoursThisWeek' => $hoursThisWeek,
            'summary' => [
                'total' => $this->team()->count(),
                'active' => $this->team()->where('status', 'active')->count(),
                'inactive' => $this->team()->where('status', 'inactive')->count(),
                'managers' => $this->team()->role(Role::Manager->value)->count(),
                'members' => $this->team()->role(Role::TeamMember->value)->count(),
            ],
            'filtering' => trim($this->search) !== '' || $this->role !== '' || $this->status !== '',
            'canViewReports' => auth()->user()->can('view-reports'),
        ];
    }
};
?>

@php
    $roleLabel = fn ($user) => \App\Enums\Role::tryFrom($user->getRoleNames()->first() ?? '')?->label() ?? 'No role';
    $roleTone = fn ($user) => $user->hasRole(\App\Enums\Role::Manager->value) ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700';
@endphp

<div class="space-y-4 sm:space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-semibold text-zinc-900 sm:text-2xl">
                Team
                <span class="rounded-full bg-zinc-900/5 px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-600">{{ $summary['total'] }}</span>
            </h1>
            <p class="hidden text-sm text-zinc-500 sm:block">The people who hand work to each other — accounts, roles and status.</p>
        </div>
        <a href="{{ route('users.create') }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand/90">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
            Add member
        </a>
    </div>

    {{-- Summary --}}
    <div class="flex gap-2 overflow-x-auto pb-0.5 scrollbar-none sm:flex-wrap [&::-webkit-scrollbar]:hidden">
        @foreach ([
            ['Active', $summary['active'], 'bg-brand', 'active', null],
            ['Inactive', $summary['inactive'], 'bg-zinc-400', 'inactive', null],
            ['Managers', $summary['managers'], 'bg-violet-500', null, \App\Enums\Role::Manager->value],
            ['Team members', $summary['members'], 'bg-sky-500', null, \App\Enums\Role::TeamMember->value],
        ] as [$chipLabel, $chipCount, $dot, $chipStatus, $chipRole])
            @php $on = ($chipStatus && $status === $chipStatus) || ($chipRole && $role === $chipRole); @endphp
            <button
                type="button"
                wire:click="$set('{{ $chipStatus ? 'status' : 'role' }}', '{{ $on ? '' : ($chipStatus ?? $chipRole) }}')"
                class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm transition {{ $on ? 'border-brand bg-brand/5 text-brand' : 'border-zinc-200 bg-surface text-zinc-600 hover:border-zinc-300' }}"
            >
                <span class="size-2 rounded-full {{ $dot }}"></span>
                {{ $chipLabel }}
                <span class="font-semibold tabular-nums text-zinc-900">{{ $chipCount }}</span>
            </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-surface p-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, email, ID or department…" class="block w-full rounded-lg border border-zinc-300 bg-zinc-50/60 py-2 pl-9 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:bg-surface focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="role" class="flex-1 rounded-lg border border-zinc-300 bg-surface py-2 pl-3 pr-8 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 sm:flex-none" aria-label="Role">
                <option value="">All roles</option>
                <option value="{{ \App\Enums\Role::Manager->value }}">Managers</option>
                <option value="{{ \App\Enums\Role::TeamMember->value }}">Team members</option>
            </select>
            <select wire:model.live="status" class="flex-1 rounded-lg border border-zinc-300 bg-surface py-2 pl-3 pr-8 text-sm text-zinc-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 sm:flex-none" aria-label="Status">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
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

    @if ($users->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-surface px-4 py-14 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><x-nav-icon name="team" class="size-5" /></span>
            <p class="mt-3 text-sm font-medium text-zinc-900">{{ $filtering ? 'No one matches those filters' : 'No team members yet' }}</p>
            @if ($filtering)
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand hover:underline">Clear filters</button>
            @else
                <a href="{{ route('users.create') }}" wire:navigate class="mt-2 inline-block text-sm font-semibold text-brand hover:underline">Add the first member</a>
            @endif
        </div>
    @elseif ($view === 'list')
        {{-- List view (sm and up); phones always get cards. --}}
        <div class="hidden overflow-hidden rounded-xl border border-zinc-200 bg-surface sm:block">
            <table class="min-w-full divide-y divide-zinc-100">
                <thead class="bg-zinc-50/80">
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                        <th class="px-5 py-3">Member</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="hidden px-4 py-3 lg:table-cell">Department</th>
                        <th class="px-4 py-3">Open tasks</th>
                        <th class="px-4 py-3">This week</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($users as $user)
                        <tr wire:key="row-{{ $user->id }}" class="group transition-colors hover:bg-zinc-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$user" class="size-9 rounded-full text-xs" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-zinc-900">{{ $user->name }}</p>
                                        <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $roleTone($user) }}">{{ $roleLabel($user) }}</span></td>
                            <td class="hidden px-4 py-3 text-sm text-zinc-600 lg:table-cell">{{ $user->department ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm font-semibold tabular-nums text-zinc-900">{{ $openTasks[$user->id] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm tabular-nums text-zinc-600">{{ \App\Support\Duration::forHumans((float) ($hoursThisWeek[$user->id] ?? 0)) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $user->isActive() ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500' }}">
                                    <span class="size-1.5 rounded-full {{ $user->isActive() ? 'bg-brand' : 'bg-zinc-400' }}"></span>{{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($canViewReports)
                                        <a href="{{ route('reports.index', ['tab' => 'people', 'person' => $user->id]) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-brand" title="Report"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></a>
                                    @endif
                                    @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-brand" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Cards: the grid view from sm up, and always on phones. --}}
    @if ($users->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4 {{ $view === 'list' ? 'sm:hidden' : '' }}">
            @foreach ($users as $user)
                <div wire:key="card-{{ $user->id }}" class="group relative flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-surface transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-lg hover:shadow-zinc-900/5">
                    {{-- A calm tinted header (no bright lime), grey for inactive members. --}}
                    <div class="h-10 sm:h-14 {{ $user->isActive() ? 'bg-linear-to-br from-brand/15 via-emerald-50 to-zinc-50' : 'bg-linear-to-br from-zinc-200 to-zinc-50' }}"></div>
                    <div class="flex flex-1 flex-col px-3 pb-3 sm:px-4 sm:pb-4">
                        <div class="-mt-6 flex items-end justify-between sm:-mt-7">
                            <div class="relative">
                                <x-user-avatar :user="$user" class="size-12 rounded-xl text-sm shadow-md ring-4 ring-surface sm:size-14 sm:rounded-2xl sm:text-base" />
                                <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full ring-2 ring-surface sm:size-3.5 {{ $user->isActive() ? 'bg-brand' : 'bg-zinc-300' }}" title="{{ ucfirst($user->status) }}"></span>
                            </div>
                            <span class="hidden rounded-full px-2 py-0.5 text-[11px] font-semibold sm:inline {{ $roleTone($user) }}">{{ $roleLabel($user) }}</span>
                        </div>

                        <div class="mt-2.5 min-w-0 sm:mt-3">
                            <p class="truncate text-sm font-semibold text-zinc-900 sm:text-base">{{ $user->name }}</p>
                            <p class="truncate text-xs text-zinc-500 sm:text-sm">{{ $user->department ?: 'No department' }}</p>
                            <span class="mt-1.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold sm:hidden {{ $roleTone($user) }}">{{ $roleLabel($user) }}</span>
                        </div>

                        <dl class="mt-3 hidden space-y-1 text-xs text-zinc-500 sm:block">
                            <div class="flex items-center gap-2 truncate">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 text-zinc-400"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                <span class="truncate">{{ $user->email }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 text-zinc-400"><path d="M16 10h2M16 14h2M6.17 15a3 3 0 0 1 5.66 0"/><circle cx="9" cy="11" r="2"/><rect x="2" y="5" width="20" height="14" rx="2"/></svg>
                                <span class="font-mono">{{ $user->user_id ?: '—' }}</span>
                                @if ($user->joining_date)
                                    <span class="text-zinc-300">·</span>
                                    <span>Joined {{ $user->joining_date->format('M Y') }}</span>
                                @endif
                            </div>
                        </dl>

                        <div class="mt-3 grid grid-cols-2 gap-1.5 sm:mt-4 sm:gap-2">
                            <div class="rounded-lg bg-zinc-50 px-2 py-1.5 sm:rounded-xl sm:px-3 sm:py-2">
                                <p class="text-[10px] text-zinc-500 sm:text-[11px]"><span class="sm:hidden">Tasks</span><span class="hidden sm:inline">Open tasks</span></p>
                                <p class="text-sm font-semibold tabular-nums text-zinc-900 sm:text-base">{{ $openTasks[$user->id] ?? 0 }}</p>
                            </div>
                            <div class="rounded-lg bg-zinc-50 px-2 py-1.5 sm:rounded-xl sm:px-3 sm:py-2">
                                <p class="text-[10px] text-zinc-500 sm:text-[11px]"><span class="sm:hidden">Week</span><span class="hidden sm:inline">This week</span></p>
                                <p class="truncate text-sm font-semibold tabular-nums text-zinc-900 sm:text-base">{{ \App\Support\Duration::forHumans((float) ($hoursThisWeek[$user->id] ?? 0)) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex gap-1.5 border-t border-zinc-100 pt-2.5 sm:mt-4 sm:gap-2 sm:pt-3">
                            @can('update', $user)
                            <a href="{{ route('users.edit', $user) }}" wire:navigate class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-700 hover:border-brand/40 hover:bg-brand/5 hover:text-brand sm:px-3 sm:text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden size-3.5 sm:block"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                                Edit
                            </a>
                            @endcan
                            @if ($canViewReports)
                                <a href="{{ route('reports.index', ['tab' => 'people', 'person' => $user->id]) }}" wire:navigate class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-700 hover:border-brand/40 hover:bg-brand/5 hover:text-brand sm:px-3 sm:text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden size-3.5 sm:block"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                                    Report
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{ $users->links() }}
</div>
