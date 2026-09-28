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
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Team</h1>
            <p class="text-sm text-zinc-500">Manage user accounts, roles, and status.</p>
        </div>

        <a href="{{ route('users.create') }}" wire:navigate class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">
            Create User
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
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
                        <td class="px-4 py-3 text-right text-sm">
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
