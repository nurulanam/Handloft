<?php

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\FileSize;
use App\Support\Html;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Create Task')] class extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    public string $assigned_to = '';

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
            'qa_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'task_category_id' => ['nullable', 'exists:task_categories,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $assignee = User::findOrFail($data['assigned_to']);

        $task = $workflow->createTask([
            'title' => $data['title'],
            'description' => Html::sanitize($data['description']),
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
            'users' => User::query()->orderBy('name')->get(),
            'categories' => TaskCategory::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Create Task</h1>
            <p class="text-sm text-zinc-500">Assign work to yourself or any team member.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.index') }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</a>
            <button type="submit" form="create-task-form" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled">
                Create Task
            </button>
        </div>
    </div>

    <form id="create-task-form" wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Left: Title + Description --}}
            <div class="space-y-4 lg:col-span-2">
                <div>
                    <input
                        wire:model="title"
                        type="text"
                        placeholder="Task title"
                        class="block w-full border-0 border-b border-zinc-200 bg-transparent px-0 py-2 text-2xl font-semibold text-zinc-900 placeholder:text-zinc-300 focus:border-brand focus:outline-none focus:ring-0"
                    >
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Description</label>
                    <div wire:ignore x-data="quillEditor(@js($description))" class="mt-1">
                        <div x-ref="editor" class="min-h-64 rounded-b-lg border border-zinc-300 bg-white text-sm [&_.ql-toolbar]:rounded-t-lg [&_.ql-toolbar]:border-zinc-300"></div>
                        <input type="hidden" x-ref="input" wire:model="description">
                    </div>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Attachments</label>
                    <input wire:model="attachments" type="file" multiple class="mt-1 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm">
                    @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if (! empty($attachments))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($attachments as $index => $file)
                                <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 py-1 pl-2 pr-1 text-xs text-zinc-700">
                                    @if (str($file->getMimeType())->startsWith('image/'))
                                        <img src="{{ $file->temporaryUrl() }}" class="size-5 rounded object-cover">
                                    @endif
                                    {{ $file->getClientOriginalName() }}
                                    <span class="text-zinc-400">({{ FileSize::forHumans($file->getSize()) }})</span>
                                    <button type="button" wire:click="removeAttachment({{ $index }})" class="rounded-full p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3">
                                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Notes</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Right: Details --}}
            <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                {{-- Status (fixed — new tasks always start Pending) --}}
                <div>
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700">
                        Pending
                    </span>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white" x-data="{ open: true }">
                    <button type="button" @click="open = ! open" class="flex w-full items-center gap-1.5 px-4 py-3 text-left">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-zinc-400 transition-transform" :class="open ? 'rotate-90' : ''">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-semibold text-zinc-900">Details</span>
                    </button>

                    <div x-show="open" x-collapse class="divide-y divide-zinc-100 px-4 pb-2">
                        {{-- Assignee --}}
                        <div
                            class="flex items-center justify-between gap-3 py-2.5"
                            x-data="inlineSelect(@js($assigned_to), @js(optional($users->firstWhere('id', $assigned_to))->name ?? 'Select assignee'))"
                        >
                            <span class="text-sm text-zinc-500">Assignee</span>

                            <div x-show="!editing">
                                <button type="button" @click="editing = true" class="flex items-center gap-2 hover:opacity-75">
                                    <template x-if="value">
                                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-semibold text-white" x-text="initialsOf(label)"></span>
                                    </template>
                                    <span class="text-sm" :class="value ? 'text-zinc-900' : 'text-zinc-400'" x-text="label"></span>
                                </button>
                            </div>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <select wire:model.live="assigned_to" @change="sync($event)" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">Select assignee</option>
                                    @foreach ($users as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @error('assigned_to') <p class="pb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                        {{-- Reporter (fixed — the person filling out this form) --}}
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-zinc-500">Reporter</span>
                            <span class="flex items-center gap-2">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials(auth()->user()->name) }}</span>
                                <span class="text-sm text-zinc-900">{{ auth()->user()->name }}</span>
                            </span>
                        </div>

                        {{-- QA / Reviewer --}}
                        <div
                            class="flex items-center justify-between gap-3 py-2.5"
                            x-data="inlineSelect(@js($qa_id), @js(optional($users->firstWhere('id', $qa_id))->name ?? 'Add reviewer'))"
                        >
                            <span class="text-sm text-zinc-500">QA / Reviewer</span>

                            <div x-show="!editing">
                                <button type="button" @click="editing = true" class="flex items-center gap-2 hover:opacity-75">
                                    <template x-if="value">
                                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white" x-text="initialsOf(label)"></span>
                                    </template>
                                    <span class="text-sm" :class="value ? 'text-zinc-900' : 'text-zinc-400'" x-text="label"></span>
                                </button>
                            </div>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <select wire:model.live="qa_id" @change="sync($event)" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">None</option>
                                    @foreach ($users as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Priority --}}
                        <div
                            class="flex items-center justify-between gap-3 py-2.5"
                            x-data="inlineSelect(@js($priority), @js(\App\Enums\TaskPriority::from($priority)->label()))"
                        >
                            <span class="text-sm text-zinc-500">Priority</span>

                            <div x-show="!editing">
                                <button type="button" @click="editing = true" class="flex items-center gap-1.5 text-sm text-zinc-900 hover:text-brand">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5" :class="priorityColor(value)">
                                        <path d="M2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" />
                                    </svg>
                                    <span x-text="label"></span>
                                </button>
                            </div>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <select wire:model.live="priority" @change="sync($event)" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    @foreach (TaskPriority::cases() as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Due date --}}
                        <div class="flex items-center justify-between gap-3 py-2.5" x-data="{ editing: false }">
                            <span class="text-sm text-zinc-500">Due date</span>

                            <button type="button" x-show="!editing" @click="editing = true" class="text-sm {{ $deadline ? 'text-zinc-900' : 'text-zinc-400' }} hover:text-brand">
                                {{ $deadline ? \Illuminate\Support\Carbon::parse($deadline)->format('d M Y') : 'Add due date' }}
                            </button>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <input wire:model.live="deadline" type="date" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            </div>
                        </div>
                        @error('deadline') <p class="pb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                        {{-- Start date --}}
                        <div class="flex items-center justify-between gap-3 py-2.5" x-data="{ editing: false }">
                            <span class="text-sm text-zinc-500">Start date</span>

                            <button type="button" x-show="!editing" @click="editing = true" class="text-sm {{ $start_date ? 'text-zinc-900' : 'text-zinc-400' }} hover:text-brand">
                                {{ $start_date ? \Illuminate\Support\Carbon::parse($start_date)->format('d M Y') : 'Add start date' }}
                            </button>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <input wire:model.live="start_date" type="date" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            </div>
                        </div>

                        {{-- Category --}}
                        <div
                            class="flex items-center justify-between gap-3 py-2.5"
                            x-data="inlineSelect(@js($task_category_id), @js(optional($categories->firstWhere('id', $task_category_id))->name ?? 'None'))"
                        >
                            <span class="text-sm text-zinc-500">Category</span>

                            <div x-show="!editing">
                                <button type="button" @click="editing = true" class="text-sm hover:text-brand" :class="value ? 'text-zinc-900' : 'text-zinc-400'" x-text="label"></button>
                            </div>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <select wire:model.live="task_category_id" @change="sync($event)" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">None</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Project --}}
                        <div
                            class="flex items-center justify-between gap-3 py-2.5"
                            x-data="inlineSelect(@js($project_id), @js(optional($projects->firstWhere('id', $project_id))->name ?? 'None'))"
                        >
                            <span class="text-sm text-zinc-500">Project</span>

                            <div x-show="!editing">
                                <button type="button" @click="editing = true" class="text-sm hover:text-brand" :class="value ? 'text-zinc-900' : 'text-zinc-400'" x-text="label"></button>
                            </div>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <select wire:model.live="project_id" @change="sync($event)" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="">None</option>
                                    @foreach ($projects as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
