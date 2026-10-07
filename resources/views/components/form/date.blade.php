{{-- A date field showing the date in words; the device's own date picker sits invisibly on top, so one
     tap or click opens it. --}}
@props(['model', 'value' => '', 'placeholder' => 'Pick a date', 'error' => false])

<label @class(['field-input group relative flex cursor-pointer items-center gap-2.5', 'field-input-error' => $error])>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0 text-zinc-400 transition-colors group-hover:text-brand"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
    <span class="min-w-0 flex-1 truncate {{ $value ? 'text-zinc-900' : 'text-zinc-400' }}">{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('D, d M Y') : $placeholder }}</span>
    <input wire:model.live="{{ $model }}" type="date" {{ $attributes }} class="absolute inset-0 h-full w-full cursor-pointer opacity-0" x-data @click="(() => { try { $el.showPicker() } catch (e) {} })()">
</label>
