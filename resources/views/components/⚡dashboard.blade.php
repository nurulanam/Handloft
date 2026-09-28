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
        return [
            'team' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'hoursToday' => (float) WorkHistory::whereDate('completed_date', now())->sum('actual_hours'),
                'hoursWeek' => (float) WorkHistory::whereBetween('completed_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('actual_hours'),
                'hoursMonth' => (float) WorkHistory::whereBetween('completed_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('actual_hours'),
            ],
            'tasks' => [
                'total' => Task::count(),
                'pending' => Task::where('status', TaskStatus::Pending)->count(),
                'inProgress' => Task::where('status', TaskStatus::InProgress)->count(),
                'completed' => Task::where('status', TaskStatus::Completed)->count(),
                'overdue' => Task::whereDate('deadline', '<', now())->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])->count(),
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

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-zinc-900">Team</h2>
            <dl class="mt-3 space-y-1 text-sm text-zinc-600">
                <div class="flex justify-between"><dt>Total Members</dt><dd class="font-medium text-zinc-900">{{ $team['total'] }}</dd></div>
                <div class="flex justify-between"><dt>Active</dt><dd class="font-medium text-zinc-900">{{ $team['active'] }}</dd></div>
                <div class="flex justify-between"><dt>Hours Today</dt><dd class="font-medium text-zinc-900">{{ number_format($team['hoursToday'], 1) }}</dd></div>
                <div class="flex justify-between"><dt>Hours This Week</dt><dd class="font-medium text-zinc-900">{{ number_format($team['hoursWeek'], 1) }}</dd></div>
                <div class="flex justify-between"><dt>Hours This Month</dt><dd class="font-medium text-zinc-900">{{ number_format($team['hoursMonth'], 1) }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-zinc-900">Tasks</h2>
            <dl class="mt-3 space-y-1 text-sm text-zinc-600">
                <div class="flex justify-between"><dt>Total</dt><dd class="font-medium text-zinc-900">{{ $tasks['total'] }}</dd></div>
                <div class="flex justify-between"><dt>Pending</dt><dd class="font-medium text-zinc-900">{{ $tasks['pending'] }}</dd></div>
                <div class="flex justify-between"><dt>In Progress</dt><dd class="font-medium text-zinc-900">{{ $tasks['inProgress'] }}</dd></div>
                <div class="flex justify-between"><dt>Completed</dt><dd class="font-medium text-zinc-900">{{ $tasks['completed'] }}</dd></div>
                <div class="flex justify-between"><dt>Overdue</dt><dd class="font-medium text-red-600">{{ $tasks['overdue'] }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-zinc-900">Leads</h2>
            <p class="mt-1 text-xs text-zinc-500">Total, new, hot, warm, cold, converted</p>
            <p class="mt-4 text-xs font-medium uppercase tracking-wide text-amber-600">Data arrives in later phases</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-zinc-900">Outreach</h2>
            <p class="mt-1 text-xs text-zinc-500">Messages, follow-ups, replies</p>
            <p class="mt-4 text-xs font-medium uppercase tracking-wide text-amber-600">Data arrives in later phases</p>
        </div>
    </div>
</div>
