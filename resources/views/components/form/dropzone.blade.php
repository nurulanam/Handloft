{{-- File upload area: click to browse, or drag files onto it (the file input covers the whole area, so a
     drop lands on it natively). Chosen files are listed in the slot, below. --}}
@props(['model', 'multiple' => true, 'hint' => 'Any file type, up to 10 MB each', 'compact' => false])

<div {{ $attributes->class('space-y-3') }}>
    <label
        x-data="{ over: false }"
        @dragenter="over = true"
        @dragleave="over = false"
        @drop="over = false"
        :class="over ? 'border-brand bg-brand/5' : 'border-zinc-300 hover:border-brand/60 hover:bg-brand/[0.03]'"
        @class([
            'relative flex cursor-pointer items-center rounded-xl border-2 border-dashed transition-colors',
            'flex-col justify-center gap-2 px-4 py-6 text-center' => ! $compact,
            'gap-3 px-3.5 py-3' => $compact,
        ])
    >
        <span @class(['flex shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand', 'size-10' => ! $compact, 'size-8' => $compact])>
            <svg wire:loading.remove wire:target="{{ $model }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M12 13v8"/><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="m8 17 4-4 4 4"/></svg>
            <svg wire:loading wire:target="{{ $model }}" class="size-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        </span>
        <span class="min-w-0 {{ $compact ? 'text-left' : '' }}">
            <span class="block text-sm text-zinc-600">
                <span wire:loading.remove wire:target="{{ $model }}"><span class="font-semibold text-brand">Click to upload</span> or drag and drop</span>
                <span wire:loading wire:target="{{ $model }}" class="font-medium text-brand">Uploading…</span>
            </span>
            <span class="block text-xs text-zinc-400">{{ $hint }}</span>
        </span>
        <input wire:model="{{ $model }}" type="file" @if ($multiple) multiple @endif class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
    </label>

    {{ $slot }}
</div>
