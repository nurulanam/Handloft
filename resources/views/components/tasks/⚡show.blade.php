<?php

use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Task')] class extends Component
{
    public Task $task;

    public bool $showCompleteModal = false;

    public bool $showReassignModal = false;

    public string $actual_hours = '';

    public string $completion_note = '';

    public string $reassign_to = '';

    public function mount(Task $task): void
    {
        Gate::authorize('view', $task);

        $this->task = $task;
    }

    public function complete(TaskWorkflowService $workflow): void
    {
        Gate::authorize('complete', $this->task);

        $data = $this->validate([
            'actual_hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'completion_note' => ['nullable', 'string'],
        ]);

        $workflow->completeTask($this->task, auth()->user(), (float) $data['actual_hours'], $data['completion_note'] ?: null);

        $this->showCompleteModal = false;
        $this->task->refresh();
    }

    public function reassign(TaskWorkflowService $workflow): void
    {
        Gate::authorize('reassign', $this->task);

        $data = $this->validate([
            'reassign_to' => ['required', 'exists:users,id'],
        ]);

        $workflow->reassignTask($this->task, User::findOrFail($data['reassign_to']), auth()->user());

        $this->showReassignModal = false;
        $this->reassign_to = '';
        $this->task->refresh();
    }

    public function with(): array
    {
        return [
            'timeline' => $this->task->activities()->with('causer')->get(),
            'currentAssignee' => $this->task->currentAssignee(),
            'users' => User::query()->orderBy('name')->get(),
            'canReassign' => Gate::allows('reassign', $this->task),
            'canComplete' => Gate::allows('complete', $this->task),
        ];
    }
};
?>

<div class="max-w-3xl space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $task->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">Created by {{ $task->creator->name }} on {{ $task->created_at->format('d M Y') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $task->status->label() }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $task->priority->label() }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Assigned To</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $currentAssignee?->name ?? '—' }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Deadline</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $task->deadline?->format('d M Y') ?? '—' }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Category</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $task->category?->name ?? '—' }}</p>
        </div>
    </div>

    @if ($task->description)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Description</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $task->description }}</p>
        </div>
    @endif

    <div class="flex gap-3">
        @if ($canComplete && $task->status->value !== 'completed')
            <button type="button" wire:click="$set('showCompleteModal', true)" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                Complete Task
            </button>
        @endif

        @if ($canReassign && $task->status->value !== 'completed')
            <button type="button" wire:click="$set('showReassignModal', true)" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Reassign
            </button>
        @endif
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Activity Timeline</h2>
        <ol class="space-y-4 border-l border-slate-200 pl-4">
            @foreach ($timeline as $entry)
                <li>
                    <p class="text-sm text-slate-900">{{ $entry->description }}</p>
                    <p class="text-xs text-slate-500">{{ $entry->occurred_at->format('d M Y — h:i A') }}</p>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Complete Task modal --}}
    @if ($showCompleteModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-slate-900">Complete Task</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $task->title }}</p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700">Actual Hours Worked</label>
                    <input wire:model="actual_hours" type="number" step="0.1" min="0.1" max="24" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                    @error('actual_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700">Completion Note (Optional)</label>
                    <textarea wire:model="completion_note" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"></textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showCompleteModal', false)" class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</button>
                    <button type="button" wire:click="complete" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Complete Task</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reassign modal --}}
    @if ($showReassignModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-slate-900">Reassign Task</h3>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700">New Assignee</label>
                    <select wire:model="reassign_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                        <option value="">Select a team member</option>
                        @foreach ($users as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('reassign_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showReassignModal', false)" class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</button>
                    <button type="button" wire:click="reassign" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Reassign</button>
                </div>
            </div>
        </div>
    @endif
</div>
