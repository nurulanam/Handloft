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
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">Notifications</h1>
            <p class="hidden text-sm text-zinc-500 sm:block">Updates on projects and tasks connected to you.</p>
        </div>

        @if ($unreadCount > 0)
            <button type="button" wire:click="markAllAsRead" class="whitespace-nowrap text-sm font-medium text-brand hover:underline">Mark all as read</button>
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
                        <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-zinc-100 text-zinc-400' : 'bg-brand/10 text-brand' }}">
                            <x-nav-icon :name="isset($notification->data['task_id']) ? 'tasks' : 'projects'" class="size-4" />
                            @unless ($notification->read_at)
                                <span class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-white bg-brand"></span>
                            @endunless
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm {{ $notification->read_at ? 'text-zinc-600' : 'font-medium text-zinc-900' }}">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-0.5 text-xs text-zinc-400" title="{{ $notification->created_at->format('d M Y — h:i A') }}">{{ $notification->created_at->diffForHumans() }}</p>
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
