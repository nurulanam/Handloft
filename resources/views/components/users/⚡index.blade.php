<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Team')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function with(): array
    {
        return [
            'users' => User::query()->orderBy('name')->paginate(15),
            'canViewAllWorkHistory' => auth()->user()->can('view-all-work-history'),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Team</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Manage user accounts, roles, and status.</p>
        </div>

        <a href="{{ route('users.create') }}" wire:navigate class="shrink-0 whitespace-nowrap rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">
            Create User
        </a>
    </div>

    {{-- Phones: one card per member — the six-column table can't fit. --}}
    <div class="space-y-3 sm:hidden">
        @foreach ($users as $user)
            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-zinc-900">{{ $user->name }}</p>
                        <p class="mt-0.5 truncate text-xs text-zinc-500">
                            {{ $user->user_id ?? '—' }} · {{ $user->getRoleNames()->first() ?? '—' }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $user->isActive() ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500' }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </div>

                <p class="mt-2 text-xs text-zinc-500"><span class="text-zinc-400">Department:</span> {{ $user->department ?? '—' }}</p>

                <div class="mt-3 flex gap-2 border-t border-zinc-100 pt-3">
                    <a href="{{ route('users.edit', $user) }}" wire:navigate class="flex-1 rounded-lg border border-zinc-300 px-3 py-1.5 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-50">Edit</a>
                    @if ($canViewAllWorkHistory)
                        <a href="{{ route('work-history.show', $user) }}" wire:navigate class="flex-1 rounded-lg border border-zinc-300 px-3 py-1.5 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-50">Work History</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 bg-white sm:block">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">User ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Role</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $user->user_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $user->getRoleNames()->first() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $user->department ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $user->isActive() ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500' }}">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            @if ($canViewAllWorkHistory)
                                <a href="{{ route('work-history.show', $user) }}" wire:navigate class="mr-3 text-zinc-600 hover:text-brand hover:underline">Work History</a>
                            @endif
                            <a href="{{ route('users.edit', $user) }}" wire:navigate class="text-zinc-600 hover:text-brand hover:underline">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
