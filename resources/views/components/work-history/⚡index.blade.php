<?php

use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Avatar;
use App\Support\Duration;
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

    public ?int $editingLogId = null;

    public string $edit_hours = '0';

    public string $edit_minutes = '0';

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

    private function authorizeTimeLogEdit(TaskTimeLog $timeLog): void
    {
        abort_unless($timeLog->user_id === auth()->id() || auth()->user()->can('edit-completed-hours'), 403);
    }

    public function startEdit(int $timeLogId): void
    {
        $timeLog = TaskTimeLog::findOrFail($timeLogId);
        $this->authorizeTimeLogEdit($timeLog);

        [$hours, $minutes] = Duration::toParts((float) $timeLog->hours);

        $this->editingLogId = $timeLogId;
        $this->edit_hours = (string) $hours;
        $this->edit_minutes = (string) $minutes;
        $this->edit_reason = '';
    }

    public function saveEdit(TaskWorkflowService $workflow): void
    {
        $timeLog = TaskTimeLog::findOrFail($this->editingLogId);
        $this->authorizeTimeLogEdit($timeLog);

        $data = $this->validate([
            'edit_hours' => ['required', 'integer', 'min:0', 'max:24'],
            'edit_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'edit_reason' => ['required', 'string', 'max:255'],
        ]);

        $totalHours = Duration::fromParts((int) $data['edit_hours'], (int) $data['edit_minutes']);

        if ($totalHours <= 0 || $totalHours > 24) {
            $this->addError('edit_hours', 'Total logged time must be between a few minutes and 24 hours.');

            return;
        }

        $workflow->editTimeLog($timeLog, $totalHours, auth()->user(), $data['edit_reason']);

        $this->editingLogId = null;
    }

    public function deleteLog(int $timeLogId, TaskWorkflowService $workflow): void
    {
        $timeLog = TaskTimeLog::findOrFail($timeLogId);
        $this->authorizeTimeLogEdit($timeLog);

        $workflow->deleteTimeLog($timeLog, auth()->user());
    }

    public function with(): array
    {
        [$start, $end] = $this->periodBounds();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $canViewAll = auth()->user()->can('view-all-work-history');
        $userIds = $canViewAll && $this->compareUserIds !== [] ? $this->compareUserIds : [auth()->id()];

        $shared = [
            'canViewAll' => $canViewAll,
            'allUsers' => $canViewAll ? User::query()->orderBy('name')->get() : collect(),
        ];

        if (count($userIds) > 1) {
            $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

            $comparison = collect($userIds)
                ->map(function (int $id) use ($users, $startDate, $endDate) {
                    $user = $users->get($id);

                    if (! $user) {
                        return null;
                    }

                    $periodQuery = TaskTimeLog::query()->where('user_id', $id)
                        ->whereDate('logged_date', '>=', $startDate)
                        ->whereDate('logged_date', '<=', $endDate);

                    return [
                        'user' => $user,
                        'hours' => (float) (clone $periodQuery)->sum('hours'),
                        'count' => (clone $periodQuery)->distinct('task_id')->count('task_id'),
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

        // Every task this person logged any time on within the period — as
        // assignee, Reporter, or QA/Reviewer alike, since anyone connected to
        // a task can log their own time on it. This is the accurate, granular
        // source of "work history" now, rather than the one-off snapshot
        // captured whenever a task happened to be submitted for QA.
        $taskIds = TaskTimeLog::query()->where('user_id', $singleUserId)
            ->whereDate('logged_date', '>=', $startDate)
            ->whereDate('logged_date', '<=', $endDate)
            ->distinct()
            ->pluck('task_id');

        $tasks = Task::query()
            ->whereIn('id', $taskIds)
            ->with(['timeLogs' => fn ($q) => $q->where('user_id', $singleUserId)
                ->whereDate('logged_date', '>=', $startDate)
                ->whereDate('logged_date', '<=', $endDate)
                ->orderByDesc('logged_date')])
            ->withSum(['timeLogs as period_hours' => fn ($q) => $q->where('user_id', $singleUserId)
                ->whereDate('logged_date', '>=', $startDate)
                ->whereDate('logged_date', '<=', $endDate)], 'hours')
            ->addSelect(['latest_log_date' => TaskTimeLog::query()
                ->select('logged_date')
                ->whereColumn('task_id', 'tasks.id')
                ->where('user_id', $singleUserId)
                ->whereDate('logged_date', '>=', $startDate)
                ->whereDate('logged_date', '<=', $endDate)
                ->orderByDesc('logged_date')
                ->limit(1),
            ])
            ->orderByDesc('latest_log_date')
            ->paginate(15);

        $summary = (float) TaskTimeLog::query()->where('user_id', $singleUserId)
            ->whereDate('logged_date', '>=', $startDate)
            ->whereDate('logged_date', '<=', $endDate)
            ->sum('hours');

        return $shared + [
            'mode' => 'single',
            'viewingUser' => User::find($singleUserId),
            'tasks' => $tasks,
            'summaryHours' => $summary,
            'canEdit' => auth()->user()->can('edit-completed-hours'),
        ];
    }
};
?>

<div class="space-y-4 sm:space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Work History</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Everyone's daily time logs — as assignee, Reporter, or QA/Reviewer.</p>
        </div>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white">
        @if ($canViewAll)
            <div class="border-b border-zinc-100 p-3 sm:p-4">
                <div class="mb-2 flex items-baseline justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Team members</p>
                    <p class="text-xs text-zinc-400">
                        <span class="sm:hidden">Pick several to compare</span>
                        <span class="hidden sm:inline">Select multiple to compare hours side by side.</span>
                    </p>
                </div>
                {{-- Phones: one swipeable row instead of a wrapping block of chips. --}}
                <div x-data x-init="setTimeout(() => { const c = $el.querySelector('input:checked'); if (c) $el.scrollLeft = c.closest('label').offsetLeft - 12 })" class="relative -mx-3 flex gap-2 overflow-x-auto px-3 pb-0.5 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 [&::-webkit-scrollbar]:hidden">
                    @foreach ($allUsers as $option)
                        <label class="shrink-0 cursor-pointer">
                            <input type="checkbox" wire:model.live="compareUserIds" value="{{ $option->id }}" class="peer sr-only">
                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-zinc-300 py-1 pl-1 pr-3 text-xs font-medium text-zinc-600 peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50">
                                <span class="flex size-5 items-center justify-center rounded-full bg-zinc-900/10 text-[9px] font-semibold">{{ Avatar::initials($option->name) }}</span>
                                {{ $option->name }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-3 p-3 sm:flex-row sm:flex-wrap sm:items-center sm:p-4">
            <div class="grid grid-cols-4 rounded-lg border border-zinc-300 bg-white p-0.5 sm:inline-flex">
                @foreach (['today' => ['Today', 'Today'], 'week' => ['Week', 'This Week'], 'month' => ['Month', 'This Month'], 'custom' => ['Custom', 'Custom Range']] as $key => [$short, $label])
                    <button
                        type="button"
                        wire:click="$set('range', '{{ $key }}')"
                        class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium sm:py-1 {{ $range === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}"
                    >
                        <span class="sm:hidden">{{ $short }}</span>
                        <span class="hidden sm:inline">{{ $label }}</span>
                    </button>
                @endforeach
            </div>

            @if ($range === 'custom')
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    @foreach (['from' => 'From', 'to' => 'To'] as $field => $label)
                        <label class="relative block">
                            <span class="pointer-events-none absolute left-3 top-1.5 text-[10px] font-medium uppercase tracking-wide text-zinc-400">{{ $label }}</span>
                            <input wire:model.live="{{ $field }}" type="date" class="block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 pb-1.5 pt-5 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40 sm:w-40">
                        </label>
                    @endforeach
                </div>
            @endif

            @if ($mode === 'single')
                <div class="flex items-center justify-between gap-3 rounded-lg bg-brand/5 px-3 py-2 sm:ml-auto">
                    <span class="text-xs font-medium text-zinc-500">{{ $viewingUser->is(auth()->user()) ? 'Your total' : $viewingUser->name.'\'s total' }}</span>
                    <span class="text-base font-semibold text-brand">{{ Duration::forHumans($summaryHours) }}</span>
                </div>
            @endif
        </div>
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
                            <p class="text-xs text-zinc-500">{{ $row['count'] }} task{{ $row['count'] === 1 ? '' : 's' }} logged</p>
                        </div>
                    </div>

                    <p class="mt-3 text-2xl font-semibold text-zinc-900">{{ Duration::forHumans($row['hours']) }}</p>

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
                        <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 sm:table-cell">Last Logged</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Task</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 sm:table-cell">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Hours</th>
                    </tr>
                </thead>
                @forelse ($tasks as $task)
                    <tbody x-data="{ open: false }" class="divide-y divide-zinc-100">
                        <tr>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-sm text-zinc-500 sm:table-cell">{{ \Illuminate\Support\Carbon::parse($task->latest_log_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900">
                                <a href="{{ route('tasks.show', $task) }}" wire:navigate class="hover:text-brand">
                                    <span class="mr-1 inline-block rounded-full bg-violet-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-700">{{ $task->task_key }}</span>
                                    {{ $task->title }}
                                </a>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-normal text-zinc-500 sm:hidden">
                                    <span class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($task->latest_log_date)->format('d M Y') }}</span>
                                    <span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 text-sm sm:table-cell">
                                <span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status->pillClasses() }}">{{ $task->status->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500">
                                <button type="button" @click="open = ! open" class="flex items-center gap-1 hover:text-brand">
                                    {{ Duration::forHumans((float) $task->period_hours) }}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-zinc-400 transition-transform" :class="open ? 'rotate-180' : ''">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="4" class="bg-zinc-50 px-4 py-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">Daily time log</p>

                                <div class="space-y-1">
                                    @foreach ($task->timeLogs as $log)
                                        <div class="flex items-center justify-between gap-3 py-1 text-xs text-zinc-600">
                                            @if ($editingLogId === $log->id)
                                                <div class="flex flex-1 flex-wrap items-center gap-2">
                                                    <span class="shrink-0">{{ $log->logged_date->format('d M Y') }}</span>
                                                    <input wire:model="edit_hours" type="number" min="0" max="24" placeholder="Hrs" class="w-14 rounded-lg border border-zinc-300 px-2 py-1 text-xs focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                                    <span class="text-zinc-400">h</span>
                                                    <input wire:model="edit_minutes" type="number" min="0" max="59" placeholder="Min" class="w-14 rounded-lg border border-zinc-300 px-2 py-1 text-xs focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                                    <span class="text-zinc-400">m</span>
                                                    <input wire:model="edit_reason" type="text" placeholder="Reason" class="w-40 rounded-lg border border-zinc-300 px-2 py-1 text-xs focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                                    <button type="button" wire:click="saveEdit" class="font-medium text-brand hover:underline">Save</button>
                                                    <button type="button" wire:click="$set('editingLogId', null)" class="text-zinc-500 hover:underline">Cancel</button>
                                                </div>
                                                @error('edit_hours') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                @error('edit_minutes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                @error('edit_reason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                            @else
                                                <span class="truncate">
                                                    {{ $log->logged_date->format('d M Y') }}
                                                    @if ($log->note)
                                                        <span class="text-zinc-400">— {{ $log->note }}</span>
                                                    @endif
                                                </span>
                                                <span class="flex shrink-0 items-center gap-2">
                                                    <span class="font-medium text-zinc-900">{{ Duration::forHumans((float) $log->hours) }}</span>
                                                    @if ($canEdit || $log->user_id === auth()->id())
                                                        <button type="button" wire:click="startEdit({{ $log->id }})" class="text-zinc-400 hover:text-brand">Edit</button>
                                                        <button type="button" wire:click="deleteLog({{ $log->id }})" wire:confirm="Remove this time entry?" class="text-zinc-400 hover:text-red-600">Remove</button>
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500">No work history for this period.</td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        {{ $tasks->links() }}
    @endif
</div>
