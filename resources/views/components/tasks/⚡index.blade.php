<?php

use App\Livewire\Concerns\TogglesStars;
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
    use WithPagination, TogglesStars;

    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $view = 'board';

    public ?int $submittingTaskId = null;

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

        // Allowed, but not as this person's step in the workflow: a Super Admin override, applied
        // directly (no QA submission window, no reviewer required).
        if (Gate::denies('followWorkflow', [$task, $newStatus])) {
            app(TaskWorkflowService::class)->overrideStatus($task, $newStatus, auth()->user());

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

        app(TaskWorkflowService::class)->notifyStatusChange($task, $newStatus, auth()->user());
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
            'submission_note' => ['nullable', 'string'],
        ]);

        // Hours are no longer asked for here — they're already captured via
        // the daily Time Logs, so the total worked so far is used as-is.
        $workflow->submitForQa($task, auth()->user(), $task->total_logged_hours, $data['submission_note'] ?: null);

        $this->submittingTaskId = null;
        $this->submission_note = '';
    }

    public function cancelSubmitForQa(): void
    {
        $this->submittingTaskId = null;
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

        $this->toggleStarFor($task);
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
                // "All Tasks" means everything the viewer is allowed to see.
                default => $query->visibleTo(auth()->user()),
            };
        }

        if ($this->view === 'board') {
            $boardLimit = 200;
            $matchingCount = (clone $query)->count();
            $tasks = (clone $query)->limit($boardLimit)->get();

            return [
                'tasks' => null,
                'statuses' => TaskStatus::cases(),
                'board' => collect(TaskStatus::cases())->mapWithKeys(
                    fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status)->values()]
                ),
                'boardTruncated' => $matchingCount > $boardLimit,
                'boardShown' => $tasks->count(),
                'boardTotal' => $matchingCount,
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

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        @unless ($projectId)
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Tasks</h1>
                <p class="hidden text-sm text-zinc-500 sm:block">Create, assign, and track work across the team.</p>
            </div>
        @else
            <h2 class="text-base font-semibold text-zinc-900">Tasks</h2>
        @endunless

        <div class="flex items-center gap-2">
            <div class="inline-flex rounded-xl bg-zinc-100 p-1" role="tablist" aria-label="View">
                @foreach (['list' => ['List', '<path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>'], 'board' => ['Board', '<rect width="6" height="16" x="3" y="4" rx="1.5"/><rect width="6" height="10" x="10" y="4" rx="1.5"/><rect width="5" height="13" x="16.5" y="4" rx="1.5"/>']] as $key => [$viewLabel, $viewIcon])
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $view === $key ? 'true' : 'false' }}"
                        wire:click="$set('view', '{{ $key }}')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $view === $key ? 'bg-surface text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-900' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">{!! $viewIcon !!}</svg>
                        <span class="hidden sm:inline">{{ $viewLabel }}</span>
                    </button>
                @endforeach
            </div>

            <a href="{{ route('tasks.create', $projectId ? ['project' => $projectId] : []) }}" wire:navigate class="btn-primary py-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                Create task
            </a>
        </div>
    </div>

    @unless ($projectId)
        <div class="flex gap-1 border-b border-zinc-200">
            @foreach ($this->tabs() as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="-mb-px border-b-2 px-3 py-2.5 text-sm font-medium transition-colors {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-zinc-500 hover:text-zinc-800' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endunless

    @if ($view === 'board')
        @if ($boardTruncated)
            <div class="rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800 ring-1 ring-inset ring-amber-200">
                Showing the {{ $boardShown }} most recent of {{ $boardTotal }} matching tasks on the board.
                <button type="button" wire:click="$set('view', 'list')" class="font-medium underline hover:text-amber-900">Switch to List view</button>
                to see and page through all of them.
            </div>
        @endif

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
            {{-- Phones: columns stack. From sm up: a row of fixed-width lanes that scrolls sideways. --}}
            class="-mx-1 flex flex-col gap-3 px-1 sm:flex-row sm:items-start sm:gap-4 sm:overflow-x-auto sm:pb-3 sm:[scrollbar-width:thin]"
            :class="draggingId !== null && moved ? 'cursor-grabbing select-none' : ''"
        >
            @php
                $laneDots = ['todo' => 'bg-zinc-400', 'in_progress' => 'bg-sky-500', 'qa_testing' => 'bg-amber-500', 'rejected' => 'bg-red-500', 'ready_to_deploy' => 'bg-lime-500', 'done' => 'bg-brand'];
            @endphp
            @foreach ($statuses as $status)
                @php $lane = $board[$status->value]; $columnEmpty = $lane->isEmpty(); @endphp
                <section
                    class="flex shrink-0 flex-col rounded-2xl bg-zinc-100/70 ring-1 ring-inset ring-zinc-200/70 transition sm:w-[17.5rem]"
                    x-on:mouseenter="if (draggingId !== null) overStatus = '{{ $status->value }}'"
                    :class="draggingId !== null && moved && overStatus === '{{ $status->value }}' ? 'bg-brand/5! ring-2! ring-brand/40!' : ''"
                    aria-label="{{ $status->label() }}"
                >
                    <header class="flex items-center gap-2 px-3.5 pb-2 pt-3">
                        <span class="size-2.5 rounded-full {{ $laneDots[$status->value] ?? 'bg-zinc-400' }}"></span>
                        <h3 class="flex-1 text-sm font-semibold text-zinc-900">{{ $status->label() }}</h3>
                        <span class="rounded-full bg-surface px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-600 ring-1 ring-zinc-200/80">{{ $lane->count() }}</span>
                    </header>

                    {{-- On phones an empty column shows only its header rather than a whole empty box. --}}
                    <div class="flex-1 space-y-2 px-2 pb-2 {{ $columnEmpty ? 'hidden sm:block' : '' }}">
                        @foreach ($lane as $task)
                            @php $assignee = $task->currentAssignment?->assignedTo; @endphp
                            <article
                                wire:key="card-{{ $task->id }}"
                                x-on:mousedown.prevent="startDrag({{ $task->id }}, $event)"
                                :class="draggingId === {{ $task->id }} && moved ? 'opacity-40 rotate-1' : ''"
                                class="group cursor-grab select-none rounded-xl border border-zinc-200/80 bg-surface p-3 shadow-xs transition hover:-translate-y-px hover:border-brand/30 hover:shadow-md hover:shadow-zinc-900/5 active:cursor-grabbing {{ $this->connectionAccentClass($task, auth()->user()) }}"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded-md bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-500" title="{{ $task->priority->label() }} priority">
                                        <span class="size-1.5 rounded-full bg-current {{ $task->priority->colorClass() }}"></span>{{ $task->priority->label() }}
                                    </span>
                                    <button type="button" x-on:mousedown.stop wire:click="toggleStar({{ $task->id }})" class="-mr-1 ml-auto flex size-6 shrink-0 items-center justify-center rounded-md transition hover:bg-amber-50 {{ $task->starredBy->isNotEmpty() ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}" title="{{ $task->starredBy->isNotEmpty() ? 'Unstar' : 'Star' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" /></svg>
                                    </button>
                                </div>

                                <p class="mt-1.5 line-clamp-2 text-sm font-medium leading-snug text-zinc-900 group-hover:text-brand">{{ $task->title }}</p>

                                @if ($task->parent_task_id || $task->isConnectedTo(auth()->user()))
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                        @if ($task->parent_task_id)
                                            <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-500">Subtask of {{ \App\Models\Task::KEY_PREFIX }}-{{ $task->parent_task_id }}</span>
                                        @endif
                                        @foreach (['Reporter' => $task->isReportedBy(auth()->user()), 'Assignee' => $task->isAssignedTo(auth()->user()), 'QA' => $task->isReviewedBy(auth()->user())] as $role => $isMine)
                                            @if ($isMine)
                                                <span class="rounded-md bg-brand/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">{{ $role }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-2.5 flex items-center justify-between gap-2">
                                    @if ($assignee)
                                        <span class="flex min-w-0 items-center gap-1.5" title="Assigned to {{ $assignee->name }}">
                                            <x-user-avatar :user="$assignee" class="size-5 shrink-0 rounded-full text-[9px]" />
                                            <span class="truncate text-xs text-zinc-500">{{ $assignee->name }}</span>
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">Unassigned</span>
                                    @endif
                                    <x-due-date :date="$task->deadline" :done="$status === \App\Enums\TaskStatus::Done" class="shrink-0" />
                                </div>
                            </article>
                        @endforeach

                        @if ($columnEmpty)
                            <p class="rounded-xl border border-dashed border-zinc-300/80 px-2 py-6 text-center text-xs text-zinc-400" x-text="draggingId !== null && moved ? 'Drop here' : 'No tasks'">No tasks</p>
                        @else
                            <p x-show="draggingId !== null && moved && overStatus === '{{ $status->value }}'" x-cloak class="rounded-xl border border-dashed border-brand/40 px-2 py-3 text-center text-xs font-medium text-brand">Drop here</p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Submit for QA Testing modal (opened by dropping a card on the QA Testing column) --}}
        @if ($submittingTaskId)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4">
                <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-lg">
                    <h3 class="text-lg font-semibold text-zinc-900">Submit for QA Testing</h3>

                    <p class="mt-4 text-sm text-zinc-500">
                        Time logged so far: <span class="font-semibold text-zinc-900">{{ \App\Support\Duration::forHumans((float) (\App\Models\Task::find($submittingTaskId)?->total_logged_hours ?? 0)) }}</span>
                    </p>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700">Note (Optional)</label>
                        <textarea wire:model="submission_note" rows="2" autofocus class="mt-1 block w-full rounded-lg border border-zinc-300 bg-surface px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelSubmitForQa" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</button>
                        <button type="button" wire:click="submitForQa" wire:loading.attr="disabled" wire:target="submitForQa" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submitForQa">Submit</span>
                            <span wire:loading wire:target="submitForQa">Submitting…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- Phones: one card per task — the seven-column table can't fit. --}}
        <div class="divide-y divide-zinc-100 overflow-hidden rounded-lg border border-zinc-200 bg-surface sm:hidden">
            @forelse ($tasks as $task)
                <div class="flex gap-3 px-4 py-3 {{ $this->connectionAccentClass($task, auth()->user()) }}">
                    <a href="{{ route('tasks.show', $task) }}" wire:navigate class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                            @if ($task->isOverdue())
                                <span class="shrink-0 rounded-full bg-red-50 px-1.5 py-0.5 text-[11px] font-medium text-red-600">Overdue</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm font-medium text-zinc-900">{{ $task->title }}</p>
                        <p class="mt-0.5 truncate text-xs text-zinc-500">
                            {{ $task->currentAssignment?->assignedTo?->name ?? 'Unassigned' }}
                            · {{ $task->priority->label() }}
                            @if ($task->deadline)
                                · Due {{ $task->deadline->format('d M') }}
                            @endif
                        </p>
                    </a>
                    <button type="button" wire:click="toggleStar({{ $task->id }})" class="-mr-1 shrink-0 self-start p-1 {{ $task->starredBy->isNotEmpty() ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}" title="Star">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                            <path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" />
                        </svg>
                    </button>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-zinc-500">No tasks here yet.</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 bg-surface sm:block">
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
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-block rounded-full bg-violet-100 px-2 py-0.5 font-mono text-xs font-semibold text-violet-700">{{ $task->task_key }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900">{{ $task->title }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $task->currentAssignment?->assignedTo?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $task->priority->label() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                                @if ($task->isOverdue())
                                    <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Overdue</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500">{{ $task->deadline?->format('d M Y') ?? '—' }}</td>
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
