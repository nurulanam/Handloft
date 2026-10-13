{{-- Inside the "Submit for QA Testing" window (App\Livewire\Concerns\SubmitsForQa): what the submitter
     has logged on the task. With nothing logged and no timer running, it asks for the time right here;
     a running timer is shown ticking and is logged on submit. --}}
@props(['task', 'error' => null])

@php
    $user = auth()->user();
    $timer = $task->timers()->where('user_id', $user->id)->first();
    $mine = (float) $task->timeLogs()->where('user_id', $user->id)->sum('hours');
    $total = (float) $task->timeLogs()->sum('hours');
@endphp

@if ($timer)
    <div class="mt-4 flex items-center gap-3 rounded-xl bg-brand/[0.06] px-3.5 py-3 ring-1 ring-inset ring-brand/15">
        <span class="relative flex size-2.5 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-60"></span><span class="relative inline-flex size-2.5 rounded-full bg-brand"></span></span>
        <p class="min-w-0 flex-1 text-sm text-zinc-700">
            Your timer is running: <span x-data="elapsedClock({{ (int) $timer->started_at->diffInSeconds(now(), true) }})" x-text="text" class="font-semibold tabular-nums text-zinc-900"></span>.
            <span class="block text-xs text-zinc-500">It stops and is added to your time log when you submit.</span>
        </p>
    </div>
@elseif ($mine <= 0)
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-3.5">
        <p class="flex items-start gap-2 text-sm font-medium text-amber-900">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 size-4 shrink-0 text-amber-500"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
            You haven't logged any time on this task yet.
        </p>
        <p class="mt-0.5 pl-6 text-xs text-amber-800/80">Add it here and it's logged with the submission. Leave it at 0 to skip.</p>

        <div class="mt-3 grid grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)] gap-2">
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Date</span>
                <input wire:model="qa_log_date" type="date" min="{{ $task->start_date?->toDateString() }}" max="{{ now()->toDateString() }}" class="field-input">
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Hours</span>
                <input wire:model="qa_log_hours" type="number" min="0" max="24" inputmode="numeric" class="field-input tabular-nums">
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Minutes</span>
                <input wire:model="qa_log_minutes" type="number" min="0" max="59" step="5" inputmode="numeric" class="field-input tabular-nums">
            </label>
        </div>
        @if ($error)
            <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
        @endif
    </div>
@endif

@if ($total > 0)
    <p class="mt-4 text-sm text-zinc-500">
        Time logged so far: <span class="font-semibold text-zinc-900">{{ \App\Support\Duration::forHumans($total) }}</span>
        @if ($mine > 0 && $mine < $total)
            <span class="text-zinc-400">(you: {{ \App\Support\Duration::forHumans($mine) }})</span>
        @endif
    </p>
@endif
