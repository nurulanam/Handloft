<?php

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TaskWorkflowService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Tasks')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'my-tasks';

    #[Url]
    public string $view = 'board';

    public ?int $completingTaskId = null;

    public string $actual_hours = '';

    public string $completion_note = '';

    public ?int $projectId = null;

    public function mount(?int $projectId = null): void
    {
        Gate::authorize('viewAny', Task::class);

        $this->projectId = $projectId;
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
    }

    public function tabs(): array
    {
        $tabs = [
            'my-tasks' => 'My Tasks',
            'assigned-to-me' => 'Assigned to Me',
            'assigned-by-me' => 'Assigned by Me',
        ];

        if (auth()->user()->can('view-all-tasks')) {
            $tabs = ['all' => 'All Tasks'] + $tabs;
        }

        return $tabs;
    }

    public function moveTask(int $taskId, string $status): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        // Completion always requires actual hours, so open the Complete Task
        // modal in place instead of setting status directly or navigating away.
        if ($status === TaskStatus::Completed->value) {
            if (Gate::allows('complete', $task)) {
                $this->completingTaskId = $task->id;
                $this->actual_hours = '';
                $this->completion_note = '';
            }

            return;
        }

        if (! in_array($status, [TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::Cancelled->value], true)) {
            return;
        }

        if ($status === TaskStatus::Cancelled->value && Gate::denies('cancel', $task)) {
            return;
        }

        $newStatus = TaskStatus::from($status);

        if ($task->status === $newStatus) {
            return;
        }

        $task->update(['status' => $newStatus]);

        $task->activities()->create([
            'causer_id' => auth()->id(),
            'type' => TaskActivityType::StatusChanged,
            'description' => auth()->user()->name." moved this to {$newStatus->label()}",
            'occurred_at' => now(),
        ]);
    }

    public function completeTask(TaskWorkflowService $workflow): void
    {
        $task = Task::findOrFail($this->completingTaskId);

        Gate::authorize('complete', $task);

        $data = $this->validate([
            'actual_hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'completion_note' => ['nullable', 'string'],
        ]);

        $workflow->completeTask($task, auth()->user(), (float) $data['actual_hours'], $data['completion_note'] ?: null);

        $this->completingTaskId = null;
        $this->actual_hours = '';
        $this->completion_note = '';
    }

    public function cancelComplete(): void
    {
        $this->completingTaskId = null;
        $this->actual_hours = '';
        $this->completion_note = '';
        $this->resetErrorBag();
    }

    public function openTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        $this->redirect(route('tasks.show', $task), navigate: true);
    }

    public function with(): array
    {
        $userId = auth()->id();

        $query = Task::query()->with(['category', 'currentAssignment.assignedTo', 'creator'])->latest();

        $query->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId));

        match ($this->tab) {
            'all' => $query,
            'assigned-to-me' => $query->whereHas('currentAssignment', fn ($q) => $q->where('assigned_to', $userId)),
            'assigned-by-me' => $query->whereHas('currentAssignment', fn ($q) => $q->where('assigned_by', $userId)),
            default => $query->where('created_by', $userId),
        };

        if ($this->view === 'board') {
            $tasks = (clone $query)->limit(200)->get();

            return [
                'tasks' => null,
                'statuses' => TaskStatus::cases(),
                'board' => collect(TaskStatus::cases())->mapWithKeys(
                    fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status)->values()]
                ),
            ];
        }

        return [
            'tasks' => $query->paginate(15),
            'statuses' => TaskStatus::cases(),
            'board' => null,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        @unless ($projectId)
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900">Tasks</h1>
                <p class="text-sm text-zinc-500">Create, assign, and track work across the team.</p>
            </div>
        @else
            <h2 class="text-lg font-semibold text-zinc-900">Tasks</h2>
        @endunless

        <a href="{{ route('tasks.create', $projectId ? ['project' => $projectId] : []) }}" wire:navigate class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">
            Create Task
        </a>
    </div>

    <div class="flex items-center justify-between border-b border-zinc-200">
        <div class="flex gap-2">
            @foreach ($this->tabs() as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="border-b-2 px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="mb-2 flex rounded-lg border border-zinc-200 p-0.5">
            <button
                type="button"
                wire:click="$set('view', 'list')"
                class="rounded-md px-3 py-1 text-xs font-medium {{ $view === 'list' ? 'bg-brand text-white' : 'text-zinc-500 hover:text-zinc-700' }}"
            >
                List
            </button>
            <button
                type="button"
                wire:click="$set('view', 'board')"
                class="rounded-md px-3 py-1 text-xs font-medium {{ $view === 'board' ? 'bg-brand text-white' : 'text-zinc-500 hover:text-zinc-700' }}"
            >
                Board
            </button>
        </div>
    </div>

    @if ($view === 'board')
        <div
            x-data="{
                draggingId: null,
                overStatus: null,
                startX: 0,
                startY: 0,
                moved: false,
                startDrag(id, event) {
                    this.draggingId = id;
                    this.overStatus = null;
                    this.startX = event.clientX;
                    this.startY = event.clientY;
                    this.moved = false;
                },
                onMove(event) {
                    if (this.draggingId === null || this.moved) return;
                    if (Math.abs(event.clientX - this.startX) > 6 || Math.abs(event.clientY - this.startY) > 6) {
                        this.moved = true;
                    }
                },
                onUp() {
                    if (this.draggingId !== null) {
                        if (this.moved && this.overStatus) {
                            $wire.moveTask(this.draggingId, this.overStatus);
                        } else if (! this.moved) {
                            $wire.openTask(this.draggingId);
                        }
                    }
                    this.draggingId = null;
                    this.overStatus = null;
                    this.moved = false;
                },
            }"
            x-on:mousemove.window="onMove($event)"
            x-on:mouseup.window="onUp()"
            x-on:mouseleave="overStatus = null"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            :class="draggingId !== null && moved ? 'cursor-grabbing select-none' : ''"
        >
            @foreach ($statuses as $status)
                <div
                    class="flex flex-col rounded-lg border border-zinc-200 bg-zinc-100/60 transition-colors"
                    x-on:mouseenter="if (draggingId !== null) overStatus = '{{ $status->value }}'"
                    :class="draggingId !== null && moved && overStatus === '{{ $status->value }}' ? 'ring-2 ring-brand-lime' : ''"
                >
                    <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2">
                        <h3 class="text-sm font-semibold text-zinc-900">{{ $status->label() }}</h3>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $status->pillClasses() }}">{{ $board[$status->value]->count() }}</span>
                    </div>

                    <div class="flex-1 space-y-2 p-2">
                        @forelse ($board[$status->value] as $task)
                            <div
                                x-on:mousedown.prevent="startDrag({{ $task->id }}, $event)"
                                :class="draggingId === {{ $task->id }} && moved ? 'opacity-40' : ''"
                                class="cursor-grab select-none rounded-lg border border-zinc-200 bg-white p-3 hover:border-brand/40 active:cursor-grabbing"
                            >
                                <span class="mb-1 inline-block rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                <p class="text-sm font-medium text-zinc-900">{{ $task->title }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $task->currentAssignment?->assignedTo?->name ?? 'Unassigned' }}</p>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-xs text-zinc-400">{{ $task->deadline?->format('d M') ?? '—' }}</span>
                                    @if ($task->isOverdue())
                                        <span class="rounded-full bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-600">Overdue</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="px-2 py-4 text-center text-xs text-zinc-400">No tasks</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Complete Task modal (opened by dropping a card on the Completed column) --}}
        @if ($completingTaskId)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-zinc-900">Complete Task</h3>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700">Actual Hours Worked</label>
                        <input wire:model="actual_hours" type="number" step="0.1" min="0.1" max="24" autofocus class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @error('actual_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700">Completion Note (Optional)</label>
                        <textarea wire:model="completion_note" rows="2" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelComplete" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</button>
                        <button type="button" wire:click="completeTask" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">Complete Task</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Key</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Assigned To</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Deadline</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($tasks as $task)
                        <tr class="cursor-pointer hover:bg-zinc-50" onclick="window.location='{{ route('tasks.show', $task) }}'">
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full bg-violet-100 px-2 py-0.5 font-mono text-xs font-semibold text-violet-700">{{ $task->task_key }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900">{{ $task->title }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $task->currentAssignment?->assignedTo?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $task->priority->label() }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                                @if ($task->isOverdue())
                                    <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Overdue</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $task->deadline?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">No tasks here yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tasks->links() }}
    @endif
</div>
