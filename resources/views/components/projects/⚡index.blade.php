<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Projects')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);
    }

    public function with(): array
    {
        return [
            // Both counts are computed in this one query via withCount rather
            // than per-project queries (Project::taskCount()/completedCount()
            // fall back to those only when not eager-loaded), so this page
            // stays a single query no matter how many projects there are.
            'projects' => Project::query()
                ->with(['creator', 'coordinator'])
                ->withCount([
                    'topLevelTasks as task_count',
                    'topLevelTasks as completed_count' => fn ($q) => $q->where('status', TaskStatus::Done),
                ])
                ->latest()
                ->paginate(12),
            'canCreate' => Gate::allows('create', Project::class),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Projects</h1>
            <p class="text-sm text-zinc-500">Group related tasks together and track overall progress.</p>
        </div>

        @if ($canCreate)
            <a href="{{ route('projects.create') }}" wire:navigate class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">
                New Project
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($projects as $project)
            <a href="{{ route('projects.show', $project) }}" wire:navigate class="block rounded-lg border border-zinc-200 bg-white p-4 hover:border-brand/40">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900">{{ $project->name }}</h2>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $project->status->pillClasses() }}">{{ $project->status->label() }}</span>
                </div>

                @if ($project->description)
                    <p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ str($project->description)->stripTags() }}</p>
                @endif

                <div class="mt-3 flex items-center justify-between text-xs text-zinc-500">
                    <span>{{ $project->completed_count }} / {{ $project->task_count }} tasks complete</span>
                    <span>{{ $project->progress_percent }}%</span>
                </div>

                <div class="mt-1.5 h-1.5 w-full rounded-full bg-zinc-100">
                    <div class="h-1.5 rounded-full bg-brand" style="width: {{ $project->progress_percent }}%"></div>
                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-zinc-400">
                    <span>{{ $project->coordinator?->name ? 'Coordinator: '.$project->coordinator->name : 'By '.$project->creator->name }}</span>
                    @if ($project->deadline)
                        <span>Due {{ $project->deadline->format('d M Y') }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-lg border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                No projects yet.
            </div>
        @endforelse
    </div>

    {{ $projects->links() }}
</div>
