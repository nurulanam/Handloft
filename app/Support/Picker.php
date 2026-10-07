<?php

namespace App\Support;

/**
 * Settings shared by <x-form.picker> and its server-side search (App\Livewire\Concerns\SearchesPickerOptions).
 */
final class Picker
{
    /** How many matches a searched picker shows at once; typing narrows them down. */
    public const LIMIT = 20;
}
