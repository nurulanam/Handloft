<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkHistory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    public function with(): array
    {
        $totalTasks = Task::count();
        $pending = Task::where('status', TaskStatus::Todo)->count();
        $inProgress = Task::where('status', TaskStatus::InProgress)->count();
        $overdue = Task::whereDate('deadline', '<', now())->where('status', '!=', TaskStatus::Done)->count();

        $activeMembers = User::where('status', 'active')->count();
        $hoursMonth = (float) WorkHistory::whereBetween('completed_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('actual_hours');
        $completedThisWeek = Task::where('status', TaskStatus::Done)
            ->whereHas('workHistory', fn ($q) => $q->whereBetween('completed_date', [now()->startOfWeek(), now()->endOfWeek()]))
            ->count();

        $days = collect(range(13, 0))->map(function (int $daysAgo) {
            $date = now()->subDays($daysAgo);

            return [
                'date' => $date,
                'label' => $date->format('j M'),
                'hours' => (float) WorkHistory::whereDate('completed_date', $date)->sum('actual_hours'),
            ];
        });
        $maxDayHours = max($days->max('hours'), 1);

        return [
            'stats' => [
                'completedThisWeek' => $completedThisWeek,
                'avgHoursPerMember' => $activeMembers > 0 ? round($hoursMonth / $activeMembers, 1) : 0,
                'activeRate' => User::count() > 0 ? round($activeMembers / User::count() * 100) : 0,
            ],
            'histogram' => [
                'days' => $days,
                'max' => $maxDayHours,
            ],
            'team' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'hoursToday' => (float) WorkHistory::whereDate('completed_date', now())->sum('actual_hours'),
                'hoursWeek' => (float) WorkHistory::whereBetween('completed_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('actual_hours'),
                'hoursMonth' => (float) WorkHistory::whereBetween('completed_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('actual_hours'),
            ],
            'tasks' => [
                'total' => $totalTasks,
                'pending' => $pending,
                'inProgress' => $inProgress,
                'overdue' => $overdue,
            ],
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900">Dashboard</h1>
        <p class="text-sm text-zinc-500">Welcome back, {{ auth()->user()->name }}.</p>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z" />
                    </svg>
                </span>
                <div>
                    <p class="text-2xl font-semibold text-zinc-900">{{ $team['total'] }}</p>
                    <p class="text-xs text-zinc-500">Team Members</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ $team['active'] }} active right now</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                        <path fill-rule="evenodd" d="M5.5 17a4.5 4.5 0 01-1.44-8.765 4.5 4.5 0 018.302-3.046 3.5 3.5 0 014.504 4.272A4 4 0 0115 17H5.5zm3.75-2.75a.75.75 0 001.5 0V9.66l1.95 2.1a.75.75 0 101.1-1.02l-3.25-3.5a.75.75 0 00-1.1 0l-3.25 3.5a.75.75 0 101.1 1.02l1.95-2.1v4.59z" clip-rule="evenodd" />
                    </svg>
                </span>
                <div>
                    <p class="text-2xl font-semibold text-zinc-900">{{ $tasks['pending'] + $tasks['inProgress'] }}</p>
                    <p class="text-xs text-zinc-500">Active Tasks</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ $tasks['total'] }} total tasks tracked</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $tasks['overdue'] > 0 ? 'bg-red-100 text-red-600' : 'bg-zinc-100 text-zinc-400' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </span>
                <div>
                    <p class="text-2xl font-semibold {{ $tasks['overdue'] > 0 ? 'text-red-600' : 'text-zinc-900' }}">{{ $tasks['overdue'] }}</p>
                    <p class="text-xs text-zinc-500">Overdue Tasks</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ $tasks['overdue'] > 0 ? 'Needs attention' : 'Nothing overdue — nice work' }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-lime/20 text-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd" />
                    </svg>
                </span>
                <div>
                    <p class="text-2xl font-semibold text-zinc-900">{{ number_format($team['hoursMonth'], 1) }}<span class="text-sm font-normal text-zinc-400">h</span></p>
                    <p class="text-xs text-zinc-500">Logged This Month</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ number_format($team['hoursToday'], 1) }}h today · {{ number_format($team['hoursWeek'], 1) }}h this week</p>
        </div>
    </div>

    {{-- Secondary stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <p class="text-xs text-zinc-500">Completed This Week</p>
            <p class="mt-1 text-xl font-semibold text-zinc-900">{{ $stats['completedThisWeek'] }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <p class="text-xs text-zinc-500">Avg Hours / Active Member (Month)</p>
            <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format($stats['avgHoursPerMember'], 1) }}h</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <p class="text-xs text-zinc-500">Team Active Rate</p>
            <p class="mt-1 text-xl font-semibold text-zinc-900">{{ $stats['activeRate'] }}%</p>
        </div>
    </div>

    {{-- Hours histogram --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-zinc-900">Hours Logged — Last 14 Days</h2>

        <div class="mt-6 flex h-32 items-stretch gap-1.5">
            @foreach ($histogram['days'] as $day)
                <div class="group relative flex flex-1 flex-col items-center justify-end">
                    <div class="pointer-events-none absolute -top-8 z-10 whitespace-nowrap rounded bg-zinc-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 transition-opacity group-hover:opacity-100">
                        {{ number_format($day['hours'], 1) }}h · {{ $day['label'] }}
                    </div>
                    <div
                        class="w-full rounded-t bg-brand transition-colors group-hover:bg-brand-lime"
                        style="height: {{ $day['hours'] > 0 ? max(4, round($day['hours'] / $histogram['max'] * 100)) : 2 }}%"
                    ></div>
                    <span class="mt-1.5 shrink-0 text-[10px] text-zinc-400">{{ $day['date']->format('d') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Coming soon --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="relative overflow-hidden rounded-lg border border-zinc-200 bg-white p-5">
            <span class="absolute right-3 top-3 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">Phase 2</span>
            <span class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                    <path fill-rule="evenodd" d="M10 2c-2.236 0-4.43.18-6.57.524C1.993 2.755 1 4.014 1 5.426v5.148c0 1.412.993 2.67 2.43 2.902.848.137 1.705.248 2.57.331v3.443a.75.75 0 001.28.53l3.58-3.579a.78.78 0 01.527-.224 41.202 41.202 0 005.183-.5c1.437-.232 2.43-1.49 2.43-2.903V5.426c0-1.412-.993-2.67-2.43-2.902A41.289 41.289 0 0010 2zm0 7a1 1 0 100-2 1 1 0 000 2zM8 8a1 1 0 11-2 0 1 1 0 012 0zm5 1a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </span>
            <h2 class="mt-3 text-sm font-semibold text-zinc-900">Leads</h2>
            <p class="mt-1 text-xs text-zinc-500">Total, new, hot, warm, cold, converted — the CRM pipeline lands in Phase 2.</p>
        </div>

        <div class="relative overflow-hidden rounded-lg border border-zinc-200 bg-white p-5">
            <span class="absolute right-3 top-3 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">Phase 2</span>
            <span class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                    <path d="M3.478 2.404a.75.75 0 00-.926.941l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.404z" />
                </svg>
            </span>
            <h2 class="mt-3 text-sm font-semibold text-zinc-900">Outreach</h2>
            <p class="mt-1 text-xs text-zinc-500">Messages sent, follow-ups, and client replies — arrives alongside Leads in Phase 2.</p>
        </div>
    </div>
</div>
