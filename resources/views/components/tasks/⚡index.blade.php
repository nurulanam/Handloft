<?php

use App\Models\Task;
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
    public string $tab = 'my-tasks';

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    public function tabs(): array
    {
        $tabs = [
            'my-tasks' => 'My Tasks',
            'assigned-to-me' => 'Assigned to Me',
            'assigned-by-me' => 'Assigned by Me',
        ];

        if (auth()->user()->can('view-all-tasks')) {
            $tabs = ['all' => 'All Tasks'] + $tabs;
        }

        return $tabs;
    }

    public function with(): array
    {
        $userId = auth()->id();

        $query = Task::query()->with(['category', 'currentAssignment.assignedTo', 'creator'])->latest();

        match ($this->tab) {
            'all' => $query,
            'assigned-to-me' => $query->whereHas('currentAssignment', fn ($q) => $q->where('assigned_to', $userId)),
            'assigned-by-me' => $query->whereHas('currentAssignment', fn ($q) => $q->where('assigned_by', $userId)),
            default => $query->where('created_by', $userId),
        };

        return [
            'tasks' => $query->paginate(15),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Tasks</h1>
            <p class="text-sm text-slate-500">Create, assign, and track work across the team.</p>
        </div>

        <a href="{{ route('tasks.create') }}" wire:navigate class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Create Task
        </a>
    </div>

    <div class="flex gap-2 border-b border-slate-200">
        @foreach ($this->tabs() as $key => $label)
            <button
                type="button"
                wire:click="$set('tab', '{{ $key }}')"
                class="border-b-2 px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned To</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Deadline</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tasks as $task)
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('tasks.show', $task) }}'">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $task->title }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $task->currentAssignment?->assignedTo?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $task->priority->label() }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $task->status->label() }}</span>
                            @if ($task->isOverdue())
                                <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Overdue</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $task->deadline?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No tasks here yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tasks->links() }}
</div>
