<?php

use App\Livewire\Concerns\SearchesPickerOptions;
use App\Livewire\Concerns\TogglesStars;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Duration;
use App\Support\Html;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Task')] class extends Component
{
    use SearchesPickerOptions, TogglesStars, WithFileUploads;

    public Task $task;

    public bool $showSubmitQaModal = false;

    public string $submission_note = '';

    public string $description = '';

    public ?string $editingField = null;

    public string $due_date_value = '';

    public string $newComment = '';

    /** @var array<int, mixed> */
    public array $commentAttachments = [];

    /** @var array<int, mixed> */
    public array $newAttachments = [];

    public bool $showAddSubtask = false;

    public string $subtask_title = '';

    public string $subtask_assigned_to = '';

    public bool $showLogTime = false;

    public string $log_date = '';

    public string $log_hours = '0';

    public string $log_minutes = '0';

    public string $log_note = '';

    public function mount(Task $task): void
    {
        Gate::authorize('view', $task);

        $this->task = $task;
        $this->description = (string) $task->description;
        $this->log_date = now()->toDateString();
    }

    public function toggleStar(): void
    {
        $this->toggleStarFor($this->task);
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
    }

    public function submitForQa(TaskWorkflowService $workflow): void
    {
        Gate::authorize('transitionStatus', [$this->task, TaskStatus::QaTesting]);

        $data = $this->validate([
            'submission_note' => ['nullable', 'string'],
        ]);

        // Hours are no longer asked for here — they're already captured via
        // the daily Time Logs, so the total worked so far is used as-is.
        $workflow->submitForQa($this->task, auth()->user(), $this->task->total_logged_hours, $data['submission_note'] ?: null);

        $this->showSubmitQaModal = false;
        $this->task->refresh();
    }

    public function cancelSubmitForQa(): void
    {
        $this->showSubmitQaModal = false;
        $this->submission_note = '';
        $this->resetErrorBag();
    }

    public function saveDescription(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate(['description' => ['nullable', 'string']]);

        $sanitized = Html::sanitize($data['description']);

        if ($sanitized !== $this->task->description) {
            $this->task->update(['description' => $sanitized]);
            $this->logMetaChange('Description updated');
            $this->task->refresh();
        }

        $this->description = (string) $this->task->description;
    }

    /**
     * Due date is the only field left using this click-to-reveal-an-input
     * pattern — every other field (status, assignee, reporter, QA, priority,
     * parent, project) is now a self-contained dropdown menu that saves
     * immediately on selection, so it no longer needs this shared "editing"
     * state at all.
     */
    public function startEditField(string $field): void
    {
        Gate::authorize('updateMeta', $this->task);

        $this->due_date_value = $this->task->deadline?->toDateString() ?? '';
        $this->editingField = $field;
    }

    public function cancelEditField(): void
    {
        $this->editingField = null;
        $this->resetErrorBag();
    }

    /**
     * The statuses the current user is allowed to move this task to right now.
     */
    private function nextStatuses(): \Illuminate\Support\Collection
    {
        return collect($this->task->status->nextStatuses())
            ->filter(fn (TaskStatus $status) => Gate::allows('transitionStatus', [$this->task, $status]))
            ->values();
    }

    public function saveStatus(string $value, TaskWorkflowService $workflow): void
    {
        $data = Validator::make(['status_value' => $value], [
            'status_value' => ['required', Rule::in(array_column(TaskStatus::cases(), 'value'))],
        ])->validate();

        $newStatus = TaskStatus::from($data['status_value']);

        Gate::authorize('transitionStatus', [$this->task, $newStatus]);

        if ($newStatus === TaskStatus::QaTesting) {
            if (! $this->task->qa_id) {
                $this->dispatch('notify', message: 'Assign a QA / Reviewer to this task before submitting it for QA testing.', type: 'error');

                return;
            }

            $this->submission_note = '';
            $this->showSubmitQaModal = true;

            return;
        }

        if ($newStatus === TaskStatus::Done) {
            $workflow->markDone($this->task, auth()->user());
        } else {
            $this->task->update(['status' => $newStatus]);
            $this->logMetaChange("Status changed to {$newStatus->label()}");
            $workflow->notifyStatusChange($this->task, $newStatus, auth()->user());
        }

        $this->task->refresh();
    }

    public function saveAssignee(int $userId, TaskWorkflowService $workflow): void
    {
        Gate::authorize('reassign', $this->task);

        $data = Validator::make(['assignee_value' => $userId], [
            'assignee_value' => ['required', 'exists:users,id'],
        ])->validate();

        $workflow->reassignTask($this->task, User::findOrFail($data['assignee_value']), auth()->user());

        $this->task->refresh();
    }

    public function saveReporter(int $userId): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = Validator::make(['reporter_value' => $userId], [
            'reporter_value' => ['required', 'exists:users,id'],
        ])->validate();

        $this->task->update(['created_by' => $data['reporter_value']]);
        $this->logMetaChange('Reporter updated');

        $this->task->refresh();
    }

    public function savePriority(string $value): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = Validator::make(['priority_value' => $value], [
            'priority_value' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
        ])->validate();

        $this->task->update(['priority' => $data['priority_value']]);
        $this->logMetaChange('Priority updated to '.TaskPriority::from($data['priority_value'])->label());

        $this->task->refresh();
    }

    public function saveDueDate(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate(['due_date_value' => ['nullable', 'date']]);

        $this->task->update(['deadline' => $data['due_date_value'] ?: null]);
        $this->logMetaChange('Due date updated');

        $this->editingField = null;
        $this->task->refresh();
    }

    public function saveQa(?int $userId): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = Validator::make(['qa_value' => $userId], [
            'qa_value' => ['nullable', 'exists:users,id'],
        ])->validate();

        $this->task->update(['qa_id' => $data['qa_value'] ?: null]);
        $this->logMetaChange('QA/Reviewer updated');

        $this->task->refresh();
    }

    public function saveProject(?int $projectId): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = Validator::make(['project_value' => $projectId], [
            'project_value' => ['nullable', 'exists:projects,id'],
        ])->validate();

        $this->task->update(['project_id' => $data['project_value'] ?: null]);
        $this->logMetaChange('Project updated');

        $this->task->refresh();
    }

    public function saveParent(?int $parentTaskId): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = Validator::make(['parent_value' => $parentTaskId], [
            'parent_value' => ['nullable', 'exists:tasks,id'],
        ])->validate();

        $parentId = $data['parent_value'] ?: null;

        if ($parentId) {
            if ((int) $parentId === $this->task->id) {
                $this->addError('parent_value', 'A task cannot be its own parent.');

                return;
            }

            $ancestor = Task::find($parentId);
            $depth = 0;

            while ($ancestor && $depth < 20) {
                if ($ancestor->id === $this->task->id) {
                    $this->addError('parent_value', 'That link would create a circular reference.');

                    return;
                }

                $ancestor = $ancestor->parent;
                $depth++;
            }
        }

        $this->task->update(['parent_task_id' => $parentId]);
        $this->logMetaChange('Parent task updated');

        $this->task->refresh();
    }

    public function addComment(): void
    {
        Gate::authorize('comment', $this->task);

        $data = $this->validate([
            'newComment' => ['required', 'string'],
            'commentAttachments.*' => ['file', 'max:10240'],
        ]);

        $comment = $this->task->comments()->create([
            'user_id' => auth()->id(),
            'body' => $data['newComment'],
        ]);

        foreach ($this->commentAttachments as $file) {
            $comment->attachments()->create([
                'uploaded_by' => auth()->id(),
                'path' => $file->store('task-comment-attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        $this->newComment = '';
        $this->commentAttachments = [];
    }

    public function removeCommentAttachment(int $index): void
    {
        unset($this->commentAttachments[$index]);
        $this->commentAttachments = array_values($this->commentAttachments);
    }

    public function addAttachments(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate(['newAttachments.*' => ['file', 'max:10240']]);

        foreach ($this->newAttachments as $file) {
            $this->task->attachments()->create([
                'uploaded_by' => auth()->id(),
                'path' => $file->store('task-attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        $count = count($data['newAttachments'] ?? []);
        $this->logMetaChange($count === 1 ? '1 attachment added' : "{$count} attachments added");

        $this->newAttachments = [];
        $this->task->refresh();
    }

    public function removeNewAttachment(int $index): void
    {
        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
    }

    public function addSubtask(TaskWorkflowService $workflow): void
    {
        Gate::authorize('create', Task::class);

        $data = $this->validate([
            'subtask_title' => ['required', 'string', 'max:255'],
            'subtask_assigned_to' => ['required', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($data['subtask_assigned_to']);

        $workflow->createTask([
            'title' => $data['subtask_title'],
            'parent_task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
        ], auth()->user(), $assignee);

        $this->subtask_title = '';
        $this->subtask_assigned_to = '';
        $this->showAddSubtask = false;
        $this->task->refresh();
    }

    public function logTime(TaskWorkflowService $workflow): void
    {
        $this->authorizeTimeLogging();

        $data = $this->validate([
            'log_date' => [
                'required',
                'date',
                'before_or_equal:today',
                ...($this->task->start_date ? ['after_or_equal:'.$this->task->start_date->toDateString()] : []),
            ],
            'log_hours' => ['required', 'integer', 'min:0', 'max:24'],
            'log_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'log_note' => ['nullable', 'string'],
        ], [
            'log_date.after_or_equal' => 'Time can\'t be logged before the task\'s start date ('.optional($this->task->start_date)->format('d M Y').').',
        ]);

        $totalHours = Duration::fromParts((int) $data['log_hours'], (int) $data['log_minutes']);

        if ($totalHours <= 0) {
            $this->addError('log_hours', 'Log at least some time.');

            return;
        }

        if ($totalHours > 24) {
            $this->addError('log_hours', "A single day can't have more than 24 hours logged.");

            return;
        }

        $workflow->logTime(
            $this->task,
            auth()->user(),
            \Illuminate\Support\Carbon::parse($data['log_date']),
            $totalHours,
            $data['log_note'] ?: null
        );

        $this->log_date = now()->toDateString();
        $this->log_hours = '0';
        $this->log_minutes = '0';
        $this->log_note = '';
        $this->showLogTime = false;
        $this->task->refresh();
    }

    public function cancelLogTime(): void
    {
        $this->showLogTime = false;
        $this->log_date = now()->toDateString();
        $this->log_hours = '0';
        $this->log_minutes = '0';
        $this->log_note = '';
        $this->resetErrorBag();
    }

    public function deleteTimeLog(TaskWorkflowService $workflow, int $timeLogId): void
    {
        $timeLog = $this->task->timeLogs()->findOrFail($timeLogId);

        abort_unless($timeLog->user_id === auth()->id() || Gate::allows('updateMeta', $this->task), 403);

        $workflow->deleteTimeLog($timeLog, auth()->user());

        $this->task->refresh();
    }

    /**
     * Only someone actually connected to the task — the assignee doing the
     * work, the QA/Reviewer testing it, or the Reporter who filed it — may
     * log time on it. There is no permission-based override: an uninvolved
     * Manager/Admin can still view and manage the task's metadata, but has no
     * reason to be logging hours on work they're not party to.
     *
     * The QA/Reviewer and Reporter's own time only makes sense once the task
     * has actually reached their stage of the workflow — a reviewer isn't
     * testing anything before QA Testing, and a reporter isn't signing off on
     * anything before Ready to Deploy.
     */
    private function authorizeTimeLogging(): void
    {
        abort_unless($this->canCurrentUserLogTime(), 403);
    }

    private function canCurrentUserLogTime(): bool
    {
        $user = auth()->user();

        if ($this->task->isAssignedTo($user)) {
            return true;
        }

        if ($this->task->isReviewedBy($user)) {
            return $this->task->status->isAtLeast(TaskStatus::QaTesting);
        }

        if ($this->task->isReportedBy($user)) {
            return $this->task->status->isAtLeast(TaskStatus::ReadyToDeploy);
        }

        return false;
    }

    private function logMetaChange(string $description): void
    {
        $this->task->activities()->create([
            'causer_id' => auth()->id(),
            'type' => TaskActivityType::MetaUpdated,
            'description' => "{$description} by ".auth()->user()->name,
            'occurred_at' => now(),
        ]);
    }

    public function with(): array
    {
        $taskAttachments = $this->task->attachments()->with('uploadedBy')->get()->map(fn ($a) => [
            'id' => 'task-'.$a->id,
            'path' => $a->path,
            'original_name' => $a->original_name,
            'size' => $a->size,
            'uploaded_by' => $a->uploadedBy->name,
            'created_at' => $a->created_at,
            'source' => 'Task',
        ]);

        $commentAttachments = $this->task->comments()
            ->with('attachments.uploadedBy')
            ->get()
            ->flatMap->attachments
            ->map(fn ($a) => [
                'id' => 'comment-'.$a->id,
                'path' => $a->path,
                'original_name' => $a->original_name,
                'size' => $a->size,
                'uploaded_by' => $a->uploadedBy->name,
                'created_at' => $a->created_at,
                'source' => 'Comment',
            ]);

        $nextStatuses = $this->nextStatuses();

        return [
            'timeline' => $this->task->activities()->with('causer')->get(),
            'currentAssignee' => $this->task->currentAssignee(),
            // People, projects and parent tasks are searched on demand (SearchesPickerOptions), not listed here.
            'subtaskAssigneeName' => $this->subtask_assigned_to ? User::query()->whereKey($this->subtask_assigned_to)->value('name') : null,
            'availableStatuses' => $nextStatuses,
            'allAttachments' => $taskAttachments->concat($commentAttachments)->sortByDesc('created_at')->values(),
            'canReassign' => Gate::allows('reassign', $this->task),
            'canEditStatus' => $nextStatuses->isNotEmpty(),
            'canEditMeta' => Gate::allows('updateMeta', $this->task),
            'canComment' => Gate::allows('comment', $this->task),
            'isStarred' => auth()->user()->starredTasks()->where('tasks.id', $this->task->id)->exists(),
            'timeLogs' => $this->task->timeLogs()->with('user')->get(),
            'totalLoggedHours' => $this->task->timeLogs()->sum('hours'),
            'canLogTime' => $this->canCurrentUserLogTime(),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <nav class="mb-2 flex min-w-0 flex-wrap items-center gap-1.5 text-sm text-zinc-500" aria-label="Breadcrumb">
                <a href="{{ route('tasks.index') }}" wire:navigate class="group inline-flex items-center gap-1.5 font-medium transition-colors hover:text-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform group-hover:-translate-x-0.5"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" /></svg>
                    Tasks
                </a>
                @if ($task->project)
                    <span class="text-zinc-300">/</span>
                    <a href="{{ route('projects.show', $task->project) }}" wire:navigate class="max-w-48 truncate font-medium transition-colors hover:text-brand">{{ $task->project->name }}</a>
                @endif
                @if ($task->parent)
                    <span class="text-zinc-300">/</span>
                    <a href="{{ route('tasks.show', $task->parent) }}" wire:navigate class="max-w-56 truncate font-medium transition-colors hover:text-brand">{{ $task->parent->task_key }} {{ $task->parent->title }}</a>
                @endif
            </nav>

            <div class="flex items-center gap-2">
                <span class="rounded-full bg-violet-100 px-2.5 py-1 font-mono text-xs font-semibold text-violet-700">{{ $task->task_key }}</span>
                <button type="button" wire:click="toggleStar" class="flex size-7 items-center justify-center rounded-lg transition-colors hover:bg-amber-50 {{ $isStarred ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}" title="{{ $isStarred ? 'Unstar' : 'Star' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                        <path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" />
                    </svg>
                </button>
            </div>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900 sm:text-[1.75rem] sm:leading-9">{{ $task->title }}</h1>
            <p class="mt-1.5 flex items-center gap-2 text-sm text-zinc-500">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-ink-700 text-[9px] font-semibold text-white">{{ \App\Support\Avatar::initials($task->creator->name) }}</span>
                <span>Created by <span class="font-medium text-zinc-700">{{ $task->creator->name }}</span> on {{ $task->created_at->format('d M Y') }}</span>
            </p>
        </div>

        {{-- Status: moves the task along the board (only to the statuses its workflow allows). --}}
        <div class="relative shrink-0" x-data="dropdownMenu()" @click.outside="open = false">
            <button
                type="button"
                @click="open = ! open"
                :aria-expanded="open"
                @disabled(! $canEditStatus)
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold shadow-xs ring-1 ring-inset ring-zinc-900/5 transition {{ $task->status->pillClasses() }} {{ $canEditStatus ? 'hover:brightness-95' : 'cursor-default' }}"
            >
                <span class="size-2 rounded-full bg-current opacity-70"></span>
                {{ $task->status->label() }}
                @if ($canEditStatus)
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 opacity-60 transition-transform" :class="open && 'rotate-180'"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                @endif
            </button>

            @if ($canEditStatus)
                <div x-show="open" x-cloak x-transition class="menu-panel right-0 w-52 sm:right-0">
                    <p class="px-2.5 pb-1 pt-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Move to</p>
                    @forelse ($availableStatuses as $option)
                        <button type="button" wire:click="saveStatus('{{ $option->value }}')" @click="open = false" class="menu-item">
                            <span class="size-2 rounded-full {{ $option->pillClasses() }}"></span>
                            {{ $option->label() }}
                        </button>
                    @empty
                        <p class="px-2.5 py-2 text-sm text-zinc-400">No other status available.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    {{-- On phones both column wrappers are display:contents, so every card becomes its own grid row and
         the order-* classes put status and Details right under the title instead of after the whole
         activity log. From lg up the wrappers are real columns again and the order classes are inert. --}}
    <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="contents lg:col-span-2 lg:block lg:space-y-6">
            {{-- Description --}}
            <div class="order-3 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20" x-data="lazyQuillEditor(@js($task->description))">
                <div class="-mx-4 -mt-4 mb-4 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Description</h2>
                    @if ($canEditMeta)
                        <button type="button" x-show="!editing" @click="startEditing()" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand transition-colors hover:bg-brand/5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" /></svg>
                            Edit
                        </button>
                    @endif
                </div>

                <div x-show="!editing">
                    @if ($task->description)
                        <div x-data="{ expanded: false, overflowing: false }" x-init="$nextTick(() => { overflowing = $refs.descriptionContent.scrollHeight > $refs.descriptionContent.clientHeight })">
                            {{-- The `ql-editor` class sets `white-space: pre-wrap` so intentional
                                 blank lines in the description render correctly — which means any
                                 whitespace *outside* the description itself (e.g. this template's own
                                 indentation) would render as stray blank space too, so the tags must
                                 hug the interpolation with no whitespace in between. --}}
                            <div
                                x-ref="descriptionContent"
                                class="ql-editor p-0! text-sm! leading-relaxed text-zinc-700 [&_a]:text-brand [&_a]:underline [&_blockquote]:text-zinc-500 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1"
                                :class="expanded ? '' : 'line-clamp-10'"
                            >{!! $task->description !!}</div>

                            <div x-show="overflowing" class="mt-2 flex justify-center">
                                <button type="button" @click="expanded = ! expanded" class="flex items-center gap-1 text-xs font-medium text-brand hover:underline">
                                    <span x-text="expanded ? 'Show less' : 'Show more'"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-zinc-400">No description yet.</p>
                    @endif
                </div>

                @if ($canEditMeta)
                    {{-- The Quill instance is created lazily on the first "Edit" click (see
                         lazyQuillEditor in app.js) and never destroyed afterwards — Quill measures
                         text layout on init, so creating it while hidden (display:none) breaks it,
                         and recreating it on every edit leaks a document-level listener. --}}
                    <div x-show="editing" x-cloak wire:ignore>
                        <div class="rich-editor">
                            <div x-ref="editor" data-placeholder="Steps, links, what done looks like…"></div>
                            <input type="hidden" x-ref="input" wire:model="description">
                        </div>

                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" @click="editing = false" class="btn-secondary px-3.5 py-2">Cancel</button>
                            <button type="button" @click="$wire.saveDescription().then(() => editing = false)" class="btn-primary px-3.5 py-2">Save</button>
                        </div>
                    </div>
                    @error('description') <p x-show="editing" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>

            {{-- Attachments (task-level + comment attachments, combined) --}}
            <div class="order-5 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20">
                <div class="-mx-4 -mt-4 mb-4 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Attachments</h2><span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">{{ $allAttachments->count() }}</span>
                </div>

                @if ($allAttachments->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($allAttachments as $attachment)
                            @if (\App\Support\FileType::isImage($attachment['original_name']))
                                <a href="{{ Storage::url($attachment['path']) }}" target="_blank" class="relative block overflow-hidden rounded-md border border-zinc-200 hover:border-brand/40">
                                    <img src="{{ Storage::url($attachment['path']) }}" alt="{{ $attachment['original_name'] }}" class="h-20 w-20 object-cover">
                                    <span class="absolute bottom-0 left-0 right-0 truncate bg-zinc-900/60 px-1 py-0.5 text-[10px] text-white">{{ $attachment['original_name'] }}</span>
                                </a>
                            @else
                                <a href="{{ Storage::url($attachment['path']) }}" target="_blank" class="flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-xs text-zinc-600 hover:border-brand/40">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-zinc-400">
                                        <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="max-w-40 truncate">{{ $attachment['original_name'] }}</span>
                                    <span class="shrink-0 text-zinc-400">{{ \App\Support\FileSize::forHumans($attachment['size']) }}</span>
                                    <span class="shrink-0 rounded bg-zinc-200 px-1 text-[10px] uppercase tracking-wide text-zinc-500">{{ $attachment['source'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-400">No attachments yet.</p>
                @endif

                @if ($canEditMeta)
                    <div class="mt-4">
                        <x-form.dropzone model="newAttachments" compact>
                            @error('newAttachments.*') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                            @if (! empty($newAttachments))
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($newAttachments as $index => $file)
                                        <x-form.file-chip :file="$file" :remove="'removeNewAttachment('.$index.')'" />
                                    @endforeach
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" wire:click="addAttachments" wire:loading.attr="disabled" wire:target="addAttachments" class="btn-primary px-3.5 py-2">Upload {{ count($newAttachments) }} {{ \Illuminate\Support\Str::plural('file', count($newAttachments)) }}</button>
                                </div>
                            @endif
                        </x-form.dropzone>
                    </div>
                @endif
            </div>

            {{-- Comments --}}
            <div class="order-6 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20">
                <div class="-mx-4 -mt-4 mb-4 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Comments</h2><span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">{{ $task->comments->count() }}</span>
                </div>

                <div class="space-y-4">
                    @forelse ($task->comments as $comment)
                        <div class="flex gap-3">
                            <x-user-avatar :user="$comment->user" class="size-8 shrink-0 rounded-full text-[11px]" />
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900">{{ $comment->user->name }}</span>
                                    <span class="text-xs text-zinc-400">{{ $comment->created_at->format('d M Y — h:i A') }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line rounded-xl rounded-tl-sm bg-zinc-50 px-3.5 py-2.5 text-sm text-zinc-700">{{ $comment->body }}</p>
                                @if ($comment->attachments->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($comment->attachments as $attachment)
                                            @if (\App\Support\FileType::isImage($attachment->original_name))
                                                <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="group block overflow-hidden rounded-md border border-zinc-200 hover:border-brand/40">
                                                    <img src="{{ Storage::url($attachment->path) }}" alt="{{ $attachment->original_name }}" class="h-20 w-20 object-cover">
                                                </a>
                                            @else
                                                <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="flex items-center gap-1.5 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs text-zinc-600 hover:border-brand/40">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 shrink-0 text-zinc-400">
                                                        <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ $attachment->original_name }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-400">No comments yet. Start the conversation below.</p>
                    @endforelse
                </div>

                @if ($canComment)
                    <div class="mt-5 border-t border-zinc-100 pt-4">
                        <textarea wire:model="newComment" rows="3" placeholder="Write a comment…" class="field-input resize-y"></textarea>
                        @error('newComment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                        @if (! empty($commentAttachments))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($commentAttachments as $index => $file)
                                    <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 py-1 pl-2 pr-1 text-xs text-zinc-700">
                                        @if (str($file->getMimeType())->startsWith('image/'))
                                            <img src="{{ $file->temporaryUrl() }}" class="size-5 rounded object-cover">
                                        @endif
                                        {{ $file->getClientOriginalName() }}
                                        <span class="text-zinc-400">({{ \App\Support\FileSize::forHumans($file->getSize()) }})</span>
                                        <button type="button" wire:click="removeCommentAttachment({{ $index }})" class="rounded-full p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700" title="Remove">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3">
                                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                            </svg>
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-2 flex items-center justify-between gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-medium text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg>
                                Attach
                                <input type="file" wire:model="commentAttachments" multiple class="sr-only">
                            </label>
                            <button type="button" wire:click="addComment" wire:loading.attr="disabled" wire:target="addComment" class="btn-primary shrink-0 px-4 py-2">Comment</button>
                        </div>
                        @error('commentAttachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            {{-- Activity Timeline --}}
            @php $hiddenEntries = max(0, $timeline->count() - 5); @endphp
            <div class="order-8 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20" x-data="{ all: false }">
                <div class="-mx-4 -mt-4 mb-4 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Activity</h2>
                    @if ($hiddenEntries > 0)
                        <button type="button" @click="all = ! all" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand transition-colors hover:bg-brand/5" x-text="all ? 'Show recent only' : 'Show all {{ $timeline->count() }}'"></button>
                    @endif
                </div>
                <ol class="relative space-y-4 pl-5 before:absolute before:inset-y-1 before:left-[5px] before:w-px before:bg-zinc-200">
                    @foreach ($timeline as $entry)
                        <li class="relative" @if ($loop->index < $hiddenEntries) x-show="all" x-cloak @endif>
                            <span class="absolute -left-5 top-1.5 size-[11px] rounded-full border-2 border-surface bg-zinc-300 ring-1 ring-zinc-200 {{ $loop->last ? 'bg-brand!' : '' }}"></span>
                            <p class="text-sm text-zinc-900">{{ $entry->description }}</p>
                            <p class="text-xs text-zinc-500">{{ $entry->occurred_at->format('d M Y — h:i A') }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="contents lg:sticky lg:top-20 lg:block lg:space-y-4 lg:self-start">
            {{-- Details: each value is edited in place (for those allowed to). --}}
            <div class="order-2 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20">
                <div class="-mx-4 -mt-4 mb-3 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Details</h2>
                </div>
                <dl class="divide-y divide-zinc-100">
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>Assignee</dt>
                        <dd class="flex min-w-0 justify-end">
                            <x-form.picker variant="inline" call="saveAssignee" :value="$currentAssignee?->id" source="people" :label="$currentAssignee?->name" avatar :placeholder="$canReassign ? 'Add assignee' : 'Unassigned'" :disabled="! $canReassign" />
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>Reporter</dt>
                        <dd class="flex min-w-0 justify-end">
                            <x-form.picker variant="inline" call="saveReporter" :value="$task->created_by" source="people" :label="$task->creator->name" avatar :disabled="! $canEditMeta" />
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>QA reviewer</dt>
                        <dd class="flex min-w-0 justify-end">
                            <x-form.picker variant="inline" call="saveQa" :value="$task->qa_id" source="people" :label="$task->qa?->name" avatar clearable clear-label="No reviewer" :placeholder="$canEditMeta ? 'Add reviewer' : 'None'" :disabled="! $canEditMeta" />
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>Priority</dt>
                        <dd class="flex min-w-0 justify-end">
                            <div class="relative" x-data="dropdownMenu()" @click.outside="open = false">
                                <button type="button" @click="open = ! open" :aria-expanded="open" @disabled(! $canEditMeta) class="-mr-2 flex items-center gap-2 rounded-lg px-2 py-1 text-sm text-zinc-900 transition-colors {{ $canEditMeta ? 'hover:bg-zinc-100' : 'cursor-default' }}">
                                    <span class="size-2 rounded-full bg-current {{ $task->priority->colorClass() }}"></span>
                                    {{ $task->priority->label() }}
                                </button>
                                @if ($canEditMeta)
                                    <div x-show="open" x-cloak x-transition class="menu-panel right-0 w-44">
                                        @foreach (TaskPriority::cases() as $option)
                                            <button type="button" wire:click="savePriority('{{ $option->value }}')" @click="open = false" class="menu-item {{ $task->priority === $option ? 'bg-brand/5 font-medium text-brand!' : '' }}">
                                                <span class="size-2 rounded-full bg-current {{ $option->colorClass() }}"></span>
                                                {{ $option->label() }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>Due date</dt>
                        <dd class="flex min-w-0 justify-end">
                            <div class="relative">
                                <button type="button" wire:click="startEditField('due_date')" @disabled(! $canEditMeta) class="-mr-2 rounded-lg px-2 py-1 text-sm transition-colors {{ $task->deadline ? ($task->deadline->isPast() && $task->status !== TaskStatus::Done ? 'font-medium text-red-600' : 'text-zinc-900') : 'text-zinc-400' }} {{ $canEditMeta ? 'hover:bg-zinc-100' : 'cursor-default' }}">
                                    {{ $task->deadline?->format('D, d M Y') ?? ($canEditMeta ? 'Add due date' : 'None') }}
                                </button>

                                @if ($editingField === 'due_date')
                                    <div class="menu-panel right-0 w-64 p-3">
                                        <input wire:model="due_date_value" type="date" class="field-input">
                                        @error('due_date_value') <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                                        <div class="mt-3 flex justify-end gap-2">
                                            <button type="button" wire:click="cancelEditField" class="btn-secondary px-3 py-1.5">Cancel</button>
                                            <button type="button" wire:click="saveDueDate" class="btn-primary px-3 py-1.5">Save</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>Category</dt>
                        <dd class="flex min-w-0 justify-end">
                            <span class="truncate text-sm {{ $task->category ? 'text-zinc-900' : 'text-zinc-400' }}">{{ $task->category?->name ?? 'None' }}</span>
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>Project</dt>
                        <dd class="flex min-w-0 justify-end">
                            <x-form.picker variant="inline" call="saveProject" :value="$task->project_id" source="projects" :label="$task->project?->name" clearable clear-label="No project" :placeholder="$canEditMeta ? 'Add project' : 'None'" :disabled="! $canEditMeta" />
                        </dd>
                    </div>
                    <div class="flex min-h-11 items-center justify-between gap-3 py-1.5">
                        <dt class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-zinc-400"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>Parent</dt>
                        <dd class="flex min-w-0 justify-end">
                            <x-form.picker variant="inline" call="saveParent" :value="$task->parent_task_id" source="tasks" :except="$task->id" :label="$task->parent ? $task->parent->task_key.' '.$task->parent->title : null" clearable clear-label="No parent" :placeholder="$canEditMeta ? 'Add parent' : 'None'" :disabled="! $canEditMeta" />
                        </dd>
                    </div>
                </dl>
                @error('parent_value') <p class="mt-1 text-right text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="order-4 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20">
                <div class="-mx-4 -mt-4 mb-3 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Subtasks</h2><span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">{{ $task->children->count() }}</span>
                </div>

                <div class="space-y-1.5">
                    @forelse ($task->children as $child)
                        <a href="{{ route('tasks.show', $child) }}" wire:navigate class="flex items-center justify-between gap-2 rounded-xl border border-zinc-100 px-2.5 py-2 text-sm transition-colors hover:border-brand/30 hover:bg-brand/[0.03]">
                            <span class="flex items-center gap-1.5 truncate text-zinc-700">
                                <span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $child->task_key }}</span>
                                {{ $child->title }}
                            </span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium {{ $child->status->pillClasses() }}">{{ $child->status->label() }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-zinc-400">No subtasks yet.</p>
                    @endforelse
                </div>

                @if ($canEditMeta)
                    @if ($showAddSubtask)
                        <div class="mt-3 space-y-2.5 rounded-xl bg-zinc-50 p-3">
                            <input wire:model="subtask_title" type="text" placeholder="What's the subtask?" class="field-input">
                            @error('subtask_title') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <x-form.picker model="subtask_assigned_to" :value="$subtask_assigned_to" source="people" :label="$subtaskAssigneeName" avatar placeholder="Assign to…" :error="$errors->has('subtask_assigned_to')" />
                            @error('subtask_assigned_to') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="$set('showAddSubtask', false)" class="btn-secondary px-3 py-1.5">Cancel</button>
                                <button type="button" wire:click="addSubtask" class="btn-primary px-3 py-1.5">Add subtask</button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="$set('showAddSubtask', true)" class="-ml-2 mt-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-semibold text-brand transition-colors hover:bg-brand/5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>Add subtask</button>
                    @endif
                @endif
            </div>

            <div class="order-7 relative rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-5 has-[[aria-expanded=true]]:z-20">
                <div class="-mx-4 -mt-4 mb-3 flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 sm:-mx-5 sm:-mt-5 sm:px-5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-zinc-900">Time logged</h2><span class="text-sm font-semibold tabular-nums text-brand">{{ Duration::forHumans((float) $totalLoggedHours) }}</span>
                </div>

                <div class="space-y-1.5">
                    @forelse ($timeLogs as $log)
                        <div class="flex items-center justify-between gap-2 rounded-xl border border-zinc-100 px-2.5 py-2 text-sm">
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span class="shrink-0 whitespace-nowrap font-medium text-zinc-900">{{ $log->logged_date->format('d M') }}</span>
                                    <span class="text-zinc-400">·</span>
                                    <span class="truncate text-zinc-600">{{ $log->user->name }}</span>
                                </div>
                                @if ($log->note)
                                    <p class="truncate text-xs text-zinc-500">{{ $log->note }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">{{ Duration::forHumans((float) $log->hours) }}</span>

                                @if ($log->user_id === auth()->id() || $canEditMeta)
                                    <button type="button" wire:click="deleteTimeLog({{ $log->id }})" wire:confirm="Remove this time entry?" class="text-zinc-300 hover:text-red-600" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-400">No time logged yet.</p>
                    @endforelse
                </div>

                @if ($canLogTime)
                    @if ($showLogTime)
                        <div class="mt-3 space-y-2.5 rounded-xl bg-zinc-50 p-3">
                            <input wire:model="log_date" type="date" min="{{ $task->start_date?->toDateString() }}" max="{{ now()->toDateString() }}" class="field-input">
                            @error('log_date') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="flex items-center gap-2">
                                <input wire:model="log_hours" type="number" min="0" max="24" placeholder="Hours" class="field-input w-1/2 tabular-nums">
                                <span class="shrink-0 text-xs text-zinc-500">hrs</span>
                                <input wire:model="log_minutes" type="number" min="0" max="59" placeholder="Minutes" class="field-input w-1/2 tabular-nums">
                                <span class="shrink-0 text-xs text-zinc-500">min</span>
                            </div>
                            @error('log_hours') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('log_minutes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <input wire:model="log_note" type="text" placeholder="What did you work on? (optional)" class="field-input">

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="cancelLogTime" class="btn-secondary px-3 py-1.5">Cancel</button>
                                <button type="button" wire:click="logTime" class="btn-primary px-3 py-1.5">Log time</button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="$set('showLogTime', true)" class="-ml-2 mt-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-semibold text-brand transition-colors hover:bg-brand/5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>Log time</button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Submit for QA Testing modal --}}
    @if ($showSubmitQaModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4">
            <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-2xl">
                <h3 class="text-lg font-semibold text-zinc-900">Submit for QA Testing</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ $task->title }}</p>

                <p class="mt-4 text-sm text-zinc-500">
                    Time logged so far: <span class="font-semibold text-zinc-900">{{ Duration::forHumans($task->total_logged_hours) }}</span>
                </p>

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
</div>
