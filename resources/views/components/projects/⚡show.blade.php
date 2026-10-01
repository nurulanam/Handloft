<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectCoordinatorAssigned;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project')] class extends Component
{
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
                $newCoordinator->notify(new ProjectCoordinatorAssigned($this->project, auth()->user()));
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
        return [
            'canEdit' => Gate::allows('update', $this->project),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900">{{ $project->name }}</h1>
                <p class="mt-1 text-sm text-zinc-500">Created by {{ $project->creator->name }} on {{ $project->created_at->format('d M Y') }}</p>

                <div class="mt-2 flex items-center gap-2 text-sm">
                    <span class="text-zinc-500">Coordinator:</span>

                    <div class="relative" x-data="dropdownMenu()">
                        @if ($project->coordinator)
                            <button type="button" @click="open = ! open" @disabled(! $canEdit) class="flex items-center gap-1.5 {{ $canEdit ? 'hover:opacity-75' : '' }}">
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[9px] font-semibold text-white">{{ \App\Support\Avatar::initials($project->coordinator->name) }}</span>
                                <span class="text-zinc-900">{{ $project->coordinator->name }}</span>
                            </button>
                        @else
                            <button type="button" @click="open = ! open" @disabled(! $canEdit) class="text-zinc-400 {{ $canEdit ? 'hover:text-brand' : '' }}">
                                {{ $canEdit ? 'Assign coordinator' : 'Unassigned' }}
                            </button>
                        @endif

                        @if ($canEdit)
                            <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute left-0 z-20 mt-1 max-h-60 w-48 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                <button type="button" wire:click="saveCoordinator(null)" @click="open = false" class="block w-full px-3 py-2 text-left text-sm text-zinc-400 hover:bg-zinc-50">None</button>
                                @foreach ($users as $option)
                                    <button type="button" wire:click="saveCoordinator({{ $option->id }})" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[9px] font-semibold text-white">{{ \App\Support\Avatar::initials($option->name) }}</span>
                                        {{ $option->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="relative shrink-0" x-data="dropdownMenu()">
                <button
                    type="button"
                    @click="open = ! open"
                    @disabled(! $canEdit)
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium {{ $project->status->pillClasses() }}"
                >
                    {{ $project->status->label() }}
                    @if ($canEdit)
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 opacity-60">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    @endif
                </button>

                @if ($canEdit)
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-1 w-40 rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                        @foreach (ProjectStatus::cases() as $option)
                            <button type="button" wire:click="saveStatus('{{ $option->value }}')" @click="open = false" class="block w-full px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                {{ $option->label() }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @if ($project->description)
            <div class="ql-editor mt-4 p-0! text-sm! text-zinc-600 [&_a]:text-brand [&_a]:underline [&_blockquote]:text-zinc-500 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1">{!! $project->description !!}</div>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-zinc-500">
            @if ($project->start_date)
                <span>Start: {{ $project->start_date->format('d M Y') }}</span>
            @endif
            @if ($project->deadline)
                <span>Deadline: {{ $project->deadline->format('d M Y') }}</span>
            @endif
            <span>{{ $project->completed_count }} / {{ $project->task_count }} tasks complete</span>
        </div>

        <div class="mt-2 h-1.5 w-full max-w-md rounded-full bg-zinc-100">
            <div class="h-1.5 rounded-full bg-brand" style="width: {{ $project->progress_percent }}%"></div>
        </div>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-5">
        <livewire:tasks.index :project-id="$project->id" :key="'project-tasks-'.$project->id" />
    </div>
</div>
