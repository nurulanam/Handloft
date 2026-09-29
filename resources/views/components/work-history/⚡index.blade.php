<?php

use App\Models\User;
use App\Models\WorkHistory;
use App\Services\TaskWorkflowService;
use App\Support\Avatar;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Work History')] class extends Component
{
    use WithPagination;

    public string $range = 'today';

    public string $from = '';

    public string $to = '';

    public ?int $editingId = null;

    public string $edit_hours = '';

    public string $edit_reason = '';

    /** @var array<int, int> */
    public array $compareUserIds = [];

    public function mount(?User $user = null): void
    {
        if (auth()->user()->can('view-all-work-history')) {
            $this->compareUserIds = [$user->id ?? auth()->id()];

            return;
        }

        if ($user && $user->isNot(auth()->user())) {
            abort(403);
        }

        $this->compareUserIds = [auth()->id()];
    }

    public function updatedRange(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function periodBounds(): array
    {
        return match ($this->range) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                $this->from ? \Illuminate\Support\Carbon::parse($this->from)->startOfDay() : now()->startOfDay(),
                $this->to ? \Illuminate\Support\Carbon::parse($this->to)->endOfDay() : now()->endOfDay(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    public function startEdit(int $id): void
    {
        $history = WorkHistory::findOrFail($id);
        Gate::authorize('update', $history);

        $this->editingId = $id;
        $this->edit_hours = (string) $history->actual_hours;
        $this->edit_reason = '';
    }

    public function saveEdit(TaskWorkflowService $workflow): void
    {
        $history = WorkHistory::findOrFail($this->editingId);
        Gate::authorize('update', $history);

        $data = $this->validate([
            'edit_hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'edit_reason' => ['required', 'string', 'max:255'],
        ]);

        $workflow->editCompletedHours($history, (float) $data['edit_hours'], auth()->user(), $data['edit_reason']);

        $this->editingId = null;
    }

    public function with(): array
    {
        [$start, $end] = $this->periodBounds();

        $canViewAll = auth()->user()->can('view-all-work-history');
        $userIds = $canViewAll && $this->compareUserIds !== [] ? $this->compareUserIds : [auth()->id()];

        $shared = [
            'canViewAll' => $canViewAll,
            'allUsers' => $canViewAll ? User::query()->orderBy('name')->get() : collect(),
        ];

        if (count($userIds) > 1) {
            $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

            $comparison = collect($userIds)
                ->map(function (int $id) use ($users, $start, $end) {
                    $user = $users->get($id);

                    if (! $user) {
                        return null;
                    }

                    $periodQuery = WorkHistory::query()->where('user_id', $id)
                        ->whereBetween('completed_date', [$start->toDateString(), $end->toDateString()]);

                    return [
                        'user' => $user,
                        'hours' => (float) (clone $periodQuery)->sum('actual_hours'),
                        'count' => (clone $periodQuery)->count(),
                    ];
                })
                ->filter()
                ->values();

            return $shared + [
                'mode' => 'compare',
                'comparison' => $comparison,
                'maxHours' => max($comparison->max('hours'), 1),
            ];
        }

        $singleUserId = $userIds[0];

        $query = WorkHistory::query()->with(['task', 'assignedBy'])
            ->where('user_id', $singleUserId)
            ->whereBetween('completed_date', [$start->toDateString(), $end->toDateString()])
            ->latest('completed_date');
        $summary = (clone $query)->sum('actual_hours');

        return $shared + [
            'mode' => 'single',
            'viewingUser' => User::find($singleUserId),
            'histories' => $query->paginate(15),
            'summaryHours' => $summary,
            'canEdit' => auth()->user()->can('edit-completed-hours'),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Work History</h1>
            <p class="text-sm text-zinc-500">Automatically recorded from completed tasks.</p>
        </div>
    </div>

    @if ($canViewAll)
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Team members</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($allUsers as $option)
                    <label class="cursor-pointer">
                        <input type="checkbox" wire:model.live="compareUserIds" value="{{ $option->id }}" class="peer sr-only">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-300 px-3 py-1 text-xs font-medium text-zinc-600 peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white">
                            <span class="flex size-4 items-center justify-center rounded-full bg-zinc-200 text-[9px] font-semibold text-zinc-600 peer-checked:bg-white/20 peer-checked:text-white">{{ Avatar::initials($option->name) }}</span>
                            {{ $option->name }}
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-zinc-400">Select multiple to compare hours side by side.</p>
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div class="flex gap-2">
            @foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom Range'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('range', '{{ $key }}')"
                    class="rounded-lg border px-3 py-1.5 text-sm font-medium {{ $range === $key ? 'border-brand bg-brand text-white' : 'border-zinc-300 text-zinc-600 hover:bg-zinc-50' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($range === 'custom')
            <div class="flex items-center gap-2">
                <input wire:model.live="from" type="date" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                <span class="text-sm text-zinc-500">to</span>
                <input wire:model.live="to" type="date" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
            </div>
        @endif

        @if ($mode === 'single')
            <div class="ml-auto rounded-lg bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700">
                {{ $viewingUser->is(auth()->user()) ? 'Your total' : $viewingUser->name.'\'s total' }}: {{ number_format($summaryHours, 2) }}h
            </div>
        @endif
    </div>

    @if ($mode === 'compare')
        {{-- Side-by-side comparison --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($comparison as $row)
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white">{{ Avatar::initials($row['user']->name) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900">{{ $row['user']->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $row['count'] }} task{{ $row['count'] === 1 ? '' : 's' }} completed</p>
                        </div>
                    </div>

                    <p class="mt-3 text-2xl font-semibold text-zinc-900">{{ number_format($row['hours'], 2) }}<span class="text-sm font-normal text-zinc-400">h</span></p>

                    <div class="mt-2 h-1.5 w-full rounded-full bg-zinc-100">
                        <div class="h-1.5 rounded-full bg-brand" style="width: {{ min(100, $row['hours'] / $maxHours * 100) }}%"></div>
                    </div>

                    <a href="{{ route('work-history.show', $row['user']) }}" wire:navigate class="mt-3 inline-block text-xs font-medium text-brand hover:underline">View full history →</a>
                </div>
            @endforeach
        </div>
    @else
        {{-- Single-user detail table --}}
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Task</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Assigned By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Hours</th>
                        @if ($canEdit)
                            <th class="px-4 py-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($histories as $history)
                        <tr>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $history->completed_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900">
                                <span class="mr-1 inline-block rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $history->task->task_key }}</span>
                                {{ $history->task->title }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $history->assignedBy->name }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500">
                                @if ($editingId === $history->id)
                                    <div class="flex items-center gap-2">
                                        <input wire:model="edit_hours" type="number" step="0.1" class="w-20 rounded-lg border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                        <input wire:model="edit_reason" type="text" placeholder="Reason" class="w-40 rounded-lg border border-zinc-300 px-2 py-1 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                        <button type="button" wire:click="saveEdit" class="text-sm font-medium text-brand hover:underline">Save</button>
                                        <button type="button" wire:click="$set('editingId', null)" class="text-sm text-zinc-500 hover:underline">Cancel</button>
                                    </div>
                                    @error('edit_hours') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    @error('edit_reason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    {{ number_format($history->actual_hours, 2) }}h
                                @endif
                            </td>
                            @if ($canEdit)
                                <td class="px-4 py-3 text-right text-sm">
                                    @if ($editingId !== $history->id)
                                        <button type="button" wire:click="startEdit({{ $history->id }})" class="text-zinc-600 hover:text-brand hover:underline">Edit</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500">No work history for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $histories->links() }}
    @endif
</div>
