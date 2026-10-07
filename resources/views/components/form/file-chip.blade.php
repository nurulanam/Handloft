{{-- One chosen (not yet uploaded) file: thumbnail or file icon, name, size and a remove button. --}}
@props(['file', 'remove'])

<div class="flex items-center gap-3 rounded-xl border border-zinc-200/80 bg-zinc-50 py-2 pl-2 pr-2.5">
    @if (str($file->getMimeType())->startsWith('image/'))
        <img src="{{ $file->temporaryUrl() }}" alt="" class="size-9 shrink-0 rounded-lg object-cover">
    @else
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-zinc-400 ring-1 ring-zinc-200">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
        </span>
    @endif
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-zinc-800">{{ $file->getClientOriginalName() }}</span>
        <span class="block text-xs text-zinc-500">{{ \App\Support\FileSize::forHumans($file->getSize()) }}</span>
    </span>
    <button type="button" wire:click="{{ $remove }}" class="flex size-7 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Remove">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
    </button>
</div>
