<?php

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskWorkflowService;
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

    public string $priority = 'medium';

    public string $start_date = '';

    public string $deadline = '';

    public string $task_category_id = '';

    public string $notes = '';

    public $attachment = null;

    public function mount(): void
    {
        Gate::authorize('create', Task::class);
    }

    public function save(TaskWorkflowService $workflow): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'task_category_id' => ['nullable', 'exists:task_categories,id'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $assignee = User::findOrFail($data['assigned_to']);

        $task = $workflow->createTask([
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'priority' => $data['priority'],
            'start_date' => $data['start_date'] ?: null,
            'deadline' => $data['deadline'] ?: null,
            'task_category_id' => $data['task_category_id'] ?: null,
            'notes' => $data['notes'] ?: null,
        ], auth()->user(), $assignee);

        if ($this->attachment) {
            $task->attachments()->create([
                'uploaded_by' => auth()->id(),
                'path' => $this->attachment->store('task-attachments', 'public'),
                'original_name' => $this->attachment->getClientOriginalName(),
                'size' => $this->attachment->getSize(),
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

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Create Task</h1>
        <p class="text-sm text-slate-500">Assign work to yourself or any team member.</p>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="block text-sm font-medium text-slate-700">Task Title</label>
            <input wire:model="title" type="text" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Description</label>
            <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Assigned To</label>
                <select wire:model="assigned_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                    <option value="">Select a team member</option>
                    @foreach ($users as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Priority</label>
                <select wire:model="priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                    @foreach (TaskPriority::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Start Date</label>
                <input wire:model="start_date" type="date" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Deadline</label>
                <input wire:model="deadline" type="date" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                @error('deadline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Category/Project</label>
                <select wire:model="task_category_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
                    <option value="">None</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('task_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Attachment</label>
                <input wire:model="attachment" type="file" class="mt-1 block w-full text-sm text-slate-600">
                @error('attachment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Notes</label>
                <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('tasks.index') }}" wire:navigate class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</a>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" wire:loading.attr="disabled">
                Create Task
            </button>
        </div>
    </form>
</div>
