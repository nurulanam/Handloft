<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('For You')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    public function toggleStar(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        auth()->user()->starredTasks()->toggle($task->id);
    }

    public function with(): array
    {
        $userId = auth()->id();

        // Everything currently waiting on this specific person, at whatever
        // stage it's in — the assignee's active work, a reviewer's QA queue,
        // and a reporter's final sign-offs — excluding anything already Done.
        $baseQuery = Task::query()
            ->where('status', '!=', TaskStatus::Done)
            ->where(function ($q) use ($userId) {
                $q->whereHas('currentAssignment', fn ($q2) => $q2->where('assigned_to', $userId))
                    ->orWhere(fn ($q2) => $q2->where('qa_id', $userId)->where('status', TaskStatus::QaTesting))
                    ->orWhere(fn ($q2) => $q2->where('created_by', $userId)->where('status', TaskStatus::ReadyToDeploy));
            });

        // This is a personal queue, so it should naturally stay small — but
        // cap it defensively rather than ever loading an unbounded result set.
        $queueLimit = 150;
        $queueTotal = (clone $baseQuery)->count();

        $tasks = $baseQuery
            ->with(['category', 'project', 'currentAssignment.assignedTo', 'creator', 'starredBy' => fn ($q) => $q->where('users.id', $userId)])
            ->orderByRaw('deadline IS NULL, deadline asc')
            ->limit($queueLimit)
            ->get();

        $sections = collect([TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::QaTesting, TaskStatus::Rejected, TaskStatus::ReadyToDeploy])
            ->map(fn (TaskStatus $status) => [
                'status' => $status,
                'tasks' => $tasks->where('status', $status)->values(),
            ]);

        return [
            'sections' => $sections,
            'queueTruncated' => $queueTotal > $queueLimit,
            'queueTotal' => $queueTotal,
            'queueLimit' => $queueLimit,
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900">For You</h1>
        <p class="text-sm text-zinc-500">Everything waiting on you right now, across every task you're connected to.</p>
    </div>

    @if ($queueTruncated)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
            You have {{ $queueTotal }} active tasks — showing the {{ $queueLimit }} soonest.
            <a href="{{ route('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list']) }}" wire:navigate class="font-medium underline hover:text-amber-900">View the full list</a>.
        </div>
    @endif

    <div class="space-y-5">
        @foreach ($sections as $section)
            <div class="rounded-lg border border-zinc-200 bg-white">
                <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-900">
                        {{ $section['status']->label() }}
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $section['status']->pillClasses() }}">{{ $section['tasks']->count() }}</span>
                    </span>
                </div>

                <div class="divide-y divide-zinc-100">
                    @forelse ($section['tasks'] as $task)
                        <div class="flex items-center gap-3 px-4 py-3 hover:bg-zinc-50">
                            <button type="button" wire:click="toggleStar({{ $task->id }})" class="shrink-0 {{ $task->starredBy->isNotEmpty() ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}">
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

                            <span class="shrink-0 text-xs text-zinc-400">{{ $task->deadline?->format('d M Y') ?? '—' }}</span>
                            @if ($task->isOverdue())
                                <span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Overdue</span>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-zinc-400">Nothing here right now.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
