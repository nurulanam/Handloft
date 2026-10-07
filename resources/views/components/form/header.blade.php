{{-- Page header for create/edit pages: a back link, the title, an optional subtitle and meta line,
     and the page actions (from sm up; phones get <x-form.actions> at the end of the form). --}}
@props(['back' => null, 'backLabel' => 'Back', 'title', 'subtitle' => null])

<div {{ $attributes->class('mb-5 flex flex-wrap items-end justify-between gap-x-6 gap-y-4 sm:mb-7') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="group mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 transition-colors hover:text-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform group-hover:-translate-x-0.5"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" /></svg>
                {{ $backLabel }}
            </a>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-[1.75rem] sm:leading-9">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 max-w-2xl text-sm text-zinc-500">{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>

    @isset($actions)
        <div class="hidden shrink-0 items-center gap-2 sm:flex">{{ $actions }}</div>
    @endisset
</div>
