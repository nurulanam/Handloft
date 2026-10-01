<?php

namespace App\Support;

/**
 * Converts between the decimal-hours value time logs are stored as (e.g.
 * TaskTimeLog::hours) and the hours+minutes split used in log-time forms and
 * human-readable displays.
 */
class Duration
{
    public static function forHumans(float $hours): string
    {
        [$wholeHours, $minutes] = self::toParts($hours);

        return match (true) {
            $wholeHours > 0 && $minutes > 0 => "{$wholeHours}h {$minutes}m",
            $wholeHours > 0 => "{$wholeHours}h",
            default => "{$minutes}m",
        };
    }

    /**
     * @return array{0: int, 1: int} [hours, minutes]
     */
    public static function toParts(float $hours): array
    {
        $totalMinutes = (int) round($hours * 60);

        return [intdiv($totalMinutes, 60), $totalMinutes % 60];
    }

    public static function fromParts(int $hours, int $minutes): float
    {
        return round($hours + ($minutes / 60), 2);
    }
}
