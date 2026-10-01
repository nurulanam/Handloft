<?php

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
    use WithFileUploads;

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
        auth()->user()->starredTasks()->toggle($this->task->id);
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
            'users' => User::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'availableParents' => Task::query()->where('id', '!=', $this->task->id)->orderBy('title')->get(),
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
    <div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-violet-100 px-2.5 py-1 font-mono text-xs font-semibold text-violet-700">{{ $task->task_key }}</span>

            <button type="button" wire:click="toggleStar" class="{{ $isStarred ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}" title="{{ $isStarred ? 'Unstar' : 'Star' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                    <path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" />
                </svg>
            </button>

            @if ($task->project)
                <a href="{{ route('projects.show', $task->project) }}" wire:navigate class="text-xs font-medium text-zinc-500 hover:text-brand">
                    {{ $task->project->name }} /
                </a>
            @endif
            @if ($task->parent)
                <a href="{{ route('tasks.show', $task->parent) }}" wire:navigate class="text-xs font-medium text-zinc-500 hover:text-brand">
                    {{ $task->parent->task_key }} {{ $task->parent->title }} /
                </a>
            @endif
        </div>
        <h1 class="mt-1 text-2xl font-semibold text-zinc-900">{{ $task->title }}</h1>
        <p class="mt-1 text-sm text-zinc-500">Created by {{ $task->creator->name }} on {{ $task->created_at->format('d M Y') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Description --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5" x-data="lazyQuillEditor(@js($task->description))">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-zinc-900">Description</h2>
                    @if ($canEditMeta)
                        <button type="button" x-show="!editing" @click="startEditing()" class="text-xs font-medium text-brand hover:underline">Edit</button>
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
                                class="ql-editor mt-2 p-0! text-sm! text-zinc-600 [&_a]:text-brand [&_a]:underline [&_blockquote]:text-zinc-500 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1"
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
                        <p class="mt-2 text-sm italic text-zinc-400">No description provided.</p>
                    @endif
                </div>

                @if ($canEditMeta)
                    {{-- The Quill instance is created lazily on the first "Edit" click (see
                         lazyQuillEditor in app.js) and never destroyed afterwards — Quill measures
                         text layout on init, so creating it while hidden (display:none) breaks it,
                         and recreating it on every edit leaks a document-level listener. --}}
                    <div x-show="editing" x-cloak wire:ignore>
                        <div class="mt-3">
                            <div x-ref="editor" class="min-h-32 rounded-b-lg border border-zinc-300 bg-white text-sm [&_.ql-toolbar]:rounded-t-lg [&_.ql-toolbar]:border-zinc-300"></div>
                            <input type="hidden" x-ref="input" wire:model="description">
                        </div>

                        <div class="mt-3 flex justify-end gap-3">
                            <button type="button" @click="editing = false" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</button>
                            <button type="button" @click="$wire.saveDescription().then(() => editing = false)" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">Save</button>
                        </div>
                    </div>
                    @error('description') <p x-show="editing" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>

            {{-- Attachments (task-level + comment attachments, combined) --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-semibold text-zinc-900">Attachments ({{ $allAttachments->count() }})</h2>

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
                    <div class="mt-3 border-t border-zinc-100 pt-3">
                        <input wire:model="newAttachments" type="file" multiple class="block w-full text-xs text-zinc-500 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-2 file:py-1 file:text-xs">
                        @error('newAttachments.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                        @if (! empty($newAttachments))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($newAttachments as $index => $file)
                                    <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 py-1 pl-2 pr-1 text-xs text-zinc-700">
                                        @if (str($file->getMimeType())->startsWith('image/'))
                                            <img src="{{ $file->temporaryUrl() }}" class="size-5 rounded object-cover">
                                        @endif
                                        {{ $file->getClientOriginalName() }}
                                        <button type="button" wire:click="removeNewAttachment({{ $index }})" class="rounded-full p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700" title="Remove">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3">
                                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                            </svg>
                                        </button>
                                    </span>
                                @endforeach
                                <button type="button" wire:click="addAttachments" class="rounded-lg bg-brand px-3 py-1 text-xs font-semibold text-white hover:bg-brand/90">Upload</button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Comments --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-semibold text-zinc-900">Comments</h2>

                <div class="space-y-4">
                    @forelse ($task->comments as $comment)
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-600">
                                {{ strtoupper(substr($comment->user->name, 0, 1)) }}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900">{{ $comment->user->name }}</span>
                                    <span class="text-xs text-zinc-400">{{ $comment->created_at->format('d M Y — h:i A') }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line text-sm text-zinc-600">{{ $comment->body }}</p>
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
                        <p class="text-sm text-zinc-400">No comments yet.</p>
                    @endforelse
                </div>

                @if ($canComment)
                    <div class="mt-5 border-t border-zinc-100 pt-4">
                        <textarea wire:model="newComment" rows="3" placeholder="Add a comment…" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"></textarea>
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
                            <input type="file" wire:model="commentAttachments" multiple class="text-xs text-zinc-500 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-2 file:py-1 file:text-xs">
                            <button type="button" wire:click="addComment" class="shrink-0 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">Comment</button>
                        </div>
                        @error('commentAttachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            {{-- Activity Timeline --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-semibold text-zinc-900">Activity Timeline</h2>
                <ol class="space-y-4 border-l border-zinc-200 pl-4">
                    @foreach ($timeline as $entry)
                        <li>
                            <p class="text-sm text-zinc-900">{{ $entry->description }}</p>
                            <p class="text-xs text-zinc-500">{{ $entry->occurred_at->format('d M Y — h:i A') }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
            {{-- Status dropdown menu --}}
            <div class="relative inline-block" x-data="dropdownMenu()">
                <button
                    type="button"
                    @click="open = ! open"
                    @disabled(! $canEditStatus)
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium {{ $task->status->pillClasses() }}"
                >
                    {{ $task->status->label() }}
                    @if ($canEditStatus)
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 opacity-60">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    @endif
                </button>

                @if ($canEditStatus)
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute left-0 z-20 mt-1 w-44 rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                        @foreach ($availableStatuses as $option)
                            <button type="button" wire:click="saveStatus('{{ $option->value }}')" @click="open = false" class="block w-full px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                {{ $option->label() }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="rounded-lg border border-zinc-200 bg-white" x-data="{ open: true }">
                <button type="button" @click="open = ! open" class="flex w-full items-center gap-1.5 px-4 py-3 text-left">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-zinc-400 transition-transform" :class="open ? 'rotate-90' : ''">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-semibold text-zinc-900">Details</span>
                </button>

                <div x-show="open" x-collapse class="divide-y divide-zinc-100 px-4 pb-2">
                    {{-- Assignee --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Assignee</span>
                        <div class="relative" x-data="dropdownMenu()">
                            <button type="button" @click="open = ! open" @disabled(! $canReassign) class="flex items-center gap-2 {{ $canReassign ? 'hover:opacity-75' : '' }}">
                                @if ($currentAssignee)
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($currentAssignee->name) }}</span>
                                    <span class="text-sm text-zinc-900">{{ $currentAssignee->name }}</span>
                                @else
                                    <span class="text-sm text-zinc-400">{{ $canReassign ? 'Add assignee' : 'Unassigned' }}</span>
                                @endif
                            </button>

                            @if ($canReassign)
                                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 max-h-60 w-48 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    @foreach ($users as $option)
                                        <button type="button" wire:click="saveAssignee({{ $option->id }})" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand text-[9px] font-semibold text-white">{{ \App\Support\Avatar::initials($option->name) }}</span>
                                            {{ $option->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Reporter --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Reporter</span>
                        <div class="relative" x-data="dropdownMenu()">
                            <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="flex items-center gap-2 {{ $canEditMeta ? 'hover:opacity-75' : '' }}">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($task->creator->name) }}</span>
                                <span class="text-sm text-zinc-900">{{ $task->creator->name }}</span>
                            </button>

                            @if ($canEditMeta)
                                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 max-h-60 w-48 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    @foreach ($users as $option)
                                        <button type="button" wire:click="saveReporter({{ $option->id }})" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[9px] font-semibold text-white">{{ \App\Support\Avatar::initials($option->name) }}</span>
                                            {{ $option->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- QA / Reviewer --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">QA / Reviewer</span>
                        <div class="relative" x-data="dropdownMenu()">
                            @if ($task->qa)
                                <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="flex items-center gap-2 {{ $canEditMeta ? 'hover:opacity-75' : '' }}">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($task->qa->name) }}</span>
                                    <span class="text-sm text-zinc-900">{{ $task->qa->name }}</span>
                                </button>
                            @else
                                <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="text-sm text-zinc-400 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                    {{ $canEditMeta ? 'Add reviewer' : 'None' }}
                                </button>
                            @endif

                            @if ($canEditMeta)
                                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 max-h-60 w-48 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    <button type="button" wire:click="saveQa(null)" @click="open = false" class="block w-full px-3 py-2 text-left text-sm text-zinc-400 hover:bg-zinc-50">Unassigned</button>
                                    @foreach ($users as $option)
                                        <button type="button" wire:click="saveQa({{ $option->id }})" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[9px] font-semibold text-white">{{ \App\Support\Avatar::initials($option->name) }}</span>
                                            {{ $option->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Priority --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Priority</span>
                        <div class="relative" x-data="dropdownMenu()">
                            <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="flex items-center gap-1.5 text-sm text-zinc-900 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 {{ $task->priority->colorClass() }}">
                                    <path d="M2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" />
                                </svg>
                                {{ $task->priority->label() }}
                            </button>

                            @if ($canEditMeta)
                                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 w-40 rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    @foreach (TaskPriority::cases() as $option)
                                        <button type="button" wire:click="savePriority('{{ $option->value }}')" @click="open = false" class="flex w-full items-center gap-1.5 px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 {{ $option->colorClass() }}">
                                                <path d="M2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" />
                                            </svg>
                                            {{ $option->label() }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Due Date --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Due date</span>
                        <div class="relative">
                            <button type="button" wire:click="startEditField('due_date')" @disabled(! $canEditMeta) class="text-sm {{ $task->deadline ? 'text-zinc-900' : 'text-zinc-400' }} {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                {{ $task->deadline?->format('d M Y') ?? ($canEditMeta ? 'Add due date' : 'None') }}
                            </button>

                            @if ($editingField === 'due_date')
                                <div class="absolute right-0 z-20 mt-1 w-56 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg">
                                    <input wire:model="due_date_value" type="date" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    @error('due_date_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                        <button type="button" wire:click="saveDueDate" class="text-xs font-medium text-brand hover:underline">Save</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Category --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Category</span>
                        <span class="text-sm text-zinc-900">{{ $task->category?->name ?? '—' }}</span>
                    </div>

                    {{-- Project --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Project</span>
                        <div class="relative" x-data="dropdownMenu()">
                            @if ($task->project)
                                <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="inline-flex items-center gap-1 rounded border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700 {{ $canEditMeta ? 'hover:border-brand/40' : '' }}">
                                    {{ $task->project->name }}
                                </button>
                            @else
                                <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="text-sm text-zinc-400 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                    {{ $canEditMeta ? 'Add project' : 'None' }}
                                </button>
                            @endif

                            @if ($canEditMeta)
                                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 max-h-60 w-48 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    <button type="button" wire:click="saveProject(null)" @click="open = false" class="block w-full px-3 py-2 text-left text-sm text-zinc-400 hover:bg-zinc-50">None</button>
                                    @foreach ($projects as $option)
                                        <button type="button" wire:click="saveProject({{ $option->id }})" @click="open = false" class="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                            {{ $option->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Parent --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Parent</span>
                        <div class="relative" x-data="dropdownMenu()">
                            @if ($task->parent)
                                <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="inline-flex items-center gap-1 rounded border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700 {{ $canEditMeta ? 'hover:border-brand/40' : '' }}">
                                    <span class="size-1.5 rounded-full bg-brand"></span>
                                    {{ $task->parent->task_key }} {{ $task->parent->title }}
                                </button>
                            @else
                                <button type="button" @click="open = ! open" @disabled(! $canEditMeta) class="text-sm text-zinc-400 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                    {{ $canEditMeta ? 'Add parent' : 'None' }}
                                </button>
                            @endif

                            @if ($canEditMeta)
                                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 max-h-60 w-56 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                    <button type="button" wire:click="saveParent(null)" @click="open = false" class="block w-full px-3 py-2 text-left text-sm text-zinc-400 hover:bg-zinc-50">None</button>
                                    @foreach ($availableParents as $option)
                                        <button type="button" wire:click="saveParent({{ $option->id }})" @click="open = false" class="block w-full truncate px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                            {{ $option->task_key }} {{ $option->title }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @error('parent_value') <p class="mt-1 max-w-xs text-right text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Subtasks</h3>

                <div class="mt-2 space-y-2">
                    @forelse ($task->children as $child)
                        <a href="{{ route('tasks.show', $child) }}" wire:navigate class="flex items-center justify-between gap-2 rounded-md border border-zinc-100 px-2 py-1.5 text-sm hover:border-brand/40">
                            <span class="flex items-center gap-1.5 truncate text-zinc-700">
                                <span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $child->task_key }}</span>
                                {{ $child->title }}
                            </span>
                            <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-600">{{ $child->status->label() }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-zinc-400">No subtasks.</p>
                    @endforelse
                </div>

                @if ($canEditMeta)
                    @if ($showAddSubtask)
                        <div class="mt-3 space-y-2 rounded-md border border-zinc-200 p-2">
                            <input wire:model="subtask_title" type="text" placeholder="Subtask title" class="block w-full rounded-md border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            @error('subtask_title') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <select wire:model="subtask_assigned_to" class="block w-full rounded-md border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                <option value="">Assign to…</option>
                                @foreach ($users as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                            @error('subtask_assigned_to') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="flex justify-end gap-3">
                                <button type="button" wire:click="$set('showAddSubtask', false)" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                <button type="button" wire:click="addSubtask" class="text-xs font-medium text-brand hover:underline">Add</button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="$set('showAddSubtask', true)" class="mt-3 text-xs font-medium text-brand hover:underline">+ Add subtask</button>
                    @endif
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Time Logs</h3>
                    <span class="text-sm font-semibold text-brand">{{ Duration::forHumans((float) $totalLoggedHours) }}</span>
                </div>

                <div class="mt-2 space-y-2">
                    @forelse ($timeLogs as $log)
                        <div class="flex items-center justify-between gap-2 rounded-md border border-zinc-100 px-2 py-1.5 text-sm">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium text-zinc-900">{{ $log->logged_date->format('d M Y') }}</span>
                                    <span class="text-zinc-400">·</span>
                                    <span class="text-zinc-600">{{ $log->user->name }}</span>
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
                        <div class="mt-3 space-y-2 rounded-md border border-zinc-200 p-2">
                            <input wire:model="log_date" type="date" min="{{ $task->start_date?->toDateString() }}" max="{{ now()->toDateString() }}" class="block w-full rounded-md border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            @error('log_date') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="flex items-center gap-2">
                                <input wire:model="log_hours" type="number" min="0" max="24" placeholder="Hours" class="block w-1/2 rounded-md border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                <span class="shrink-0 text-xs text-zinc-500">hrs</span>
                                <input wire:model="log_minutes" type="number" min="0" max="59" placeholder="Minutes" class="block w-1/2 rounded-md border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                <span class="shrink-0 text-xs text-zinc-500">min</span>
                            </div>
                            @error('log_hours') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('log_minutes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <input wire:model="log_note" type="text" placeholder="Note (optional)" class="block w-full rounded-md border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">

                            <div class="flex justify-end gap-3">
                                <button type="button" wire:click="cancelLogTime" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                <button type="button" wire:click="logTime" class="text-xs font-medium text-brand hover:underline">Save</button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="$set('showLogTime', true)" class="mt-3 text-xs font-medium text-brand hover:underline">+ Log time</button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Submit for QA Testing modal --}}
    @if ($showSubmitQaModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-zinc-900">Submit for QA Testing</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ $task->title }}</p>

                <p class="mt-4 text-sm text-zinc-500">
                    Time logged so far: <span class="font-semibold text-zinc-900">{{ Duration::forHumans($task->total_logged_hours) }}</span>
                </p>

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
</div>
