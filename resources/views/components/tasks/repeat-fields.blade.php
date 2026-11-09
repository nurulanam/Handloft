{{-- The repeat settings, bound to the host Livewire component's repeat, repeat_every, repeat_on (weekdays),
     repeat_day, repeat_until and repeat_max properties (rules: App\Enums\TaskRepeat::rules()). --}}
@props(['repeat' => '', 'until' => ''])

@php
    $types = \App\Enums\TaskRepeat::cases();
    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
    $type = \App\Enums\TaskRepeat::tryFrom((string) $repeat);
@endphp

<div class="space-y-4">
    <x-form.field label="Repeat" error="repeat" optional>
        <x-form.picker model="repeat" :value="$repeat" :options="collect($types)->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])" clearable clear-label="Doesn't repeat" placeholder="Doesn't repeat" />
    </x-form.field>

    @if ($type)
        <x-form.field label="Every" error="repeat_every">
            <div class="flex items-center gap-2">
                <input wire:model.blur="repeat_every" type="number" min="1" max="365" class="field-input w-20" aria-label="Repeat interval">
                <span class="text-sm text-zinc-600">{{ $type->unit() }}(s)</span>
            </div>
        </x-form.field>

        @if ($type === \App\Enums\TaskRepeat::Weekly)
            <x-form.field label="On" error="repeat_on" hint="Leave empty to repeat on the weekday it was finished." optional>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($days as $value => $name)
                        <label class="cursor-pointer">
                            <input wire:model="repeat_on" type="checkbox" value="{{ $value }}" class="peer sr-only">
                            <span class="block rounded-lg border border-zinc-200 px-2.5 py-1.5 text-sm text-zinc-600 transition peer-checked:border-brand peer-checked:bg-brand/10 peer-checked:font-medium peer-checked:text-brand peer-focus-visible:ring-4 peer-focus-visible:ring-brand/20">{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
            </x-form.field>
        @elseif ($type === \App\Enums\TaskRepeat::Monthly)
            <x-form.field label="On day" error="repeat_day" hint="Short months use their last day. Leave empty to use the day it was finished." optional>
                <input wire:model.blur="repeat_day" type="number" min="1" max="31" class="field-input w-24" placeholder="1–31">
            </x-form.field>
        @endif

        <x-form.field label="Stop after" error="repeat_max" hint="Number of repeats. Leave empty to repeat until you stop it." optional>
            <input wire:model.blur="repeat_max" type="number" min="1" class="field-input w-28" placeholder="Never">
        </x-form.field>

        <x-form.field label="Or stop on" error="repeat_until" optional>
            <x-form.date model="repeat_until" :value="$until" placeholder="No end date" />
        </x-form.field>
    @endif
</div>
