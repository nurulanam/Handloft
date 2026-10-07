{{-- Label, control (the slot), validation message and hint for one form field. --}}
@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false, 'optional' => false])

<div {{ $attributes }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-zinc-700">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
            @if ($optional)
                <span class="text-xs font-normal text-zinc-400">Optional</span>
            @endif
            {{ $badge ?? '' }}
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5 shrink-0"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                {{ $message }}
            </p>
        @enderror
    @endif

    @if ($hint)
        <p class="mt-1.5 text-xs text-zinc-500">{{ $hint }}</p>
    @endif
</div>
