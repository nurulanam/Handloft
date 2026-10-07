{{-- The primary submit button, with a spinner while the given Livewire action runs. --}}
@props(['target' => 'save', 'form' => null])

<button type="submit" @if ($form) form="{{ $form }}" @endif wire:loading.attr="disabled" wire:target="{{ $target }}" {{ $attributes->class('btn-primary') }}>
    <svg wire:loading wire:target="{{ $target }}" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
    {{ $slot }}
</button>
