<?php

use App\Enums\TaskPriority;
use App\Livewire\Concerns\SearchesPickerOptions;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Html;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Create Task')] class extends Component
{
    use SearchesPickerOptions, WithFileUploads;

    public string $title = '';

    public string $description = '';

    public string $assigned_to = '';

    public string $reporter_id = '';

    public string $qa_id = '';

    public string $priority = 'medium';

    public string $start_date = '';

    public string $deadline = '';

    public string $task_category_id = '';

    public string $project_id = '';

    public string $notes = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

    public function mount(): void
    {
        Gate::authorize('create', Task::class);

        $this->project_id = (string) request()->query('project', '');
        $this->reporter_id = (string) auth()->id();
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function save(TaskWorkflowService $workflow): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['required', 'exists:users,id'],
            'reporter_id' => ['required', 'exists:users,id'],
            'qa_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'task_category_id' => ['nullable', 'exists:task_categories,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ], [
            'title.required' => 'Give the task a short title.',
            'assigned_to.required' => 'Choose who will do this task.',
            'deadline.after_or_equal' => 'The due date can\'t be before the start date.',
            'attachments.*.max' => 'Each file can be up to 10 MB.',
        ]);

        $assignee = User::findOrFail($data['assigned_to']);

        $task = $workflow->createTask([
            'title' => $data['title'],
            'description' => Html::sanitize($data['description']),
            'created_by' => $data['reporter_id'],
            'priority' => $data['priority'],
            'start_date' => $data['start_date'] ?: null,
            'deadline' => $data['deadline'] ?: null,
            'task_category_id' => $data['task_category_id'] ?: null,
            'project_id' => $data['project_id'] ?: null,
            'qa_id' => $data['qa_id'] ?: null,
            'notes' => $data['notes'] ?: null,
        ], auth()->user(), $assignee);

        foreach ($this->attachments as $file) {
            $task->attachments()->create([
                'uploaded_by' => auth()->id(),
                'path' => $file->store('task-attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        $this->redirect(route('tasks.show', $task), navigate: true);
    }

    public function with(): array
    {
        return [
            // People and projects are searched on demand (SearchesPickerOptions); only the chosen names are needed here.
            'names' => [
                'people' => User::query()->whereKey(array_filter([$this->assigned_to, $this->reporter_id, $this->qa_id]))->pluck('name', 'id'),
                'project' => $this->project_id ? Project::query()->whereKey($this->project_id)->value('name') : null,
            ],
            'categories' => TaskCategory::query()->orderBy('name')->get()->map(fn (TaskCategory $category) => ['value' => $category->id, 'label' => $category->name]),
        ];
    }
};
?>

@php
    $icons = [
        'task' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'files' => '<path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/>',
        'people' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'planning' => '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.3V14"/><circle cx="16" cy="16" r="6"/>',
        'organise' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
    ];
@endphp

<div>
    <x-form.header :back="route('tasks.index')" back-label="Tasks" title="Create a task" subtitle="Describe the work, choose who does it and when it's due. Everyone involved gets notified.">
        <x-slot:actions>
            <a href="{{ route('tasks.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            <x-form.submit form="create-task-form">Create task</x-form.submit>
        </x-slot:actions>
    </x-form.header>

    <form id="create-task-form" wire:submit="save">
        {{-- On phones both column wrappers are display:contents, so the cards interleave by their order-*
             classes (the task, then people and planning, then files); from lg up they're real columns. --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-6">
            <div class="contents lg:col-span-2 lg:block lg:space-y-6">
                <x-form.card class="order-1" title="What needs doing" description="A clear title, and the details someone needs to get started." :icon="$icons['task']">
                    <x-form.field label="Title" for="task-title" error="title" required>
                        <input id="task-title" wire:model="title" type="text" placeholder="e.g. Fix the checkout timeout on mobile" autocomplete="off" @class(['field-input text-base font-medium', 'field-input-error' => $errors->has('title')])>
                    </x-form.field>

                    <x-form.field label="Description" error="description" optional>
                        <div wire:ignore x-data="quillEditor(@js($description))" class="rich-editor">
                            <div x-ref="editor" data-placeholder="Steps to reproduce, links, what done looks like…"></div>
                            <input type="hidden" x-ref="input" wire:model="description">
                        </div>
                    </x-form.field>
                </x-form.card>

                <x-form.card class="order-4" title="Files & notes" description="Screenshots, specs, or anything else that helps." :icon="$icons['files']">
                    <x-form.field label="Attachments" error="attachments.*" optional>
                        <x-form.dropzone model="attachments">
                            @if (! empty($attachments))
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($attachments as $index => $file)
                                        <x-form.file-chip :file="$file" :remove="'removeAttachment('.$index.')'" />
                                    @endforeach
                                </div>
                            @endif
                        </x-form.dropzone>
                    </x-form.field>

                    <x-form.field label="Notes" for="task-notes" error="notes" optional>
                        <textarea id="task-notes" wire:model="notes" rows="3" placeholder="Anything else worth knowing…" class="field-input resize-y"></textarea>
                    </x-form.field>
                </x-form.card>
            </div>

            <div class="contents lg:sticky lg:top-20 lg:block lg:space-y-6 lg:self-start">
                <x-form.card class="order-2" title="People" description="Who does it, who asked, who checks it." :icon="$icons['people']">
                    <x-form.field label="Assignee" error="assigned_to" required>
                        <x-form.picker model="assigned_to" :value="$assigned_to" source="people" :label="$names['people'][$assigned_to] ?? null" avatar placeholder="Who will do it?" :error="$errors->has('assigned_to')" />
                    </x-form.field>

                    <x-form.field label="Reporter" error="reporter_id">
                        <x-form.picker model="reporter_id" :value="$reporter_id" source="people" :label="$names['people'][$reporter_id] ?? null" avatar placeholder="Who asked for it?" />
                    </x-form.field>

                    <x-form.field label="QA reviewer" error="qa_id" optional>
                        <x-form.picker model="qa_id" :value="$qa_id" source="people" :label="$names['people'][$qa_id] ?? null" avatar clearable clear-label="No reviewer" placeholder="Add a reviewer" />
                    </x-form.field>
                </x-form.card>

                <x-form.card class="order-3" title="Planning" description="How urgent it is, and when." :icon="$icons['planning']">
                    <x-form.field label="Priority" error="priority">
                        <div class="grid grid-cols-4 gap-1 rounded-xl bg-zinc-100 p-1" role="radiogroup" aria-label="Priority">
                            @foreach (TaskPriority::cases() as $option)
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model.live="priority" value="{{ $option->value }}" class="peer sr-only">
                                    <span class="flex items-center justify-center gap-1.5 rounded-lg px-1.5 py-1.5 text-xs font-medium text-zinc-500 transition hover:text-zinc-900 peer-checked:bg-surface peer-checked:text-zinc-900 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand/30">
                                        <span class="size-2 shrink-0 rounded-full bg-current {{ $option->colorClass() }}"></span>
                                        {{ $option->label() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </x-form.field>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <x-form.field label="Start date" error="start_date" optional>
                            <x-form.date model="start_date" :value="$start_date" placeholder="Not set" />
                        </x-form.field>

                        <x-form.field label="Due date" error="deadline" optional>
                            <x-form.date model="deadline" :value="$deadline" placeholder="No deadline" :error="$errors->has('deadline')" />
                        </x-form.field>
                    </div>
                </x-form.card>

                <x-form.card class="order-3" title="Organise" description="Where it belongs." :icon="$icons['organise']">
                    <x-form.field label="Project" error="project_id" optional>
                        <x-form.picker model="project_id" :value="$project_id" source="projects" :label="$names['project']" clearable clear-label="No project" placeholder="No project" :icon="$icons['organise']" />
                    </x-form.field>

                    <x-form.field label="Category" error="task_category_id" optional>
                        <x-form.picker model="task_category_id" :value="$task_category_id" :options="$categories" clearable clear-label="No category" placeholder="No category" icon='<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>' />
                    </x-form.field>
                </x-form.card>

                <p class="order-5 flex items-baseline gap-2 px-1 text-xs text-zinc-500">
                    <span class="size-1.5 shrink-0 -translate-y-px rounded-full bg-sky-500"></span>
                    <span>New tasks start in <span class="font-medium text-zinc-700">{{ \App\Enums\TaskStatus::Todo->label() }}</span> and move along the board from there.</span>
                </p>
            </div>
        </div>

        <x-form.actions :cancel="route('tasks.index')">Create task</x-form.actions>
    </form>
</div>
