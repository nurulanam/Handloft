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

<div
    class="relative"
    x-data="{
        open: false,
        dragY: 0,
        startY: null,
        dismissing: false,
        toggle() { this.open = ! this.open; this.dragY = 0; this.dismissing = false; },
        dragStart(e) { this.startY = e.touches[0].clientY; },
        dragMove(e) {
            if (this.startY === null) return;
            this.dragY = Math.min(0, e.touches[0].clientY - this.startY);
        },
        {{-- A swipe past the threshold finishes the slide-up from wherever the finger let go (same 300ms
             curve as opening), and only then hides the sheet — rather than snapping back down first and
             replaying the close from the top. A short drag springs back. --}}
        dragEnd() {
            const swipedAway = this.dragY < -60;
            this.startY = null;
            if (! swipedAway) { this.dragY = 0; return; }
            this.dismissing = true;
            {{-- dismissing stays true (holding the sheet off-screen) through x-show's own leave; toggle() resets it on the next open. --}}
            setTimeout(() => { this.open = false; }, 300);
        },
    }"
    x-effect="document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 640)"
    @keydown.escape.window="open = false"
    wire:poll.30s
>
    <button type="button" @click="toggle()" class="relative rounded-full p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" title="Notifications" :aria-expanded="open">
        <span class="sr-only">Notifications</span>
        <x-nav-icon name="notifications" class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-0.5 top-0.5 flex size-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-semibold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    {{-- Desktop: a frosted-glass dropdown under the bell, styled like the phone sheet. --}}
    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition:enter="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
        x-transition:enter-start="-translate-y-1 scale-95 opacity-0"
        x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="-translate-y-1 scale-95 opacity-0"
        class="absolute right-0 z-30 mt-2 hidden w-96 origin-top-right flex-col overflow-hidden rounded-2xl border border-white/60 bg-white/70 shadow-2xl shadow-zinc-900/20 backdrop-blur-xl backdrop-saturate-150 sm:flex"
    >
        <div class="flex items-center justify-between px-4 pb-2 pt-3.5">
            <div>
                <p class="text-sm font-semibold text-zinc-900">Notifications</p>
                <p class="text-xs text-zinc-500">{{ $unreadCount > 0 ? $unreadCount.' unread' : 'All caught up' }}</p>
            </div>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="rounded-full px-3 py-1.5 text-xs font-medium text-brand hover:bg-white/60">Mark all read</button>
            @endif
        </div>

        <div class="max-h-96 space-y-1.5 overflow-y-auto overscroll-contain px-2">
            @forelse ($recent as $notification)
                <button
                    type="button"
                    wire:click="openNotification('{{ $notification->id }}')"
                    class="flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left transition-colors {{ $notification->read_at ? 'bg-white/40 hover:bg-white/70' : 'bg-white/85 shadow-sm hover:bg-white' }}"
                >
                    <span class="relative flex size-8 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-zinc-900/5 text-zinc-500' : 'bg-brand/10 text-brand' }}">
                        <x-nav-icon :name="isset($notification->data['task_id']) ? 'tasks' : 'projects'" class="size-4" />
                        @unless ($notification->read_at)
                            <span class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-white bg-brand"></span>
                        @endunless
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 block text-sm {{ $notification->read_at ? 'text-zinc-600' : 'font-medium text-zinc-900' }}">{{ $notification->data['message'] ?? '' }}</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <p class="py-8 text-center text-sm text-zinc-500">No notifications yet.</p>
            @endforelse
        </div>

        <div class="p-2">
            <a href="{{ route('notifications.index') }}" wire:navigate @click="open = false" class="block rounded-xl bg-zinc-900/5 py-2 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-900/10">View all notifications</a>
        </div>
    </div>

    {{-- Phones: a frosted-glass sheet that slides down from the top edge. Teleported to <body> because the
         sticky header is its own stacking context — left inside it, the sheet would sit under the bottom
         shortcut bar and the drawer. Swipe it up (or tap outside) to close. --}}
    @teleport('body')
        <div class="sm:hidden">
            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition-opacity duration-300 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-300 ease-out"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="open = false"
                :class="dismissing ? 'opacity-0' : ''"
                class="fixed inset-0 z-50 bg-zinc-900/25 backdrop-blur-sm transition-opacity duration-300"
            ></div>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                x-transition:enter-start="-translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="-translate-y-full"
                :style="dismissing
                    ? 'transform: translateY(-100%); transition: transform 300ms cubic-bezier(0.32, 0.72, 0, 1)'
                    : (startY !== null ? `transform: translateY(${dragY}px); transition: none` : '')"
                class="fixed inset-x-0 top-0 z-50 flex transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] max-h-[85vh] flex-col rounded-b-3xl border-b border-white/60 bg-white/70 pt-[env(safe-area-inset-top)] shadow-2xl shadow-zinc-900/20 backdrop-blur-xl backdrop-saturate-150"
                role="dialog"
                aria-label="Notifications"
            >
                <div class="flex items-center justify-between px-5 pb-3 pt-4" @touchstart.passive="dragStart($event)" @touchmove.passive="dragMove($event)" @touchend="dragEnd()">
                    <div>
                        <p class="text-lg font-semibold text-zinc-900">Notifications</p>
                        <p class="text-xs text-zinc-500">{{ $unreadCount > 0 ? $unreadCount.' unread' : 'All caught up' }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($unreadCount > 0)
                            <button type="button" wire:click="markAllAsRead" class="rounded-full px-3 py-1.5 text-xs font-medium text-brand hover:bg-white/60">Mark all read</button>
                        @endif
                        <button type="button" @click="open = false" class="rounded-full p-2 text-zinc-500 hover:bg-white/60" title="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain px-3">
                    @forelse ($recent as $notification)
                        <button
                            type="button"
                            wire:click="openNotification('{{ $notification->id }}')"
                            class="flex w-full items-start gap-3 rounded-2xl px-3 py-3 text-left transition-colors {{ $notification->read_at ? 'bg-white/40 active:bg-white/70' : 'bg-white/85 shadow-sm active:bg-white' }}"
                        >
                            <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-zinc-900/5 text-zinc-500' : 'bg-brand/10 text-brand' }}">
                                <x-nav-icon :name="isset($notification->data['task_id']) ? 'tasks' : 'projects'" class="size-4" />
                                @unless ($notification->read_at)
                                    <span class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-white bg-brand"></span>
                                @endunless
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 block text-sm {{ $notification->read_at ? 'text-zinc-600' : 'font-medium text-zinc-900' }}">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                        </button>
                    @empty
                        <p class="py-10 text-center text-sm text-zinc-500">No notifications yet.</p>
                    @endforelse
                </div>

                <div class="px-3 pb-2 pt-3">
                    <a href="{{ route('notifications.index') }}" wire:navigate @click="open = false" class="block rounded-2xl bg-zinc-900/5 py-2.5 text-center text-sm font-medium text-zinc-700 active:bg-zinc-900/10">View all notifications</a>
                </div>

                {{-- Drag handle: swipe up here (or on the header) to dismiss. --}}
                <div class="flex justify-center pb-2.5 pt-1" @touchstart.passive="dragStart($event)" @touchmove.passive="dragMove($event)" @touchend="dragEnd()">
                    <span class="h-1.5 w-10 rounded-full bg-zinc-900/20"></span>
                </div>
            </div>
        </div>
    @endteleport
</div>
