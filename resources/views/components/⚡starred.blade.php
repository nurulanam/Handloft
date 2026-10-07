<?php

use App\Livewire\Concerns\TogglesStars;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Starred')] class extends Component
{
    use WithPagination, TogglesStars;

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    public function toggleStar(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        $this->toggleStarFor($task);
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

<div class="space-y-5 sm:space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Starred</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Tasks you've starred for quick access, newest first.</p>
        </div>
        @if ($tasks->total() > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" /></svg>
                {{ $tasks->total() }} starred
            </span>
        @endif
    </div>

    @if ($tasks->isEmpty())
        <div class="flex flex-col items-center rounded-2xl border border-zinc-200 bg-surface px-6 py-14 text-center">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-400 ring-1 ring-inset ring-amber-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-6"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" /></svg>
            </span>
            <p class="mt-4 text-sm font-semibold text-zinc-900">No starred tasks yet</p>
            <p class="mt-1 max-w-sm text-sm text-zinc-500">Star a task from its page or the board to keep it one tap away.</p>
            <a href="{{ route('tasks.index') }}" wire:navigate class="btn-secondary mt-5">Browse tasks</a>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-surface">
            <table class="w-full text-sm">
                <thead class="hidden md:table-header-group">
                    <tr class="border-b border-zinc-100 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                        <th class="w-12 py-3 pl-4"><span class="sr-only">Star</span></th>
                        <th class="px-3 py-3 font-semibold">Task</th>
                        <th class="hidden px-3 py-3 font-semibold lg:table-cell">Project</th>
                        <th class="px-3 py-3 font-semibold">Assignee</th>
                        <th class="px-3 py-3 font-semibold">Priority</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="py-3 pl-3 pr-5 text-right font-semibold">Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($tasks as $task)
                        @php $assignee = $task->currentAssignment?->assignedTo; @endphp
                        <tr wire:key="starred-{{ $task->id }}" class="group flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3.5 transition-colors hover:bg-zinc-50 md:table-row md:p-0">
                            <td class="md:py-3 md:pl-4">
                                <button type="button" wire:click="toggleStar({{ $task->id }})" class="flex size-8 items-center justify-center rounded-lg text-amber-400 transition hover:bg-amber-50 hover:text-amber-500 active:scale-90" title="Unstar">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-[18px]"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" /></svg>
                                </button>
                            </td>
                            <td class="min-w-0 flex-1 md:px-3 md:py-3">
                                <a href="{{ route('tasks.show', $task) }}" wire:navigate class="block min-w-0">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="shrink-0 rounded-md bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                        <span class="truncate font-medium text-zinc-900 group-hover:text-brand">{{ $task->title }}</span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-zinc-500 lg:hidden">{{ $task->project?->name ?? 'No project' }}</span>
                                </a>
                            </td>
                            <td class="hidden max-w-48 px-3 py-3 lg:table-cell">
                                @if ($task->project)
                                    <span class="inline-flex max-w-full items-center gap-1.5 truncate text-zinc-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 text-zinc-400"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg><span class="truncate">{{ $task->project->name }}</span></span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="order-last basis-full pl-11 md:order-none md:basis-auto md:px-3 md:py-3">
                                <div class="flex items-center justify-between gap-3 md:justify-start">
                                    @if ($assignee)
                                        <span class="flex min-w-0 items-center gap-2">
                                            <x-user-avatar :user="$assignee" class="size-6 rounded-full text-[10px]" />
                                            <span class="truncate text-zinc-700">{{ $assignee->name }}</span>
                                        </span>
                                    @else
                                        <span class="text-zinc-400">Unassigned</span>
                                    @endif
                                    <span class="flex items-center gap-2 md:hidden">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                                        <x-due-date :date="$task->deadline" :done="$task->status === \App\Enums\TaskStatus::Done" />
                                    </span>
                                </div>
                            </td>
                            <td class="hidden px-3 py-3 md:table-cell">
                                <span class="inline-flex items-center gap-1.5 text-zinc-700"><span class="size-2 rounded-full bg-current {{ $task->priority->colorClass() }}"></span>{{ $task->priority->label() }}</span>
                            </td>
                            <td class="hidden px-3 py-3 md:table-cell">
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                            </td>
                            <td class="hidden py-3 pl-3 pr-5 text-right md:table-cell">
                                <x-due-date :date="$task->deadline" :done="$task->status === \App\Enums\TaskStatus::Done" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $tasks->links() }}
    @endif
</div>
