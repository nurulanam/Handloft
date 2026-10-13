<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * The work timer (auto time log), from Settings → Work schedule: whether people can start one, and
 * how long a forgotten timer may run before it's stopped for them.
 */
final class WorkTimer
{
    /** Hours a timer may be set to run at most, whatever the setting. */
    public const MAX_HOURS_LIMIT = 24;

    public static function enabled(): bool
    {
        return AppSetting::current()->work_timer_enabled ?? true;
    }

    /** Hours after which a running timer is stopped automatically, or null for no limit. */
    public static function maxHours(): ?int
    {
        $hours = AppSetting::current()->work_timer_max_hours;

        return $hours !== null && $hours > 0 ? min((int) $hours, self::MAX_HOURS_LIMIT) : null;
    }
}
