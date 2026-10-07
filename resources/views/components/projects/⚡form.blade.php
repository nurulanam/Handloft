<?php

use App\Livewire\Concerns\SearchesPickerOptions;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectCoordinatorAssigned;
use App\Support\DeferredNotification;
use App\Support\Html;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Create a project, or edit one (/projects/{project}/edit): the same form for both.
 */
new #[Layout('layouts.app')] #[Title('Project')] class extends Component
{
    use SearchesPickerOptions;

    public ?Project $project = null;

    public string $name = '';

    public string $description = '';

    public string $coordinator_id = '';

    public string $start_date = '';

    public string $deadline = '';

    public function mount(?Project $project = null): void
    {
        Gate::authorize($project?->exists ? 'update' : 'create', $project?->exists ? $project : Project::class);

        if ($project?->exists) {
            $this->project = $project;
            $this->name = $project->name;
            $this->description = (string) $project->description;
            $this->coordinator_id = (string) ($project->coordinator_id ?? '');
            $this->start_date = $project->start_date?->toDateString() ?? '';
            $this->deadline = $project->deadline?->toDateString() ?? '';
        }
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
    }

    public function save(): void
    {
        if ($this->project) {
            Gate::authorize('update', $this->project);
        } else {
            Gate::authorize('create', Project::class);
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'coordinator_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'name.required' => 'Give the project a name.',
            'deadline.after_or_equal' => 'The deadline can\'t be before the start date.',
        ]);

        $attributes = [
            'name' => $data['name'],
            'description' => Html::sanitize($data['description']),
            'coordinator_id' => $data['coordinator_id'] ?: null,
            'start_date' => $data['start_date'] ?: null,
            'deadline' => $data['deadline'] ?: null,
        ];

        $previousCoordinatorId = $this->project?->coordinator_id;

        if ($this->project) {
            $this->project->update($attributes);
            $project = $this->project;
        } else {
            $project = Project::create($attributes + ['created_by' => auth()->id()]);
        }

        // Tell a newly chosen coordinator (not on every save, and never yourself).
        if ($project->coordinator_id && (int) $project->coordinator_id !== (int) $previousCoordinatorId && $project->coordinator->isNot(auth()->user())) {
            DeferredNotification::send($project->coordinator, new ProjectCoordinatorAssigned($project, auth()->user()));
        }

        if ($this->project) {
            $this->dispatch('notify', message: 'Project updated.', type: 'success');
        }

        $this->redirect(route('projects.show', $project), navigate: true);
    }

    public function with(): array
    {
        $days = $this->start_date && $this->deadline && $this->deadline >= $this->start_date
            ? Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->deadline)) + 1
            : null;

        return [
            'coordinatorName' => $this->coordinator_id ? User::query()->whereKey($this->coordinator_id)->value('name') : null,
            'days' => $days,
        ];
    }
};
?>

@php
    $editing = (bool) $project;
    $cancel = $editing ? route('projects.show', $project) : route('projects.index');
    $action = $editing ? 'Save changes' : 'Create project';
@endphp

<div>
    <x-form.header
        :back="$cancel"
        :back-label="$editing ? $project->name : 'Projects'"
        :title="$editing ? 'Edit project' : 'Create a project'"
        :subtitle="$editing ? 'Update the brief, the coordinator or the timeline.' : 'Group related tasks under one goal, with someone to coordinate it and a timeline to aim for.'"
    >
        <x-slot:actions>
            <a href="{{ $cancel }}" wire:navigate class="btn-secondary">Cancel</a>
            <x-form.submit form="project-form">{{ $action }}</x-form.submit>
        </x-slot:actions>
    </x-form.header>

    <form id="project-form" wire:submit="save">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-6">
            <x-form.card class="lg:col-span-2" title="About the project" description="A name people will recognise, and what it's for." icon='<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>'>
                <x-form.field label="Name" for="project-name" error="name" required>
                    <input id="project-name" wire:model="name" type="text" placeholder="e.g. Website relaunch" autocomplete="off" @class(['field-input text-base font-medium', 'field-input-error' => $errors->has('name')])>
                </x-form.field>

                <x-form.field label="Description" error="description" optional>
                    <div wire:ignore x-data="quillEditor(@js($description))" class="rich-editor" style="--editor-height: 14rem">
                        <div x-ref="editor" data-placeholder="Goals, scope, links, who it's for…"></div>
                        <input type="hidden" x-ref="input" wire:model="description">
                    </div>
                </x-form.field>
            </x-form.card>

            <div class="space-y-4 lg:sticky lg:top-20 lg:space-y-6 lg:self-start">
                <x-form.card title="Coordinator" description="Keeps the project on track." icon='<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'>
                    <x-form.field error="coordinator_id" hint="They're notified when you choose them.">
                        <x-form.picker model="coordinator_id" :value="$coordinator_id" source="people" :label="$coordinatorName" avatar clearable clear-label="No coordinator" placeholder="Choose a coordinator" />
                    </x-form.field>
                </x-form.card>

                <x-form.card title="Timeline" description="When it starts, and when it should be done." icon='<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.3V14"/><circle cx="16" cy="16" r="6"/>'>
                    <x-form.field label="Start date" error="start_date" optional>
                        <x-form.date model="start_date" :value="$start_date" placeholder="Not set" />
                    </x-form.field>

                    <x-form.field label="Deadline" error="deadline" optional>
                        <x-form.date model="deadline" :value="$deadline" placeholder="No deadline" :error="$errors->has('deadline')" />
                    </x-form.field>

                    @if ($days)
                        <p class="flex items-center gap-2 rounded-xl bg-brand/5 px-3 py-2 text-xs text-zinc-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-brand"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
                            <span>Runs <span class="font-semibold text-zinc-900">{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</span>{{ $days >= 14 ? ' (about '.round($days / 7).' weeks)' : '' }}.</span>
                        </p>
                    @endif
                </x-form.card>
            </div>
        </div>

        <x-form.actions :cancel="$cancel">{{ $action }}</x-form.actions>
    </form>
</div>
