{{-- A searchable dropdown select for people, projects, categories… Choosing an option either sets a
     Livewire property (model="assigned_to") or calls a Livewire method with its id (call="saveAssignee",
     null when cleared). variant="field" looks like an input; variant="inline" is a quiet, right-aligned
     value for detail lists, where the field is edited in place.
     options: a list of ['value' => …, 'label' => …], plus optional 'hint' (shown under the label), for
     short fixed lists. For people, projects and tasks pass source="people|projects|tasks" (and the current
     choice's label) instead: options are then searched on the server as you type, via the component's
     pickerOptions() (App\Livewire\Concerns\SearchesPickerOptions). --}}
@props([
    'options' => [],
    'source' => null,
    'label' => null,
    'except' => null,
    'value' => '',
    'placeholder' => 'Select…',
    'model' => null,
    'call' => null,
    'avatar' => false,
    'clearable' => false,
    'clearLabel' => 'None',
    'variant' => 'field',
    'disabled' => false,
    'icon' => null,
    'error' => false,
    'align' => null,
])

@php
    $options = collect($options)->map(fn ($o) => ['value' => (string) $o['value'], 'label' => (string) $o['label'], 'hint' => $o['hint'] ?? null])->values();
    $value = (string) ($value ?? '');
    $remote = (bool) $source;
    $selected = $remote ? ($value !== '' ? ['label' => (string) $label] : null) : $options->firstWhere('value', $value);
    $searchable = $remote || $options->count() > 6;
    $inline = $variant === 'inline';
    $align ??= $inline ? 'right' : 'left';
    $pick = fn (string $v) => $call
        ? $call.'('.($v === '' ? 'null' : $v).')'
        : "\$set('{$model}', '".e($v)."')";
@endphp

<div {{ $attributes->class(['relative', 'w-full' => ! $inline, 'min-w-0' => $inline]) }}
    @if ($remote)
        x-data="remotePicker(@js(['value' => $value, 'label' => $selected['label'] ?? '', 'source' => $source, 'except' => $except, 'model' => $model, 'call' => $call, 'limit' => \App\Support\Picker::LIMIT]))"
    @else
        x-data="picker(@js($value), @js($selected['label'] ?? ''), @js($options->pluck('label')))"
    @endif
    @click.outside="open = false"
    @keydown.escape="open = false"
>
    <button
        type="button"
        @click="toggle()"
        @disabled($disabled)
        :aria-expanded="open"
        @class([
            'field-input flex items-center gap-2.5 text-left' => ! $inline,
            'field-input-error' => ! $inline && $error,
            '-mr-2 flex items-center gap-2 rounded-lg px-2 py-1 text-right text-sm transition-colors' => $inline,
            'hover:bg-zinc-100' => $inline && ! $disabled,
            'cursor-default' => $disabled,
        ])
    >
        @if ($avatar)
            <template x-if="value">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-semibold text-white" x-text="initialsOf(label)"></span>
            </template>
            <template x-if="! value && {{ $inline ? 'false' : 'true' }}">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full border border-dashed border-zinc-300 text-zinc-400">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" /></svg>
                </span>
            </template>
        @elseif ($icon)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0 text-zinc-400">{!! $icon !!}</svg>
        @endif

        <span class="truncate {{ $inline ? 'max-w-44 shrink-0' : 'min-w-0 flex-1' }}" :class="value ? 'text-zinc-900' : 'text-zinc-400'" x-text="value ? label : @js($placeholder)">{{ $selected['label'] ?? $placeholder }}</span>

        @unless ($disabled)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-zinc-400 {{ $inline ? 'hidden' : '' }}"><path fill-rule="evenodd" d="M10.53 3.47a.75.75 0 0 0-1.06 0L6.22 6.72a.75.75 0 0 0 1.06 1.06L10 5.06l2.72 2.72a.75.75 0 1 0 1.06-1.06l-3.25-3.25Zm-4.31 9.81 3.25 3.25a.75.75 0 0 0 1.06 0l3.25-3.25a.75.75 0 1 0-1.06-1.06L10 14.94l-2.72-2.72a.75.75 0 0 0-1.06 1.06Z" clip-rule="evenodd" /></svg>
        @endunless
    </button>

    @unless ($disabled)
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="-translate-y-1 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-100 ease-in"
            x-transition:leave-end="opacity-0"
            @class(['menu-panel w-72 max-w-[calc(100vw-2rem)]', 'right-0' => $align === 'right', 'left-0' => $align === 'left', 'min-w-full' => ! $inline])
        >
            @if ($searchable)
                <div class="relative mb-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                    <input x-ref="search" x-model="query" @if ($remote) @input="search()" @endif type="text" placeholder="{{ $remote ? match ($source) { 'people' => 'Search people…', 'projects' => 'Search projects…', default => 'Search by title or key…' } : 'Search…' }}" class="w-full rounded-lg border-0 bg-zinc-100 py-2 pl-8 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20">
                </div>
            @endif

            <div class="max-h-64 overflow-y-auto overscroll-contain">
                @if ($clearable)
                    <button type="button" @unless ($remote) wire:click="{{ $pick('') }}" @endunless @click="choose('', '')" x-show="! query" class="menu-item text-zinc-500">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full border border-dashed border-zinc-300 text-zinc-400">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                        </span>
                        {{ $clearLabel }}
                    </button>
                @endif

                @if ($remote)
                    <template x-for="option in results" :key="option.value">
                        <button type="button" @click="choose(option.value, option.label)" class="menu-item" :class="value === option.value && 'bg-brand/5 font-medium text-brand!'">
                            @if ($avatar)
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-semibold text-white" x-text="initialsOf(option.label)"></span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate" x-text="option.label"></span>
                                <span x-show="option.hint" class="block truncate text-xs font-normal text-zinc-500" x-text="option.hint"></span>
                            </span>
                            <svg x-show="value === option.value" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-brand"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                        </button>
                    </template>
                    <p x-show="loading && results.length === 0" class="flex items-center justify-center gap-2 px-3 py-6 text-sm text-zinc-400">
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        Searching…
                    </p>
                    <p x-show="! loading && results.length === 0" class="px-3 py-6 text-center text-sm text-zinc-400" x-text="query ? 'No matches for “' + query + '”' : 'Nothing here yet'"></p>
                @endif

                @foreach ($options as $option)
                    <button
                        type="button"
                        wire:click="{{ $pick($option['value']) }}"
                        @click="choose(@js($option['value']), @js($option['label']))"
                        @if ($searchable) x-show="matches(@js($option['label']))" @endif
                        class="menu-item"
                        :class="value === @js($option['value']) && 'bg-brand/5 font-medium text-brand!'"
                    >
                        @if ($avatar)
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-semibold text-white">{{ \App\Support\Avatar::initials($option['label']) }}</span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ $option['label'] }}</span>
                            @if ($option['hint'])
                                <span class="block truncate text-xs font-normal text-zinc-500">{{ $option['hint'] }}</span>
                            @endif
                        </span>
                        <svg x-show="value === @js($option['value'])" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-brand"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                    </button>
                @endforeach

                @if ($searchable && ! $remote)
                    <p x-show="nothingMatches" class="px-3 py-6 text-center text-sm text-zinc-400">No matches</p>
                @endif
            </div>

            @if ($remote)
                <p x-show="results.length >= limit" class="border-t border-zinc-100 px-2.5 pb-1 pt-2 text-[11px] text-zinc-400">Showing the first {{ \App\Support\Picker::LIMIT }}. Type to narrow it down.</p>
            @endif
        </div>
    @endunless
</div>
