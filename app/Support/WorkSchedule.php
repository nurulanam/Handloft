<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\CarbonInterface;

/**
 * The organisation's working week, from Settings → Work schedule: which weekdays are off, which day a
 * week starts on, and the daily hours target. Everything that talks about "this week", shades days
 * off, or compares hours with a target goes through here. Days use Carbon's numbering: 0 = Sunday … 6 = Saturday.
 */
final class WorkSchedule
{
    public const DAY_NAMES = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    private static function settings(): AppSetting
    {
        return AppSetting::current();
    }

    /** @return list<int> */
    public static function offDays(): array
    {
        $days = self::settings()->off_days;

        return is_array($days) ? array_values(array_map('intval', $days)) : [0, 6];
    }

    public static function weekStartsOn(): int
    {
        $day = self::settings()->week_starts_on;

        return is_int($day) && $day >= 0 && $day <= 6 ? $day : CarbonInterface::MONDAY;
    }

    public static function weekEndsOn(): int
    {
        return (self::weekStartsOn() + 6) % 7;
    }

    /** Hours a person is expected to work on a working day, or null when no target is set. */
    public static function dailyTarget(): ?float
    {
        $target = self::settings()->daily_hours_target;

        return $target !== null && (float) $target > 0 ? (float) $target : null;
    }

    public static function isOffDay(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeek, self::offDays(), true);
    }

    public static function startOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfWeek(self::weekStartsOn());
    }

    public static function endOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfWeek(self::weekEndsOn());
    }

    /** Working (non-off) days from $start to $end, inclusive. */
    public static function workingDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $off = self::offDays();
        $count = 0;

        for ($day = $start->copy()->startOfDay(); $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeek, $off, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Short weekday labels in week order, starting from the configured first day.
     *
     * @return list<array{day: int, short: string, off: bool}>
     */
    public static function weekdays(): array
    {
        $off = self::offDays();

        return array_map(function (int $offset) use ($off) {
            $day = (self::weekStartsOn() + $offset) % 7;

            return ['day' => $day, 'short' => substr(self::DAY_NAMES[$day], 0, 3), 'off' => in_array($day, $off, true)];
        }, range(0, 6));
    }
}
