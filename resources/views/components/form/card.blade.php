{{-- A form section: icon, title and short description in a header, fields below. With the Liquid glass
     style the card is frosted (it matches the glass card rule in app.css); controls inside stay solid. --}}
@props(['title' => null, 'description' => null, 'icon' => null, 'flush' => false])

{{-- relative + has-[…]: a frosted card is its own stacking context, so while one of its menus is open
     it's lifted above the cards after it, which would otherwise paint over the menu. --}}
<section {{ $attributes->class('relative rounded-2xl border border-zinc-200 bg-surface has-[[aria-expanded=true]]:z-20') }}>
    @if ($title)
        <header class="flex items-start gap-3 border-b border-zinc-100 px-4 py-3.5 sm:px-5">
            @if ($icon)
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5">{!! $icon !!}</svg>
                </span>
            @endif
            <div class="min-w-0 flex-1 {{ $icon ? '' : 'py-0.5' }}">
                <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
                @if ($description)
                    <p class="text-xs text-zinc-500">{{ $description }}</p>
                @endif
            </div>
            @isset($aside)
                <div class="shrink-0 self-center">{{ $aside }}</div>
            @endisset
        </header>
    @endif

    <div @class(['space-y-5 px-4 py-5 sm:px-5' => ! $flush])>
        {{ $slot }}
    </div>
</section>
