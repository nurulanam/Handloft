{{-- A task's due date as a small chip: red once overdue, amber when due within three days, quiet otherwise. --}}
@props(['date' => null, 'done' => false])

@php
    $days = $date ? (int) today()->diffInDays($date->copy()->startOfDay(), false) : null;
    [$text, $tone] = match (true) {
        $date === null => ['No date', 'text-zinc-400'],
        $done => [$date->format('d M'), 'text-zinc-500'],
        $days < 0 => ['Overdue · '.abs($days).'d', 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200'],
        $days === 0 => ['Due today', 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200'],
        $days === 1 => ['Tomorrow', 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200'],
        $days <= 3 => ['In '.$days.' days', 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200'],
        default => [$date->format($date->isCurrentYear() ? 'd M' : 'd M Y'), 'text-zinc-500'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-md text-xs font-medium', 'px-1.5 py-0.5' => str_contains($tone, 'ring'), $tone]) }} @if ($date) title="{{ $date->format('l, d M Y') }}" @endif>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5 shrink-0 opacity-70"><path fill-rule="evenodd" d="M4 1.75a.75.75 0 0 1 1.5 0V3h5V1.75a.75.75 0 0 1 1.5 0V3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2V1.75ZM4.5 6a1 1 0 0 0-1 1v4.5a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-7Z" clip-rule="evenodd" /></svg>
    {{ $text }}
</span>
