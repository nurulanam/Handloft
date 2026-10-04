<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton row (always id 1) holding app-wide settings configurable from
 * the Settings page, rather than a full key/value settings table — there are
 * only a couple of these and they don't need per-key flexibility.
 */
#[Fillable([
    'show_loading_screen',
    'loading_screen_seconds',
    'loading_screen_opacity',
    'loading_screen_blur',
    'mail_host',
    'mail_port',
    'mail_username',
    'mail_password',
    'mail_encryption',
    'mail_from_address',
    'mail_from_name',
    'mail_brand_color',
    'mail_button_text_color',
    'mail_footer_note',
])]
class AppSetting extends Model
{
    protected function casts(): array
    {
        return [
            'show_loading_screen' => 'boolean',
            'loading_screen_seconds' => 'integer',
            'loading_screen_opacity' => 'integer',
            'loading_screen_blur' => 'integer',
            'mail_port' => 'integer',
            // Laravel transparently encrypts/decrypts this on save/read, so
            // the SMTP password is never stored or logged in plain text.
            'mail_password' => 'encrypted',
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

    /**
     * Whether a custom SMTP server has been configured here, as opposed to
     * falling back to whatever's in .env.
     */
    public function hasCustomMailSettings(): bool
    {
        return filled($this->mail_host);
    }

    /**
     * The brand color used for links/buttons/header in outgoing notification
     * emails — falls back to the app's own brand green when unset.
     */
    public function mailBrandColor(): string
    {
        return $this->mail_brand_color ?: '#10512a';
    }

    /**
     * The text color drawn on top of the brand color (e.g. the button
     * label) — falls back to white, which reads on any reasonably dark
     * brand color.
     */
    public function mailButtonTextColor(): string
    {
        return $this->mail_button_text_color ?: '#ffffff';
    }
}
