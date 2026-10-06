<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Notifications')] class extends Component
{
    use WithPagination;

    /** 'all' or 'unread'. */
    #[Url]
    public string $filter = 'all';

    /** @var array<int, string> Ids of the notifications ticked for a bulk action. */
    public array $selected = [];

    public function updatedFilter(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    public function updatedPage(): void
    {
        $this->selected = [];
    }

    public function openNotification(string $id): void
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('notifications.index'), navigate: true);
    }

    public function toggleRead(string $id): void
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return;
        }

        $notification->read_at ? $notification->markAsUnread() : $notification->markAsRead();
    }

    /**
     * Marks the ticked notifications read or unread. Scoped to the user's own
     * notifications, so ids from anywhere else are simply ignored.
     */
    public function markSelected(bool $read): void
    {
        $count = auth()->user()->notifications()
            ->whereIn('id', $this->selected)
            ->update(['read_at' => $read ? now() : null]);

        $this->selected = [];

        $this->dispatch('notify',
            message: $count.' '.Str::plural('notification', $count).' marked as '.($read ? 'read' : 'unread').'.',
            type: 'success',
        );
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->selected = [];
    }

    public function with(): array
    {
        return [
            'notifications' => auth()->user()->notifications()
                ->when($this->filter === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->paginate(20),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ];
    }
};
?>

<div class="space-y-4 sm:space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Notifications</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Updates on projects and tasks connected to you.</p>
        </div>

        <div class="inline-flex shrink-0 rounded-lg border border-zinc-300 bg-white p-0.5" role="group" aria-label="Show">
            @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $key }}')"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1 text-sm font-medium {{ $filter === $key ? 'bg-brand text-white' : 'text-zinc-600 hover:bg-zinc-50' }}"
                >
                    {{ $label }}
                    @if ($key === 'unread' && $unreadCount > 0)
                        <span class="rounded-full px-1.5 text-[11px] font-semibold tabular-nums {{ $filter === $key ? 'bg-white/20 text-white' : 'bg-brand/10 text-brand' }}">{{ $unreadCount }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <div
        class="overflow-hidden rounded-xl border border-zinc-200 bg-white"
        x-data="{ pageIds: @js($notifications->pluck('id')->values()) }"
    >
        {{-- Toolbar: select-all, then the bulk actions once anything is ticked. --}}
        @if ($notifications->isNotEmpty())
            <div class="flex min-h-13 items-center gap-3 border-b border-zinc-100 bg-zinc-50/70 px-4 py-2">
                <label class="flex cursor-pointer items-center" title="Select all on this page">
                    <span class="sr-only">Select all on this page</span>
                    <input
                        type="checkbox"
                        class="size-4 cursor-pointer rounded border-zinc-300 text-brand accent-brand"
                        :checked="pageIds.length > 0 && pageIds.every((id) => $wire.selected.includes(id))"
                        :indeterminate="$wire.selected.length > 0 && ! pageIds.every((id) => $wire.selected.includes(id))"
                        @change="$wire.selected = $event.target.checked ? [...pageIds] : []"
                    >
                </label>

                <template x-if="$wire.selected.length > 0">
                    <div class="flex flex-1 items-center gap-1.5 sm:gap-2">
                        <span class="mr-auto whitespace-nowrap text-sm font-medium text-zinc-700"><span x-text="$wire.selected.length"></span> selected</span>
                        <button type="button" wire:click="markSelected(true)" class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-2.5 py-1.5 text-xs font-semibold sm:px-3 text-white hover:bg-brand/90">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
                            <span class="sm:hidden">Read</span><span class="hidden sm:inline">Mark read</span>
                        </button>
                        <button type="button" wire:click="markSelected(false)" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-semibold sm:px-3 text-zinc-700 hover:bg-zinc-50">
                            <span class="size-2 rounded-full bg-brand"></span>
                            <span class="sm:hidden">Unread</span><span class="hidden sm:inline">Mark unread</span>
                        </button>
                        <button type="button" @click="$wire.selected = []" class="flex items-center rounded-lg p-1.5 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 sm:px-2" title="Clear selection">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 sm:hidden"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                            <span class="hidden sm:inline">Clear</span>
                        </button>
                    </div>
                </template>

                <template x-if="$wire.selected.length === 0">
                    <div class="flex flex-1 items-center justify-between gap-2">
                        <span class="text-sm text-zinc-500">{{ $unreadCount > 0 ? $unreadCount.' unread' : 'All caught up' }}</span>
                        @if ($unreadCount > 0)
                            <button type="button" wire:click="markAllAsRead" class="whitespace-nowrap rounded-lg px-2 py-1.5 text-xs font-semibold text-brand hover:bg-brand/5">Mark all as read</button>
                        @endif
                    </div>
                </template>
            </div>
        @endif

        <ul class="divide-y divide-zinc-100">
            @forelse ($notifications as $notification)
                <li wire:key="notification-{{ $notification->id }}" class="group flex items-start gap-3 px-4 py-3.5 transition-colors {{ $notification->read_at ? 'hover:bg-zinc-50' : 'bg-brand/3 hover:bg-brand/6' }}" :class="$wire.selected.includes(@js($notification->id)) && 'bg-brand/7!'">
                    <label class="flex shrink-0 cursor-pointer items-center pt-2.5">
                        <span class="sr-only">Select notification</span>
                        <input type="checkbox" wire:model="selected" value="{{ $notification->id }}" class="size-4 cursor-pointer rounded border-zinc-300 accent-brand">
                    </label>

                    <button
                        type="button"
                        wire:click="openNotification('{{ $notification->id }}')"
                        class="flex min-w-0 flex-1 items-start gap-3 text-left"
                    >
                        <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-zinc-100 text-zinc-400' : 'bg-brand/10 text-brand' }}">
                            <x-nav-icon :name="isset($notification->data['task_id']) ? 'tasks' : 'projects'" class="size-4" />
                            @unless ($notification->read_at)
                                <span class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-white bg-brand"></span>
                            @endunless
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $notification->read_at ? 'text-zinc-600' : 'font-medium text-zinc-900' }}">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-0.5 block text-xs text-zinc-400" title="{{ $notification->created_at->format('d M Y — h:i A') }}">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="toggleRead('{{ $notification->id }}')"
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-brand sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                        title="{{ $notification->read_at ? 'Mark as unread' : 'Mark as read' }}"
                    >
                        <span class="sr-only">{{ $notification->read_at ? 'Mark as unread' : 'Mark as read' }}</span>
                        @if ($notification->read_at)
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6Z"/><path d="m22 10-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 10"/></svg>
                        @endif
                    </button>
                </li>
            @empty
                <li class="px-4 py-12 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand/10 text-brand">
                        <x-nav-icon name="notifications" class="size-5" />
                    </span>
                    <p class="mt-3 text-sm font-medium text-zinc-900">{{ $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' }}</p>
                    <p class="text-xs text-zinc-500">{{ $filter === 'unread' ? "You're all caught up." : 'Updates on your tasks and projects will show up here.' }}</p>
                </li>
            @endforelse
        </ul>
    </div>

    {{ $notifications->links() }}
</div>
