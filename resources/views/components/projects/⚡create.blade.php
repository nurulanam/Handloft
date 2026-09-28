<?php

use App\Models\Project;
use App\Support\Html;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Create Project')] class extends Component
{
    public string $name = '';

    public string $description = '';

    public string $start_date = '';

    public string $deadline = '';

    public function mount(): void
    {
        Gate::authorize('create', Project::class);
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
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project = Project::create([
            'name' => $data['name'],
            'description' => Html::sanitize($data['description']),
            'start_date' => $data['start_date'] ?: null,
            'deadline' => $data['deadline'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('projects.show', $project), navigate: true);
    }
};
?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Create Project</h1>
            <p class="text-sm text-zinc-500">Group related tasks together under one project.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('projects.index') }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</a>
            <button type="submit" form="create-project-form" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled">
                Create Project
            </button>
        </div>
    </div>

    <form id="create-project-form" wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Left: Name + Description --}}
            <div class="space-y-4 lg:col-span-2">
                <div>
                    <input
                        wire:model="name"
                        type="text"
                        placeholder="Project name"
                        class="block w-full border-0 border-b border-zinc-200 bg-transparent px-0 py-2 text-2xl font-semibold text-zinc-900 placeholder:text-zinc-300 focus:border-brand focus:outline-none focus:ring-0"
                    >
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Description</label>
                    <div wire:ignore x-data="quillEditor(@js($description))" class="mt-1">
                        <div x-ref="editor" class="min-h-64 rounded-b-lg border border-zinc-300 bg-white text-sm [&_.ql-toolbar]:rounded-t-lg [&_.ql-toolbar]:border-zinc-300"></div>
                        <input type="hidden" x-ref="input" wire:model="description">
                    </div>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Right: Details --}}
            <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Details</h3>

                    <div class="mt-3 divide-y divide-zinc-100">
                        {{-- Reporter (fixed — the person filling out this form) --}}
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-zinc-500">Created by</span>
                            <span class="flex items-center gap-2">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials(auth()->user()->name) }}</span>
                                <span class="text-sm text-zinc-900">{{ auth()->user()->name }}</span>
                            </span>
                        </div>

                        {{-- Start date --}}
                        <div class="flex items-center justify-between gap-3 py-2.5" x-data="{ editing: false }">
                            <span class="text-sm text-zinc-500">Start date</span>

                            <button type="button" x-show="!editing" @click="editing = true" class="text-sm {{ $start_date ? 'text-zinc-900' : 'text-zinc-400' }} hover:text-brand">
                                {{ $start_date ? \Illuminate\Support\Carbon::parse($start_date)->format('d M Y') : 'Add start date' }}
                            </button>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <input wire:model.live="start_date" type="date" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            </div>
                        </div>

                        {{-- Deadline --}}
                        <div class="flex items-center justify-between gap-3 py-2.5" x-data="{ editing: false }">
                            <span class="text-sm text-zinc-500">Deadline</span>

                            <button type="button" x-show="!editing" @click="editing = true" class="text-sm {{ $deadline ? 'text-zinc-900' : 'text-zinc-400' }} hover:text-brand">
                                {{ $deadline ? \Illuminate\Support\Carbon::parse($deadline)->format('d M Y') : 'Add deadline' }}
                            </button>

                            <div x-show="editing" x-cloak class="max-w-[65%] flex-1" @click.outside="editing = false">
                                <input wire:model.live="deadline" type="date" class="block w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                            </div>
                        </div>
                        @error('deadline') <p class="pb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
