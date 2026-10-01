<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Notifications')] class extends Component
{
    use WithPagination;

    public function openNotification(string $id): void
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('notifications.index'), navigate: true);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function with(): array
    {
        return [
            'notifications' => auth()->user()->notifications()->paginate(20),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Notifications</h1>
            <p class="text-sm text-zinc-500">Updates on projects and tasks connected to you.</p>
        </div>

        @if ($unreadCount > 0)
            <button type="button" wire:click="markAllAsRead" class="text-sm font-medium text-brand hover:underline">Mark all as read</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <ul class="divide-y divide-zinc-100">
            @forelse ($notifications as $notification)
                <li>
                    <button
                        type="button"
                        wire:click="openNotification('{{ $notification->id }}')"
                        class="flex w-full items-start gap-3 px-4 py-4 text-left hover:bg-zinc-50"
                    >
                        @unless ($notification->read_at)
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand"></span>
                        @else
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-transparent"></span>
                        @endunless

                        <div class="min-w-0 flex-1">
                            <p class="text-sm {{ $notification->read_at ? 'text-zinc-600' : 'font-medium text-zinc-900' }}">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-0.5 text-xs text-zinc-400">{{ $notification->created_at->format('d M Y — h:i A') }}</p>
                        </div>
                    </button>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-sm text-zinc-500">No notifications yet.</li>
            @endforelse
        </ul>
    </div>

    {{ $notifications->links() }}
</div>
