<?php

use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Starred')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    public function toggleStar(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        auth()->user()->starredTasks()->toggle($task->id);
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'tasks' => auth()->user()->starredTasks()
                ->with(['category', 'project', 'currentAssignment.assignedTo', 'creator'])
                ->orderByPivot('created_at', 'desc')
                ->paginate(20),
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900">Starred</h1>
        <p class="hidden text-sm text-zinc-500 sm:block">Tasks you've starred for quick access.</p>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <div class="divide-y divide-zinc-100">
            @forelse ($tasks as $task)
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-zinc-50">
                    <button type="button" wire:click="toggleStar({{ $task->id }})" class="shrink-0 text-amber-400 hover:text-amber-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                            <path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" />
                        </svg>
                    </button>

                    <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="shrink-0 rounded-full bg-violet-100 px-2 py-0.5 font-mono text-xs font-semibold text-violet-700">{{ $task->task_key }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-900">{{ $task->title }}</span>
                        @if ($task->project)
                            <span class="hidden shrink-0 rounded bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 sm:inline-block">{{ $task->project->name }}</span>
                        @endif
                    </a>

                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                    <span class="hidden shrink-0 text-xs text-zinc-400 sm:inline">{{ $task->deadline?->format('d M Y') ?? '—' }}</span>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-zinc-400">No starred tasks yet. Star a task from its page or the board to pin it here.</p>
            @endforelse
        </div>
    </div>

    {{ $tasks->links() }}
</div>
