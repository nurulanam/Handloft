<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Notifications\Channels\SafeBroadcastChannel;
use Illuminate\Support\Facades\Schema;

/**
 * Which channels a notification goes out on (Settings → Notifications):
 *
 * - In-app (on by default): the database record behind the bell and the notifications page, plus
 *   the live push to open browsers when live notifications are on.
 * - Email (on by default): the notification email.
 *
 * The two are independent; with both off a notification isn't sent at all. Emails people ask for
 * directly (password reset, the test emails in Settings) aren't notifications and always go out.
 */
final class NotificationChannels
{
    private static ?AppSetting $settings = null;

    private static bool $loaded = false;

    private static function settings(): ?AppSetting
    {
        if (! self::$loaded) {
            self::$loaded = true;

            try {
                self::$settings = Schema::hasTable('app_settings') ? AppSetting::query()->first() : null;
            } catch (\Throwable) {
                self::$settings = null;
            }
        }

        return self::$settings;
    }

    public static function reset(): void
    {
        self::$settings = null;
        self::$loaded = false;
    }

    public static function inApp(): bool
    {
        return self::settings()?->app_notifications_enabled ?? true;
    }

    public static function email(): bool
    {
        return self::settings()?->mail_notifications_enabled ?? true;
    }

    /**
     * @return list<string>
     */
    public static function for(object $notifiable): array
    {
        return array_values(array_filter([
            self::inApp() ? 'database' : null,
            self::inApp() && LiveUpdates::enabled() ? SafeBroadcastChannel::class : null,
            self::email() ? 'mail' : null,
        ]));
    }
}
