<?php

use App\Livewire\Concerns\TogglesStars;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('For You')] class extends Component
{
    use TogglesStars;

    private const PAGE_SIZE = 5;

    /** @var array<string, int> number of cards shown per status, keyed by TaskStatus::value */
    public array $visibleCounts = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    public function toggleStar(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        Gate::authorize('view', $task);

        $this->toggleStarFor($task);
    }

    public function loadMore(string $status): void
    {
        $this->visibleCounts[$status] = ($this->visibleCounts[$status] ?? self::PAGE_SIZE) + self::PAGE_SIZE;
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
            ->map(function (TaskStatus $status) use ($tasks) {
                $statusTasks = $tasks->where('status', $status)->values();
                $visibleCount = $this->visibleCounts[$status->value] ?? self::PAGE_SIZE;

                return [
                    'status' => $status,
                    'tasks' => $statusTasks,
                    'visibleTasks' => $statusTasks->take($visibleCount),
                    'hasMore' => $statusTasks->count() > $visibleCount,
                ];
            });

        $open = $tasks;

        return [
            'sections' => $sections,
            'summary' => [
                'total' => $queueTotal,
                'overdue' => $open->filter->isOverdue()->count(),
                'dueThisWeek' => $open->filter(fn (Task $task) => $task->deadline && ! $task->isOverdue() && $task->deadline->lte(\App\Support\WorkSchedule::endOfWeek(now())))->count(),
            ],
            'queueTruncated' => $queueTotal > $queueLimit,
            'queueTotal' => $queueTotal,
            'queueLimit' => $queueLimit,
        ];
    }
};
?>

@php
    [$filledSections, $emptySections] = $sections->partition(fn ($section) => $section['tasks']->isNotEmpty());
    $dots = [
        'todo' => 'bg-zinc-400', 'in_progress' => 'bg-sky-500', 'qa_testing' => 'bg-amber-500',
        'rejected' => 'bg-red-500', 'ready_to_deploy' => 'bg-lime-500',
    ];
    // Why a task is waiting on me: I'm doing it, reviewing it, or signing it off.
    $why = function ($task) {
        return match (true) {
            $task->qa_id === auth()->id() && $task->status === \App\Enums\TaskStatus::QaTesting => ['Review', 'bg-amber-50 text-amber-700'],
            $task->created_by === auth()->id() && $task->status === \App\Enums\TaskStatus::ReadyToDeploy => ['Sign-off', 'bg-lime-50 text-lime-700'],
            default => ['Assigned', 'bg-brand/10 text-brand'],
        };
    };
@endphp

<div class="space-y-5 sm:space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">For You</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Everything waiting on you right now, soonest due first.</p>
        </div>

        @if ($summary['total'] > 0)
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1 text-xs font-semibold text-zinc-700 ring-1 ring-inset ring-zinc-200">
                    <span class="size-1.5 rounded-full bg-brand"></span>{{ $summary['total'] }} waiting on you
                </span>
                @if ($summary['overdue'] > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-200">
                        <span class="size-1.5 rounded-full bg-red-500"></span>{{ $summary['overdue'] }} overdue
                    </span>
                @endif
                @if ($summary['dueThisWeek'] > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                        <span class="size-1.5 rounded-full bg-amber-500"></span>{{ $summary['dueThisWeek'] }} due this week
                    </span>
                @endif
            </div>
        @endif
    </div>

    @if ($queueTruncated)
        <div class="rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800 ring-1 ring-inset ring-amber-200">
            You have {{ $queueTotal }} active tasks. Showing the {{ $queueLimit }} due soonest.
            <a href="{{ route('tasks.index', ['tab' => 'assigned-to-me', 'view' => 'list']) }}" wire:navigate class="font-semibold underline hover:text-amber-900">See them all</a>
        </div>
    @endif

    @if ($filledSections->isEmpty())
        <div class="flex flex-col items-center rounded-2xl border border-zinc-200 bg-surface px-6 py-14 text-center">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
            </span>
            <p class="mt-4 text-sm font-semibold text-zinc-900">You're all caught up</p>
            <p class="mt-1 text-sm text-zinc-500">Nothing is waiting on you right now.</p>
        </div>
    @else
        {{-- One column per stage that has work in it; empty stages are summed up in one line below. --}}
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 lg:gap-6 xl:grid-cols-3">
            @foreach ($filledSections as $section)
                <section class="rounded-2xl border border-zinc-200 bg-surface">
                    <header class="flex items-center justify-between gap-3 px-4 pb-2 pt-4">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                            <span class="size-2.5 rounded-full {{ $dots[$section['status']->value] ?? 'bg-zinc-400' }}"></span>
                            {{ $section['status']->label() }}
                        </h2>
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-600">{{ $section['tasks']->count() }}</span>
                    </header>

                    <ul class="space-y-2 px-2.5 pb-2.5">
                        @foreach ($section['visibleTasks'] as $task)
                            @php [$whyLabel, $whyTone] = $why($task); $starred = $task->starredBy->isNotEmpty(); @endphp
                            <li wire:key="for-you-{{ $task->id }}" class="group relative rounded-xl border border-zinc-200/80 bg-surface p-3 transition hover:-translate-y-px hover:border-brand/30 hover:shadow-md hover:shadow-zinc-900/5">
                                <a href="{{ route('tasks.show', $task) }}" wire:navigate class="absolute inset-0 rounded-xl" aria-label="{{ $task->task_key }} {{ $task->title }}"></a>

                                <div class="flex items-center gap-1.5">
                                    <span class="rounded-md bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                    <span class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $whyTone }}">{{ $whyLabel }}</span>
                                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] font-medium text-zinc-500" title="{{ $task->priority->label() }} priority">
                                        <span class="size-2 rounded-full bg-current {{ $task->priority->colorClass() }}"></span>{{ $task->priority->label() }}
                                    </span>
                                    <button type="button" wire:click="toggleStar({{ $task->id }})" class="relative -mr-1 flex size-7 items-center justify-center rounded-lg transition hover:bg-amber-50 active:scale-90 {{ $starred ? 'text-amber-400' : 'text-zinc-300 hover:text-amber-400' }}" title="{{ $starred ? 'Unstar' : 'Star' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" /></svg>
                                    </button>
                                </div>

                                <p class="mt-1.5 line-clamp-2 text-sm font-medium leading-snug text-zinc-900 group-hover:text-brand">{{ $task->title }}</p>

                                <div class="mt-2.5 flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-1.5 text-xs text-zinc-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 text-zinc-400"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                                        <span class="truncate">{{ $task->project?->name ?? 'No project' }}</span>
                                    </span>
                                    <x-due-date :date="$task->deadline" class="shrink-0" />
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if ($section['hasMore'])
                        <div class="border-t border-zinc-100 p-2">
                            <button type="button" wire:click="loadMore('{{ $section['status']->value }}')" class="w-full rounded-lg py-2 text-sm font-semibold text-brand transition-colors hover:bg-brand/5">
                                Show {{ min(5, $section['tasks']->count() - $section['visibleTasks']->count()) }} more
                            </button>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($emptySections->isNotEmpty())
            <p class="flex items-center gap-2 text-sm text-zinc-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                Nothing in {{ $emptySections->map(fn ($section) => $section['status']->label())->join(', ', ' or ') }} right now.
            </p>
        @endif
    @endif
</div>
