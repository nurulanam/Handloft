<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectCoordinatorAssigned;
use App\Support\DeferredNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project')] class extends Component
{
    use \App\Livewire\Concerns\SearchesPickerOptions;

    public Project $project;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
    }

    public function saveCoordinator(?int $userId): void
    {
        Gate::authorize('update', $this->project);

        $data = Validator::make(['coordinator_value' => $userId], [
            'coordinator_value' => ['nullable', 'exists:users,id'],
        ])->validate();

        $newCoordinatorId = $data['coordinator_value'] ?: null;

        if ($newCoordinatorId && $newCoordinatorId !== $this->project->coordinator_id) {
            $newCoordinator = User::find($newCoordinatorId);

            if ($newCoordinator && $newCoordinator->isNot(auth()->user())) {
                DeferredNotification::send($newCoordinator, new ProjectCoordinatorAssigned($this->project, auth()->user()));
            }
        }

        $this->project->update(['coordinator_id' => $newCoordinatorId]);

        $this->project->refresh();
    }

    public function saveStatus(string $value): void
    {
        Gate::authorize('update', $this->project);

        $data = Validator::make(['status_value' => $value], [
            'status_value' => ['required', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
        ])->validate();

        $this->project->update(['status' => $data['status_value']]);

        $this->project->refresh();
    }

    public function with(): array
    {
        $tasks = $this->project->topLevelTasks()->get(['id', 'status', 'deadline']);
        $total = $tasks->count();
        $counts = collect(\App\Enums\TaskStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $tasks->where('status', $status)->count()]);

        // Timeline: how far between start and deadline today is, and the days left.
        $start = $this->project->start_date;
        $deadline = $this->project->deadline;
        $timeline = null;
        if ($start && $deadline && $deadline->gte($start)) {
            $span = max(1, $start->diffInDays($deadline));
            $timeline = [
                'elapsed' => (int) min(100, max(0, round($start->diffInDays(today(), false) / $span * 100))),
                'daysLeft' => (int) today()->diffInDays($deadline, false),
            ];
        }

        return [
            'canEdit' => Gate::allows('update', $this->project),
            'total' => $total,
            'counts' => $counts,
            'overdue' => $tasks->filter(fn ($task) => $task->deadline && $task->deadline->isPast() && $task->status !== \App\Enums\TaskStatus::Done)->count(),
            'inProgress' => $counts['in_progress'] + $counts['qa_testing'] + $counts['rejected'] + $counts['ready_to_deploy'],
            'timeline' => $timeline,
        ];
    }
};
?>

@php
    $segments = [
        'done' => ['Done', 'bg-brand'],
        'ready_to_deploy' => ['Ready to Deploy', 'bg-lime-500'],
        'qa_testing' => ['QA Testing', 'bg-amber-500'],
        'rejected' => ['Rejected', 'bg-red-500'],
        'in_progress' => ['In Progress', 'bg-sky-500'],
        'todo' => ['To Do', 'bg-zinc-300'],
    ];
    $percent = $project->progress_percent;
    $ring = 2 * M_PI * 26;
@endphp

<div class="space-y-5 sm:space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <a href="{{ route('projects.index') }}" wire:navigate class="group mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 transition-colors hover:text-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform group-hover:-translate-x-0.5"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" /></svg>
                Projects
            </a>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-[1.75rem] sm:leading-9">{{ $project->name }}</h1>
            <p class="mt-1.5 flex items-center gap-2 text-sm text-zinc-500">
                <x-user-avatar :user="$project->creator" class="size-5 shrink-0 rounded-full text-[9px]" />
                <span>Created by <span class="font-medium text-zinc-700">{{ $project->creator->name }}</span> on {{ $project->created_at->format('d M Y') }}</span>
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($canEdit)
                <a href="{{ route('projects.edit', $project) }}" wire:navigate class="btn-secondary py-2" title="Edit project">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" /></svg>
                    <span class="hidden sm:inline">Edit</span>
                </a>
            @endif

            <div class="relative" x-data="dropdownMenu()" @click.outside="open = false">
                <button type="button" @click="open = ! open" :aria-expanded="open" @disabled(! $canEdit) class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold shadow-xs ring-1 ring-inset ring-zinc-900/5 transition {{ $project->status->pillClasses() }} {{ $canEdit ? 'hover:brightness-95' : 'cursor-default' }}">
                    <span class="size-2 rounded-full bg-current opacity-70"></span>
                    {{ $project->status->label() }}
                    @if ($canEdit)
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 opacity-60 transition-transform" :class="open && 'rotate-180'"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                    @endif
                </button>
                @if ($canEdit)
                    <div x-show="open" x-cloak x-transition class="menu-panel right-0 w-48">
                        @foreach (ProjectStatus::cases() as $option)
                            <button type="button" wire:click="saveStatus('{{ $option->value }}')" @click="open = false" class="menu-item {{ $project->status === $option ? 'bg-brand/5 font-medium text-brand!' : '' }}">
                                <span class="size-2 rounded-full {{ $option->pillClasses() }}"></span>
                                {{ $option->label() }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Overview: progress, where the work is, who coordinates it and the timeline. --}}
    <section class="relative grid gap-5 rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-6 lg:grid-cols-[auto_1fr_auto] lg:items-center lg:gap-8 has-[[aria-expanded=true]]:z-20">
        <div class="flex items-center gap-4">
            <div class="relative size-[68px] shrink-0">
                <svg viewBox="0 0 60 60" class="size-full -rotate-90">
                    <circle cx="30" cy="30" r="26" fill="none" stroke-width="6" class="stroke-zinc-200/80" />
                    <circle cx="30" cy="30" r="26" fill="none" stroke-width="6" stroke-linecap="round" class="stroke-brand transition-[stroke-dashoffset] duration-700" stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring * (1 - $percent / 100) }}" />
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-sm font-bold tabular-nums text-zinc-900">{{ $percent }}%</span>
            </div>
            <div>
                <p class="text-sm font-semibold text-zinc-900">{{ $project->completed_count }} of {{ $total }} done</p>
                <p class="text-xs text-zinc-500">Top-level tasks</p>
            </div>
        </div>

        <div class="min-w-0 space-y-3">
            {{-- Where the tasks are, as one stacked bar. --}}
            <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-zinc-200/70" role="img" aria-label="Tasks by status">
                @foreach ($segments as $key => [$segmentLabel, $segmentColor])
                    @if ($total > 0 && $counts[$key] > 0)
                        <span class="{{ $segmentColor }} h-full border-r-2 border-surface last:border-r-0" style="width: {{ $counts[$key] / $total * 100 }}%" title="{{ $segmentLabel }}: {{ $counts[$key] }}"></span>
                    @endif
                @endforeach
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                @foreach ($segments as $key => [$segmentLabel, $segmentColor])
                    @if ($counts[$key] > 0)
                        <span class="inline-flex items-center gap-1.5 text-xs text-zinc-600"><span class="size-2 rounded-full {{ $segmentColor }}"></span>{{ $segmentLabel }} <span class="font-semibold tabular-nums text-zinc-900">{{ $counts[$key] }}</span></span>
                    @endif
                @endforeach
                @if ($total === 0)
                    <span class="text-xs text-zinc-500">No tasks yet.</span>
                @endif
            </div>
            <div class="grid grid-cols-3 gap-2 pt-1 sm:max-w-md">
                @foreach ([['In motion', $inProgress, 'text-sky-700'], ['Overdue', $overdue, $overdue ? 'text-red-600' : 'text-zinc-900'], ['To do', $counts['todo'], 'text-zinc-900']] as [$statLabel, $statValue, $statTone])
                    <div class="rounded-xl bg-zinc-50/80 px-3 py-2 ring-1 ring-inset ring-zinc-200/60">
                        <p class="text-[11px] font-medium text-zinc-500">{{ $statLabel }}</p>
                        <p class="text-lg font-semibold tabular-nums {{ $statTone }}">{{ $statValue }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-4 border-t border-zinc-100 pt-4 lg:w-64 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0">
            <div>
                <p class="mb-1 text-xs font-medium text-zinc-500">Coordinator</p>
                <div class="-ml-2">
                    <x-form.picker variant="inline" call="saveCoordinator" :value="$project->coordinator_id" source="people" :label="$project->coordinator?->name" avatar clearable clear-label="No coordinator" :placeholder="$canEdit ? 'Assign coordinator' : 'Unassigned'" :disabled="! $canEdit" align="left" />
                </div>
            </div>
            <div>
                <p class="mb-1.5 text-xs font-medium text-zinc-500">Timeline</p>
                @if ($timeline)
                    <div class="relative h-1.5 rounded-full bg-zinc-200/70">
                        <div class="h-full rounded-full {{ $timeline['daysLeft'] < 0 ? 'bg-red-500' : 'bg-brand' }}" style="width: {{ $timeline['elapsed'] }}%"></div>
                        <span class="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-surface {{ $timeline['daysLeft'] < 0 ? 'bg-red-500' : 'bg-brand' }} shadow" style="left: {{ $timeline['elapsed'] }}%" title="Today"></span>
                    </div>
                    <div class="mt-1.5 flex justify-between text-[11px] text-zinc-500">
                        <span>{{ $project->start_date->format('d M') }}</span>
                        <span class="font-semibold {{ $timeline['daysLeft'] < 0 ? 'text-red-600' : ($timeline['daysLeft'] <= 7 ? 'text-amber-700' : 'text-zinc-700') }}">
                            {{ $timeline['daysLeft'] < 0 ? abs($timeline['daysLeft']).' '.\Illuminate\Support\Str::plural('day', abs($timeline['daysLeft'])).' over' : ($timeline['daysLeft'] === 0 ? 'Due today' : $timeline['daysLeft'].' '.\Illuminate\Support\Str::plural('day', $timeline['daysLeft']).' left') }}
                        </span>
                        <span>{{ $project->deadline->format('d M') }}</span>
                    </div>
                @else
                    <p class="text-sm text-zinc-700">
                        @if ($project->deadline)
                            Due <span class="font-medium">{{ $project->deadline->format('d M Y') }}</span>
                        @elseif ($project->start_date)
                            Started {{ $project->start_date->format('d M Y') }}
                        @else
                            <span class="text-zinc-400">No dates set</span>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </section>

    @if ($project->description)
        <section class="rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-6" x-data="{ more: false, long: false }" x-init="$nextTick(() => long = $refs.body.scrollHeight > $refs.body.clientHeight + 2)">
            <h2 class="mb-2 text-sm font-semibold text-zinc-900">About this project</h2>
            <div x-ref="body" style="--rich-clamp: 9rem" class="ql-editor rich-text p-0! text-sm! leading-relaxed text-zinc-700 [&_a]:text-brand [&_a]:underline [&_blockquote]:text-zinc-500 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1" :class="more ? '' : (long ? 'rich-clamp is-overflowing' : 'rich-clamp')">{!! $project->description !!}</div>
            <button type="button" x-show="long" x-cloak @click="more = ! more" class="mt-2 text-xs font-semibold text-brand hover:underline" x-text="more ? 'Show less' : 'Show more'"></button>
        </section>
    @endif

    <section class="rounded-2xl border border-zinc-200 bg-surface p-4 sm:p-6">
        <livewire:tasks.index :project-id="$project->id" :key="'project-tasks-'.$project->id" />
    </section>
</div>
