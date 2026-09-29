<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton row (always id 1) holding app-wide settings configurable from
 * the Settings page, rather than a full key/value settings table — there are
 * only a couple of these and they don't need per-key flexibility.
 */
#[Fillable(['show_loading_screen', 'loading_screen_seconds', 'loading_screen_opacity', 'loading_screen_blur'])]
class AppSetting extends Model
{
    protected function casts(): array
    {
        return [
            'show_loading_screen' => 'boolean',
            'loading_screen_seconds' => 'integer',
            'loading_screen_opacity' => 'integer',
            'loading_screen_blur' => 'integer',
        ];
    }

    public static function current(): self
    {
        // firstOrCreate([]) would leave a freshly created row's attributes as
        // null in memory (Eloquent doesn't reload DB column defaults after an
        // insert), so the defaults are passed explicitly here instead.
        return static::query()->firstOrCreate([], [
            'show_loading_screen' => true,
            'loading_screen_seconds' => 3,
            'loading_screen_opacity' => 10,
            'loading_screen_blur' => 64,
        ]);
    }
}
