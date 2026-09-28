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
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Team</h1>
            <p class="text-sm text-slate-500">Manage user accounts, roles, and status.</p>
        </div>

        <a href="{{ route('users.create') }}" wire:navigate class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Create User
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">User ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Role</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->user_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->getRoleNames()->first() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->department ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $user->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('users.edit', $user) }}" wire:navigate class="text-slate-600 hover:text-slate-900 hover:underline">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
