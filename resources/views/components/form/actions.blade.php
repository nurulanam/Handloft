{{-- Phones: Cancel and the primary action in a frosted bar that stays in view (just above the bottom
     shortcut bar) until the end of the form. From sm up the page header carries the actions instead. --}}
@props(['cancel', 'target' => 'save'])

<div class="sticky bottom-[calc(5.25rem+env(safe-area-inset-bottom))] z-10 mt-6 sm:hidden">
    <div class="flex gap-2 rounded-2xl border border-white/60 bg-surface/80 p-2 shadow-lg shadow-zinc-900/10 backdrop-blur-xl">
        <a href="{{ $cancel }}" wire:navigate class="btn-secondary flex-1">Cancel</a>
        <x-form.submit :target="$target" class="flex-[1.4]">{{ $slot }}</x-form.submit>
    </div>
</div>
