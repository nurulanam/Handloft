<?php

use App\Models\User;
use App\Models\WorkHistory;
use App\Services\TaskWorkflowService;
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

    public User $user;

    public function mount(?User $user = null): void
    {
        $this->user = $user ?? auth()->user();

        if ($this->user->isNot(auth()->user())) {
            Gate::authorize('view', new WorkHistory(['user_id' => $this->user->id]));
        }
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

        $query = WorkHistory::query()->with(['task', 'assignedBy'])->where('user_id', $this->user->id)->latest('completed_date');

        $summary = (clone $query)->whereBetween('completed_date', [$start->toDateString(), $end->toDateString()])->sum('actual_hours');

        return [
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

        <div class="ml-auto rounded-lg bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700">
            Total: {{ number_format($summaryHours, 2) }}h
        </div>
    </div>

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
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900">{{ $history->task->title }}</td>
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
</div>
