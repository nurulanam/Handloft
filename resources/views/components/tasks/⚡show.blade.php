<?php

use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Html;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Task')] class extends Component
{
    use WithFileUploads;

    public Task $task;

    public bool $showCompleteModal = false;

    public string $actual_hours = '';

    public string $completion_note = '';

    public string $description = '';

    public ?string $editingField = null;

    public string $status_value = '';

    public string $assignee_value = '';

    public string $reporter_value = '';

    public string $priority_value = '';

    public string $due_date_value = '';

    public string $qa_value = '';

    public string $parent_value = '';

    public string $project_value = '';

    public string $newComment = '';

    /** @var array<int, mixed> */
    public array $commentAttachments = [];

    /** @var array<int, mixed> */
    public array $newAttachments = [];

    public bool $showAddSubtask = false;

    public string $subtask_title = '';

    public string $subtask_assigned_to = '';

    public function mount(Task $task): void
    {
        Gate::authorize('view', $task);

        $this->task = $task;
        $this->description = (string) $task->description;
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
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

    public function startEditField(string $field): void
    {
        $this->authorizeField($field);

        match ($field) {
            'status' => $this->status_value = $this->task->status->value,
            'assignee' => $this->assignee_value = (string) $this->task->currentAssignee()?->id,
            'reporter' => $this->reporter_value = (string) $this->task->created_by,
            'priority' => $this->priority_value = $this->task->priority->value,
            'due_date' => $this->due_date_value = $this->task->deadline?->toDateString() ?? '',
            'qa' => $this->qa_value = (string) $this->task->qa_id,
            'parent' => $this->parent_value = (string) $this->task->parent_task_id,
            'project' => $this->project_value = (string) $this->task->project_id,
            default => null,
        };

        $this->editingField = $field;
    }

    public function cancelEditField(): void
    {
        $this->editingField = null;
        $this->resetErrorBag();
    }

    private function authorizeField(string $field): void
    {
        $allowed = match ($field) {
            'status' => Gate::allows('complete', $this->task) || Gate::allows('cancel', $this->task) || Gate::allows('updateMeta', $this->task),
            'assignee', 'reporter' => Gate::allows('reassign', $this->task),
            default => Gate::allows('updateMeta', $this->task),
        };

        abort_unless($allowed, 403);
    }

    public function saveStatus(TaskWorkflowService $workflow): void
    {
        $data = $this->validate([
            'status_value' => ['required', Rule::in(array_column(TaskStatus::cases(), 'value'))],
        ]);

        if ($data['status_value'] === TaskStatus::Completed->value) {
            Gate::authorize('complete', $this->task);

            $this->editingField = null;
            $this->showCompleteModal = true;

            return;
        }

        if ($data['status_value'] === TaskStatus::Cancelled->value) {
            Gate::authorize('cancel', $this->task);
        } else {
            Gate::authorize('updateMeta', $this->task);
        }

        $newStatus = TaskStatus::from($data['status_value']);

        if ($this->task->status !== $newStatus) {
            $this->task->update(['status' => $newStatus]);
            $this->logMetaChange("Status changed to {$newStatus->label()}");
        }

        $this->editingField = null;
        $this->task->refresh();
    }

    public function saveAssignee(TaskWorkflowService $workflow): void
    {
        Gate::authorize('reassign', $this->task);

        $data = $this->validate(['assignee_value' => ['required', 'exists:users,id']]);

        $workflow->reassignTask($this->task, User::findOrFail($data['assignee_value']), auth()->user());

        $this->editingField = null;
        $this->task->refresh();
    }

    public function saveReporter(): void
    {
        Gate::authorize('reassign', $this->task);

        $data = $this->validate(['reporter_value' => ['required', 'exists:users,id']]);

        $this->task->update(['created_by' => $data['reporter_value']]);
        $this->logMetaChange('Reporter updated');

        $this->editingField = null;
        $this->task->refresh();
    }

    public function savePriority(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate([
            'priority_value' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
        ]);

        $this->task->update(['priority' => $data['priority_value']]);
        $this->logMetaChange('Priority updated to '.TaskPriority::from($data['priority_value'])->label());

        $this->editingField = null;
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

    public function saveQa(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate(['qa_value' => ['nullable', 'exists:users,id']]);

        $this->task->update(['qa_id' => $data['qa_value'] ?: null]);
        $this->logMetaChange('QA/Reviewer updated');

        $this->editingField = null;
        $this->task->refresh();
    }

    public function saveProject(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate(['project_value' => ['nullable', 'exists:projects,id']]);

        $this->task->update(['project_id' => $data['project_value'] ?: null]);
        $this->logMetaChange('Project updated');

        $this->editingField = null;
        $this->task->refresh();
    }

    public function saveParent(): void
    {
        Gate::authorize('updateMeta', $this->task);

        $data = $this->validate(['parent_value' => ['nullable', 'exists:tasks,id']]);

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

        $this->editingField = null;
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

    private function logMetaChange(string $description): void
    {
        $this->task->activities()->create([
            'causer_id' => auth()->id(),
            'type' => TaskActivityType::MetaUpdated,
            'description' => $description,
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

        $canComplete = Gate::allows('complete', $this->task);
        $canCancel = Gate::allows('cancel', $this->task);

        return [
            'timeline' => $this->task->activities()->with('causer')->get(),
            'currentAssignee' => $this->task->currentAssignee(),
            'users' => User::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'availableParents' => Task::query()->where('id', '!=', $this->task->id)->orderBy('title')->get(),
            'availableStatuses' => collect(TaskStatus::cases())->filter(
                fn ($s) => ($s !== TaskStatus::Completed || $canComplete) && ($s !== TaskStatus::Cancelled || $canCancel)
            ),
            'allAttachments' => $taskAttachments->concat($commentAttachments)->sortByDesc('created_at')->values(),
            'canReassign' => Gate::allows('reassign', $this->task),
            'canComplete' => $canComplete,
            'canEditStatus' => $canComplete || $canCancel || Gate::allows('updateMeta', $this->task),
            'canEditMeta' => Gate::allows('updateMeta', $this->task),
            'canComment' => Gate::allows('comment', $this->task),
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-violet-100 px-2.5 py-1 font-mono text-xs font-semibold text-violet-700">{{ $task->task_key }}</span>
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
                            <div
                                x-ref="descriptionContent"
                                class="ql-editor mt-2 p-0! text-sm! text-zinc-600 [&_a]:text-brand [&_a]:underline [&_blockquote]:text-zinc-500 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1"
                                :class="expanded ? '' : 'line-clamp-10'"
                            >
                                {!! $task->description !!}
                            </div>

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
            {{-- Status dropdown --}}
            <div>
                @if ($editingField === 'status')
                    <div class="flex items-center gap-2">
                        <select wire:model="status_value" class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            @foreach ($availableStatuses as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="saveStatus" class="text-xs font-medium text-brand hover:underline">Save</button>
                        <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                    </div>
                @else
                    <button
                        type="button"
                        wire:click="startEditField('status')"
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
                        @if ($editingField === 'assignee')
                            <div class="max-w-[65%] flex-1">
                                <select wire:model="assignee_value" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    @foreach ($users as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                @error('assignee_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="saveAssignee" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @else
                            <button type="button" wire:click="startEditField('assignee')" @disabled(! $canReassign) class="flex items-center gap-2 {{ $canReassign ? 'hover:opacity-75' : '' }}">
                                @if ($currentAssignee)
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($currentAssignee->name) }}</span>
                                    <span class="text-sm text-zinc-900">{{ $currentAssignee->name }}</span>
                                @else
                                    <span class="text-sm text-zinc-400">{{ $canReassign ? 'Add assignee' : 'Unassigned' }}</span>
                                @endif
                            </button>
                        @endif
                    </div>

                    {{-- Reporter --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Reporter</span>
                        @if ($editingField === 'reporter')
                            <div class="max-w-[65%] flex-1">
                                <select wire:model="reporter_value" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    @foreach ($users as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                @error('reporter_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="saveReporter" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @else
                            <button type="button" wire:click="startEditField('reporter')" @disabled(! $canReassign) class="flex items-center gap-2 {{ $canReassign ? 'hover:opacity-75' : '' }}">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($task->creator->name) }}</span>
                                <span class="text-sm text-zinc-900">{{ $task->creator->name }}</span>
                            </button>
                        @endif
                    </div>

                    {{-- QA / Reviewer --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">QA / Reviewer</span>
                        @if ($editingField === 'qa')
                            <div class="flex-1 max-w-[65%]">
                                <select wire:model="qa_value" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="saveQa" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @elseif ($task->qa)
                            <button type="button" wire:click="startEditField('qa')" @disabled(! $canEditMeta) class="flex items-center gap-2 {{ $canEditMeta ? 'hover:opacity-75' : '' }}">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($task->qa->name) }}</span>
                                <span class="text-sm text-zinc-900">{{ $task->qa->name }}</span>
                                @if ($canEditMeta)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-zinc-400">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </button>
                        @else
                            <button type="button" wire:click="startEditField('qa')" @disabled(! $canEditMeta) class="text-sm text-zinc-400 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                {{ $canEditMeta ? 'Add reviewer' : 'None' }}
                            </button>
                        @endif
                    </div>

                    {{-- Priority --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Priority</span>
                        @if ($editingField === 'priority')
                            <div class="flex-1 max-w-[65%]">
                                <select wire:model="priority_value" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    @foreach (TaskPriority::cases() as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="savePriority" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @else
                            <button type="button" wire:click="startEditField('priority')" @disabled(! $canEditMeta) class="flex items-center gap-1.5 text-sm text-zinc-900 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 {{ $task->priority->colorClass() }}">
                                    <path d="M2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" />
                                </svg>
                                {{ $task->priority->label() }}
                                @if ($canEditMeta)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-zinc-400">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </button>
                        @endif
                    </div>

                    {{-- Due Date --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Due date</span>
                        @if ($editingField === 'due_date')
                            <div class="flex-1 max-w-[65%]">
                                <input wire:model="due_date_value" type="date" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('due_date_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="saveDueDate" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @else
                            <button type="button" wire:click="startEditField('due_date')" @disabled(! $canEditMeta) class="text-sm {{ $task->deadline ? 'text-zinc-900' : 'text-zinc-400' }} {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                {{ $task->deadline?->format('d M Y') ?? ($canEditMeta ? 'Add due date' : 'None') }}
                            </button>
                        @endif
                    </div>

                    {{-- Category --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Category</span>
                        <span class="text-sm text-zinc-900">{{ $task->category?->name ?? '—' }}</span>
                    </div>

                    {{-- Project --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Project</span>
                        @if ($editingField === 'project')
                            <div class="flex-1 max-w-[65%]">
                                <select wire:model="project_value" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">None</option>
                                    @foreach ($projects as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                @error('project_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="saveProject" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @elseif ($task->project)
                            <button type="button" wire:click="startEditField('project')" @disabled(! $canEditMeta) class="inline-flex items-center gap-1 rounded border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700 {{ $canEditMeta ? 'hover:border-brand/40' : '' }}">
                                {{ $task->project->name }}
                            </button>
                        @else
                            <button type="button" wire:click="startEditField('project')" @disabled(! $canEditMeta) class="text-sm text-zinc-400 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                {{ $canEditMeta ? 'Add project' : 'None' }}
                            </button>
                        @endif
                    </div>

                    {{-- Parent --}}
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-zinc-500">Parent</span>
                        @if ($editingField === 'parent')
                            <div class="flex-1 max-w-[65%]">
                                <select wire:model="parent_value" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">None</option>
                                    @foreach ($availableParents as $option)
                                        <option value="{{ $option->id }}">{{ $option->task_key }} {{ $option->title }}</option>
                                    @endforeach
                                </select>
                                @error('parent_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-1 flex justify-end gap-2">
                                    <button type="button" wire:click="cancelEditField" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                                    <button type="button" wire:click="saveParent" class="text-xs font-medium text-brand hover:underline">Save</button>
                                </div>
                            </div>
                        @elseif ($task->parent)
                            <button type="button" wire:click="{{ $canEditMeta ? "startEditField('parent')" : '' }}" class="inline-flex items-center gap-1 rounded border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-700 {{ $canEditMeta ? 'hover:border-brand/40' : '' }}">
                                <span class="size-1.5 rounded-full bg-brand"></span>
                                {{ $task->parent->task_key }} {{ $task->parent->title }}
                            </button>
                        @else
                            <button type="button" wire:click="startEditField('parent')" @disabled(! $canEditMeta) class="text-sm text-zinc-400 {{ $canEditMeta ? 'hover:text-brand' : '' }}">
                                {{ $canEditMeta ? 'Add parent' : 'None' }}
                            </button>
                        @endif
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
        </div>
    </div>

    {{-- Complete Task modal --}}
    @if ($showCompleteModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/50 px-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-zinc-900">Complete Task</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ $task->title }}</p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-zinc-700">Actual Hours Worked</label>
                    <input wire:model="actual_hours" type="number" step="0.1" min="0.1" max="24" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                    @error('actual_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-zinc-700">Completion Note (Optional)</label>
                    <textarea wire:model="completion_note" rows="2" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"></textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showCompleteModal', false)" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</button>
                    <button type="button" wire:click="complete" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">Complete Task</button>
                </div>
            </div>
        </div>
    @endif
</div>
