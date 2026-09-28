<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    //
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
        <p class="text-sm text-slate-500">Welcome back, {{ auth()->user()->name }}.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'Team' => 'Members, active count, hours today/week/month',
            'Tasks' => 'Total, pending, in progress, completed, overdue',
            'Leads' => 'Total, new, hot, warm, cold, converted',
            'Outreach' => 'Messages, follow-ups, replies',
        ] as $title => $description)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-amber-600">Data arrives in later phases</p>
            </div>
        @endforeach
    </div>
</div>
