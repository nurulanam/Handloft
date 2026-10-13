<?php

use App\Services\TaskWorkflowService;
use App\Support\Duration;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The header's work-timer pill: the task you're working on and how long for, on every page, with a
 * stop button. Hidden while no timer is running.
 */
new class extends Component
{
    #[On('timer-changed')]
    public function refreshTimer(): void
    {
        // Re-render: a timer was started or stopped on the page.
    }

    public function stop(TaskWorkflowService $workflow): void
    {
        $timer = auth()->user()->runningTimer()->first();

        if (! $timer) {
            return;
        }

        $logged = $workflow->stopTimer($timer);

        $this->dispatch('timer-changed');
        $this->dispatch('notify', message: $logged > 0 ? 'Logged '.Duration::forHumans($logged).'.' : 'Under a minute, so nothing was logged.', type: $logged > 0 ? 'success' : 'info');
    }

    public function with(): array
    {
        return [
            'timer' => auth()->user()->runningTimer()->with('task')->first(),
        ];
    }
};
?>

<div>
    @if ($timer)
        <div class="flex items-center rounded-full bg-brand/[0.08] py-1 pl-1 pr-1 ring-1 ring-inset ring-brand/20">
            <a href="{{ route('tasks.show', $timer->task) }}" wire:navigate class="flex min-w-0 items-center gap-2 rounded-full py-0.5 pl-2 pr-2.5 text-xs font-semibold text-brand transition-colors hover:bg-brand/10" title="Working on {{ $timer->task->task_key }}: {{ $timer->task->title }}">
                <span class="relative flex size-2 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-brand"></span></span>
                <span class="hidden font-mono sm:inline">{{ $timer->task->task_key }}</span>
                <span wire:key="clock-{{ $timer->id }}" x-data="elapsedClock({{ (int) $timer->started_at->diffInSeconds(now(), true) }})" x-text="text" class="font-mono tabular-nums"></span>
            </a>
            <button type="button" wire:click="stop" wire:loading.attr="disabled" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-white transition hover:bg-brand/90 disabled:opacity-60" title="Stop and log the time">
                <span class="sr-only">Stop timer</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-2.5"><rect x="3" y="3" width="10" height="10" rx="2" /></svg>
            </button>
        </div>
    @endif
</div>
