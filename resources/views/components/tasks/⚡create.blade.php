<?php

use App\Enums\TaskPriority;
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

    public string $notes = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

    public function mount(): void
    {
        Gate::authorize('create', Task::class);
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
            <div class="space-y-4">
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">Assigned To</label>
                    <select wire:model="assigned_to" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        <option value="">Select a team member</option>
                        @foreach ($users as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">QA / Reviewer</label>
                    <select wire:model="qa_id" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        <option value="">None</option>
                        @foreach ($users as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('qa_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">Priority</label>
                    <select wire:model="priority" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @foreach (TaskPriority::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">Category/Project</label>
                    <select wire:model="task_category_id" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        <option value="">None</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('task_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">Start Date</label>
                    <input wire:model="start_date" type="date" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                    @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">Deadline</label>
                    <input wire:model="deadline" type="date" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                    @error('deadline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </form>
</div>
