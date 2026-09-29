<?php

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
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
    public string $tab = 'all';

    #[Url]
    public string $view = 'board';

    public ?int $submittingTaskId = null;

    public string $actual_hours = '';

    public string $submission_note = '';

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
        return [
            'all' => 'All Tasks',
            'assigned-to-me' => 'Assigned to Me',
        ];
    }

    public function moveTask(int $taskId, string $status): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        $newStatus = TaskStatus::tryFrom($status);

        if (! $newStatus || $newStatus === $task->status) {
            return;
        }

        if (Gate::denies('transitionStatus', [$task, $newStatus])) {
            $message = in_array($newStatus, $task->status->nextStatuses(), true)
                ? "Only {$this->requiredActorFor($task->status, $newStatus)} can move this task to {$newStatus->label()}."
                : "A task can't move from {$task->status->label()} to {$newStatus->label()}.";

            $this->dispatch('notify', message: $message, type: 'error');

            return;
        }

        // Submitting for QA testing always requires a reviewer to already be
        // assigned (someone has to approve/reject it) and logs the assignee's
        // hours worked so far, so open a modal instead of updating directly.
        if ($newStatus === TaskStatus::QaTesting) {
            if (! $task->qa_id) {
                $this->dispatch('notify', message: 'Assign a QA / Reviewer to this task before submitting it for QA testing.', type: 'error');

                return;
            }

            $this->submittingTaskId = $task->id;
            $this->actual_hours = (string) ($task->workHistory?->actual_hours ?? '');
            $this->submission_note = '';

            return;
        }

        if ($newStatus === TaskStatus::Done) {
            app(TaskWorkflowService::class)->markDone($task, auth()->user());

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

    /**
     * A human-readable description of who is allowed to make this specific
     * move, for the board's "move not possible" alert. Mirrors the pairing in
     * TaskPolicy::transitionStatus() — some source statuses (Rejected,
     * ReadyToDeploy) have a different actor depending on the target.
     */
    private function requiredActorFor(TaskStatus $from, TaskStatus $to): string
    {
        return match (true) {
            $from === TaskStatus::Todo, $from === TaskStatus::InProgress => 'the assignee',
            $from === TaskStatus::Rejected && $to === TaskStatus::QaTesting => 'the assignee',
            $from === TaskStatus::Rejected && $to === TaskStatus::ReadyToDeploy => 'the QA / Reviewer',
            $from === TaskStatus::QaTesting => 'the QA / Reviewer',
            $from === TaskStatus::ReadyToDeploy && $to === TaskStatus::Rejected => 'the QA / Reviewer',
            $from === TaskStatus::ReadyToDeploy && $to === TaskStatus::Done => 'the Reporter',
            default => 'no one',
        };
    }

    public function submitForQa(TaskWorkflowService $workflow): void
    {
        $task = Task::findOrFail($this->submittingTaskId);

        Gate::authorize('transitionStatus', [$task, TaskStatus::QaTesting]);

        $data = $this->validate([
            'actual_hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'submission_note' => ['nullable', 'string'],
        ]);

        $workflow->submitForQa($task, auth()->user(), (float) $data['actual_hours'], $data['submission_note'] ?: null);

        $this->submittingTaskId = null;
        $this->actual_hours = '';
        $this->submission_note = '';
    }

    public function cancelSubmitForQa(): void
    {
        $this->submittingTaskId = null;
        $this->actual_hours = '';
        $this->submission_note = '';
        $this->resetErrorBag();
    }

    public function openTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        $this->redirect(route('tasks.show', $task), navigate: true);
    }

    public function toggleStar(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        auth()->user()->starredTasks()->toggle($task->id);
    }

    /**
     * A left-border accent identifying the viewer's relationship to a task on
     * a shared board, so "your" cards stand out without a distracting full
     * background tint. A task can hold more than one role at once (e.g. you
     * both reported and self-assigned it) — priority favors whichever role is
     * most actionable for you right now.
     */
    private function connectionAccentClass(Task $task, User $user): string
    {
        return match (true) {
            $task->isAssignedTo($user) => 'border-l-4 border-l-brand',
            $task->isReviewedBy($user) => 'border-l-4 border-l-amber-400',
            $task->isReportedBy($user) => 'border-l-4 border-l-zinc-400',
            default => '',
        };
    }

    public function with(): array
    {
        $userId = auth()->id();

        $query = Task::query()
            ->with(['category', 'currentAssignment.assignedTo', 'creator', 'starredBy' => fn ($q) => $q->where('users.id', $userId)])
            ->latest();

        $query->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId));

        // Inside a project, tasks are scoped by project alone — no tab split,
        // since the project itself is the relevant scope.
        if (! $this->projectId) {
            match ($this->tab) {
                // "Assigned to Me" is really "my action queue": everything
                // currently assigned to me, plus tasks waiting on ME
                // specifically at their current stage — a task I'm QA-reviewing
                // while it's in QA Testing, or one I reported once it's Ready
                // to Deploy and needs my final sign-off.
                'assigned-to-me' => $query->where(function ($q) use ($userId) {
                    $q->whereHas('currentAssignment', fn ($q2) => $q2->where('assigned_to', $userId))
                        ->orWhere(fn ($q2) => $q2->where('qa_id', $userId)->where('status', TaskStatus::QaTesting))
                        ->orWhere(fn ($q2) => $q2->where('created_by', $userId)->where('status', TaskStatus::ReadyToDeploy));
                }),
                // "All Tasks" means everything the viewer is allowed to see:
                // literally everything for a view-all-tasks holder, otherwise
                // just the tasks they're connected to (matches TaskPolicy::view()).
                default => auth()->user()->can('view-all-tasks') ? $query : $query->where(function ($q) use ($userId) {
                    $q->where('created_by', $userId)
                        ->orWhereHas('currentAssignment', fn ($q2) => $q2->where('assigned_to', $userId))
                        ->orWhere('qa_id', $userId);
                }),
            };
        }

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

        <div class="flex items-center gap-3">
            @if ($projectId)
                <div class="flex rounded-lg border border-zinc-200 p-0.5">
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
            @endif

            <a href="{{ route('tasks.create', $projectId ? ['project' => $projectId] : []) }}" wire:navigate class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">
                Create Task
            </a>
        </div>
    </div>

    @unless ($projectId)
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
    @endunless

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
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
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
                                class="cursor-grab select-none rounded-lg border border-zinc-200 bg-white p-3 hover:border-brand/40 active:cursor-grabbing {{ $this->connectionAccentClass($task, auth()->user()) }}"
                            >
                                <div class="mb-1 flex items-center justify-between gap-1">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="inline-block rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                        @if ($task->parent_task_id)
                                            <span class="inline-block rounded-full bg-zinc-200 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-zinc-600">SUB-{{ $task->parent_task_id }}</span>
                                        @endif
                                    </div>

                                    <button type="button" x-on:mousedown.stop wire:click="toggleStar({{ $task->id }})" class="shrink-0 {{ $task->starredBy->isNotEmpty() ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                            <path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-sm font-medium text-zinc-900">{{ $task->title }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $task->currentAssignment?->assignedTo?->name ?? 'Unassigned' }}</p>

                                @if ($task->isConnectedTo(auth()->user()))
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @if ($task->isReportedBy(auth()->user()))
                                            <span class="rounded-full bg-zinc-800 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white">Reporter</span>
                                        @endif
                                        @if ($task->isAssignedTo(auth()->user()))
                                            <span class="rounded-full bg-zinc-800 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white">Assignee</span>
                                        @endif
                                        @if ($task->isReviewedBy(auth()->user()))
                                            <span class="rounded-full bg-zinc-800 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white">QA</span>
                                        @endif
                                    </div>
                                @endif

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

        {{-- Submit for QA Testing modal (opened by dropping a card on the QA Testing column) --}}
        @if ($submittingTaskId)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-zinc-900">Submit for QA Testing</h3>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700">Actual Hours Worked</label>
                        <input wire:model="actual_hours" type="number" step="0.1" min="0.1" max="24" autofocus class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @error('actual_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700">Note (Optional)</label>
                        <textarea wire:model="submission_note" rows="2" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelSubmitForQa" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</button>
                        <button type="button" wire:click="submitForQa" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">Submit</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="w-8 px-4 py-3"></th>
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
                        <tr class="cursor-pointer hover:bg-zinc-50 {{ $this->connectionAccentClass($task, auth()->user()) }}" onclick="window.location='{{ route('tasks.show', $task) }}'">
                            <td class="px-4 py-3">
                                <button type="button" onclick="event.stopPropagation()" wire:click="toggleStar({{ $task->id }})" class="{{ $task->starredBy->isNotEmpty() ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                        <path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" />
                                    </svg>
                                </button>
                            </td>
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
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">No tasks here yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tasks->links() }}
    @endif
</div>
