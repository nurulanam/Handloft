<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the SMTP server be configured from the Settings page instead of only
 * via .env, and have it take effect immediately — no deploy/restart needed.
 * Falls back to whatever's already in config/mail.php (i.e. .env) when no
 * custom server has been saved.
 */
class MailSettings
{
    public static function applyFromDatabase(): void
    {
        // Guard against running before the table exists (fresh install,
        // migrations not yet run) or the database not being reachable yet.
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }

            $settings = AppSetting::query()->first();
        } catch (\Throwable) {
            return;
        }

        if (! $settings || ! $settings->hasCustomMailSettings()) {
            return;
        }

        static::applyFrom($settings);
    }

    /**
     * @param  AppSetting|array{mail_host: ?string, mail_port: ?int, mail_username: ?string, mail_password: ?string, mail_encryption: ?string, mail_from_address: ?string, mail_from_name: ?string}  $settings
     */
    public static function applyFrom(AppSetting|array $settings): void
    {
        $get = fn (string $key) => is_array($settings) ? ($settings[$key] ?? null) : $settings->{$key};

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $get('mail_host'),
            'mail.mailers.smtp.port' => $get('mail_port'),
            'mail.mailers.smtp.username' => $get('mail_username'),
            'mail.mailers.smtp.password' => $get('mail_password'),
            'mail.mailers.smtp.encryption' => $get('mail_encryption') ?: null,
            'mail.from.address' => $get('mail_from_address') ?: config('mail.from.address'),
            'mail.from.name' => $get('mail_from_name') ?: config('mail.from.name'),
        ]);

        // Drop any already-resolved "smtp" mailer so the next send is built
        // fresh from the config just set, instead of reusing a transport
        // created from the old settings earlier in the same request.
        app('mail.manager')->purge('smtp');
    }
}
