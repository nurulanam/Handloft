<?php

use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $user = auth()->user();

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->take(8)->get(),
        ];
    }

    /**
     * A plain button + redirect (not an <a href> with a wire:click
     * alongside it) so marking as read and navigating never race each
     * other — the same fix used for the sidebar's submenu links.
     */
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
};
?>

<div class="relative" x-data="{ open: false }" wire:poll.30s>
    <button type="button" @click="open = ! open" class="relative rounded-full p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" title="Notifications">
        <span class="sr-only">Notifications</span>
        <x-nav-icon name="notifications" class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-0.5 top-0.5 flex size-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-semibold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition class="fixed inset-x-4 top-16 z-30 rounded-lg border border-zinc-200 bg-white py-2 shadow-lg sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-80">
        <div class="flex items-center justify-between px-3 pb-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Notifications</p>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-brand hover:underline">Mark all as read</button>
            @endif
        </div>

        <div class="max-h-96 divide-y divide-zinc-100 overflow-y-auto">
            @forelse ($recent as $notification)
                <button
                    type="button"
                    wire:click="openNotification('{{ $notification->id }}')"
                    class="block w-full px-3 py-2.5 text-left text-sm hover:bg-zinc-50 {{ $notification->read_at ? 'text-zinc-500' : 'text-zinc-900' }}"
                >
                    <div class="flex items-start gap-2">
                        @unless ($notification->read_at)
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            <p class="truncate">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <p class="px-3 py-6 text-center text-sm text-zinc-400">No notifications yet.</p>
            @endforelse
        </div>

        <div class="border-t border-zinc-100 px-3 pt-2">
            <a href="{{ route('notifications.index') }}" wire:navigate class="block text-center text-xs font-medium text-brand hover:underline">View all</a>
        </div>
    </div>
</div>
