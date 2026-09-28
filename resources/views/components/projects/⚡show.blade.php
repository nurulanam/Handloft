<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Project')] class extends Component
{
    public Project $project;

    public bool $editingStatus = false;

    public string $status_value = '';

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
    }

    public function startEditStatus(): void
    {
        Gate::authorize('update', $this->project);

        $this->status_value = $this->project->status->value;
        $this->editingStatus = true;
    }

    public function saveStatus(): void
    {
        Gate::authorize('update', $this->project);

        $data = $this->validate([
            'status_value' => ['required', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
        ]);

        $this->project->update(['status' => $data['status_value']]);

        $this->editingStatus = false;
        $this->project->refresh();
    }

    public function with(): array
    {
        return [
            'canEdit' => Gate::allows('update', $this->project),
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
            </div>

            @if ($editingStatus)
                <div class="flex items-center gap-2">
                    <select wire:model="status_value" class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @foreach (ProjectStatus::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="saveStatus" class="text-xs font-medium text-brand hover:underline">Save</button>
                    <button type="button" wire:click="$set('editingStatus', false)" class="text-xs text-zinc-500 hover:text-zinc-700">Cancel</button>
                </div>
            @else
                <button
                    type="button"
                    wire:click="startEditStatus"
                    @disabled(! $canEdit)
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium {{ $project->status->pillClasses() }}"
                >
                    {{ $project->status->label() }}
                    @if ($canEdit)
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 opacity-60">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    @endif
                </button>
            @endif
        </div>

        @if ($project->description)
            <div class="ql-editor mt-4 p-0! text-sm! text-zinc-600 [&_a]:text-brand [&_a]:underline [&_blockquote]:text-zinc-500 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1">
                {!! $project->description !!}
            </div>
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
