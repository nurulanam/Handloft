<?php

use App\Livewire\Concerns\RejectsTask;
use App\Livewire\Concerns\SearchesPickerOptions;
use App\Livewire\Concerns\SubmitsForQa;
use App\Livewire\Concerns\TogglesStars;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Tasks')] class extends Component
{
    use RejectsTask, SearchesPickerOptions, SubmitsForQa, TogglesStars;

    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $view = 'board';

    /** How many cards each board column shows to begin with, and how many more "Load more" adds. */
    private const LANE_PAGE = 5;

    /** How many rows the list shows to begin with, and how many more "Load more" adds. */
    private const LIST_PAGE = 10;

    /** @var array<string, int> Cards currently shown per board column, keyed by status. */
    public array $laneLimits = [];

    public int $listLimit = self::LIST_PAGE;

    public ?int $submittingTaskId = null;

    public ?int $rejectingTaskId = null;

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

        // A different tab or view starts again from the first few items.
        if (in_array($name, ['tab', 'view'], true)) {
            $this->laneLimits = [];
            $this->listLimit = self::LIST_PAGE;
        }
    }

    public function tabs(): array
    {
        return [
            'all' => 'All Tasks',
            'assigned-to-me' => 'Assigned to Me',
        ];
    }

    /**
     * Someone created, moved or edited a task (see TasksChanged): being an action, this makes
     * Livewire re-render the board with the latest data.
     */
    #[On('tasks-changed')]
    public function refreshBoard(): void {}

    /**
     * Show more cards in one board column.
     */
    public function loadMoreInLane(string $status): void
    {
        if (TaskStatus::tryFrom($status) === null) {
            return;
        }

        $this->laneLimits[$status] = ($this->laneLimits[$status] ?? self::LANE_PAGE) + self::LANE_PAGE * 2;
    }

    /**
     * Show more rows in the list.
     */
    public function loadMoreInList(): void
    {
        $this->listLimit += self::LIST_PAGE;
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

        // Submitting for QA testing logs the assignee's hours worked so far and, when nobody suitable
        // is on the task yet, asks who will review it — so open a modal instead of updating directly.
        if ($newStatus === TaskStatus::QaTesting) {
            $this->submittingTaskId = $task->id;
            $this->submission_note = '';
            $this->resetQaSubmission();

            return;
        }

        // Rejecting needs a reason from the reviewer, so ask for it in a window first.
        if ($newStatus === TaskStatus::Rejected) {
            $this->rejectingTaskId = $task->id;
            $this->resetRejection();

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
        if (! $this->submitTaskForQa(Task::findOrFail($this->submittingTaskId), $workflow)) {
            return;
        }

        $this->submittingTaskId = null;
    }

    public function confirmReject(TaskWorkflowService $workflow): void
    {
        $this->rejectTaskWithReason(Task::findOrFail($this->rejectingTaskId), $workflow);

        $this->rejectingTaskId = null;
    }

    public function cancelReject(): void
    {
        $this->rejectingTaskId = null;
        $this->resetRejection();
    }

    public function cancelSubmitForQa(): void
    {
        $this->submittingTaskId = null;
        $this->submission_note = '';
        $this->resetQaSubmission();
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
            ->with(['category', 'currentAssignment.assignedTo', 'creator', 'timers.user', 'starredBy' => fn ($q) => $q->where('users.id', $userId)])
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
                        ->orWhere(fn ($q2) => $q2->where('qa_id', $userId)->whereIn('status', [TaskStatus::QaTesting, TaskStatus::Rejected, TaskStatus::ReadyToDeploy]))
                        ->orWhere(fn ($q2) => $q2->where('created_by', $userId)->where('status', TaskStatus::ReadyToDeploy));
                }),
                // "All Tasks" means everything the viewer is allowed to see.
                default => $query->visibleTo(auth()->user()),
            };
        }

        if ($this->view === 'board') {
            // One grouped count for every column's total, then only each column's first few cards —
            // never the whole task table. Most recently touched first, so a card just moved into
            // a column is at the top of it.
            $totals = (clone $query)->reorder()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            $board = collect(TaskStatus::cases())->mapWithKeys(function (TaskStatus $status) use ($query, $totals) {
                $total = (int) ($totals[$status->value] ?? 0);
                $limit = $this->laneLimits[$status->value] ?? self::LANE_PAGE;

                return [$status->value => [
                    'tasks' => $total === 0
                        ? collect()
                        : (clone $query)->reorder()->where('status', $status)->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get(),
                    'total' => $total,
                ]];
            });

            return [
                'tasks' => null,
                'statuses' => TaskStatus::cases(),
                'board' => $board,
                // The reviewer chosen in the Submit for QA window (only needed while the modal is open).
                'qa_reviewer_name' => $this->qa_reviewer_id ? User::query()->whereKey($this->qa_reviewer_id)->value('name') : null,
            ];
        }

        $listTotal = (clone $query)->count();

        return [
            'tasks' => $query->orderByDesc('id')->limit($this->listLimit)->get(),
            'listTotal' => $listTotal,
            'statuses' => TaskStatus::cases(),
            'board' => null,
            // The reviewer chosen in the Submit for QA window (only needed while the modal is open).
            'qa_reviewer_name' => $this->qa_reviewer_id ? User::query()->whereKey($this->qa_reviewer_id)->value('name') : null,
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
        <div
            :data-dragging="draggingId !== null && moved ? '' : null"
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
                @php $lane = $board[$status->value]['tasks']; $laneTotal = $board[$status->value]['total']; $columnEmpty = $lane->isEmpty(); @endphp
                <section
                    class="flex shrink-0 flex-col rounded-2xl bg-zinc-100/70 ring-1 ring-inset ring-zinc-200/70 transition sm:w-[17.5rem]"
                    x-on:mouseenter="if (draggingId !== null) overStatus = '{{ $status->value }}'"
                    :class="draggingId !== null && moved && overStatus === '{{ $status->value }}' ? 'bg-brand/5! ring-2! ring-brand/40!' : ''"
                    aria-label="{{ $status->label() }}"
                >
                    <header class="flex items-center gap-2 px-3.5 pb-2 pt-3">
                        <span class="size-2.5 rounded-full {{ $laneDots[$status->value] ?? 'bg-zinc-400' }}"></span>
                        <h3 class="flex-1 text-sm font-semibold text-zinc-900">{{ $status->label() }}</h3>
                        <span class="rounded-full bg-surface px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-600 ring-1 ring-zinc-200/80">{{ $laneTotal }}</span>
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
                                    @if ($task->timers->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 rounded-md bg-brand/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand" title="{{ $task->timers->pluck('user.name')->join(', ', ' and ') }} working on this now">
                                            <span class="relative flex size-1.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-60"></span><span class="relative inline-flex size-1.5 rounded-full bg-brand"></span></span>
                                            Live
                                        </span>
                                    @endif
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

                        @if ($laneTotal > $lane->count())
                            <button type="button" x-on:mousedown.stop wire:click="loadMoreInLane('{{ $status->value }}')" wire:loading.attr="disabled" wire:target="loadMoreInLane('{{ $status->value }}')" class="w-full rounded-xl px-2 py-2 text-center text-xs font-semibold text-brand transition hover:bg-brand/5 disabled:opacity-60">
                                Load more · {{ $laneTotal - $lane->count() }} more
                            </button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Reject task modal: the reviewer must say why --}}
        @if ($rejectingTaskId && ($rejectingTask = \App\Models\Task::find($rejectingTaskId)))
                <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4" wire:keydown.escape.window="cancelReject">
                    <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-2xl">
                        <h3 class="text-lg font-semibold text-zinc-900">Reject task</h3>
                        <p class="mt-1 truncate text-sm text-zinc-500">{{ $rejectingTask->task_key }} {{ $rejectingTask->title }}</p>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-zinc-700">Reason for rejection <span class="text-red-600">*</span></label>
                            <p class="mb-1.5 text-xs text-zinc-500">This is posted as a comment and sent to the assignee.</p>
                            <textarea wire:model="rejection_reason" rows="4" class="field-input mt-1.5" placeholder="What needs to be fixed?" autofocus></textarea>
                            @error('rejection_reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" wire:click="cancelReject" class="btn-secondary">Cancel</button>
                            <button type="button" wire:click="confirmReject" wire:loading.attr="disabled" wire:target="confirmReject" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-60">
                                <span wire:loading.remove wire:target="confirmReject">Reject task</span>
                                <span wire:loading wire:target="confirmReject">Rejecting…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

        {{-- Submit for QA Testing modal (opened by dropping a card on the QA Testing column) --}}
        @if ($submittingTaskId && ($submittingTask = \App\Models\Task::find($submittingTaskId)))
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4" wire:keydown.escape.window="cancelSubmitForQa">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-zinc-900">Submit for QA Testing</h3>
                    <p class="mt-1 truncate text-sm text-zinc-500"><span class="font-mono text-xs font-semibold text-violet-700">{{ $submittingTask->task_key }}</span> {{ $submittingTask->title }}</p>

                    @if ($this->needsQaReviewer($submittingTask))
                        {{-- Nobody suitable on the task yet: pick a reviewer here so the submission isn't blocked. --}}
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-zinc-700">QA / Reviewer <span class="text-red-600">*</span></label>
                            <p class="mb-1.5 text-xs text-zinc-500">
                                @if ($submittingTask->qa)
                                    You're the assigned reviewer — someone else has to approve this.
                                @else
                                    The task has no reviewer assigned yet.
                                @endif
                            </p>
                            <x-form.picker
                                model="qa_reviewer_id"
                                source="people"
                                :value="$qa_reviewer_id"
                                :label="$qa_reviewer_name"
                                avatar
                                placeholder="Choose who will review this"
                                align="left"
                            />
                            @error('qa_reviewer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <x-tasks.qa-time-log :task="$submittingTask" :error="$errors->first('qa_log_date') ?: ($errors->first('qa_log_hours') ?: $errors->first('qa_log_minutes'))" />

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700">Note (Optional)</label>
                        <textarea wire:model="submission_note" rows="2" class="field-input mt-1.5"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelSubmitForQa" class="btn-secondary">Cancel</button>
                        <button type="button" wire:click="submitForQa" wire:loading.attr="disabled" wire:target="submitForQa" class="btn-primary">
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

        @if ($listTotal > $tasks->count())
            <div class="flex items-center justify-center gap-3 pt-1 text-sm text-zinc-500">
                <span>Showing {{ $tasks->count() }} of {{ $listTotal }}</span>
                <button type="button" wire:click="loadMoreInList" wire:loading.attr="disabled" wire:target="loadMoreInList" class="btn-secondary">Load more</button>
            </div>
        @endif
    @endif
</div>
