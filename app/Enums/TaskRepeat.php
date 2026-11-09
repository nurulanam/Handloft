<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

enum TaskRepeat: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function unit(): string
    {
        return match ($this) {
            self::Daily => 'day',
            self::Weekly => 'week',
            self::Monthly => 'month',
        };
    }

    /**
     * Validation rules for the repeat form fields (same names on the create and task pages).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'repeat' => ['nullable', Rule::enum(self::class)],
            'repeat_every' => ['nullable', 'integer', 'min:1', 'max:365'],
            'repeat_on' => ['nullable', 'array'],
            'repeat_on.*' => ['integer', 'between:0,6'],
            'repeat_day' => ['nullable', 'integer', 'between:1,31'],
            'repeat_until' => ['nullable', 'date', 'after:today'],
            'repeat_max' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * The task columns for validated form input; all empty when it doesn't repeat.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        $type = filled($data['repeat'] ?? null) ? self::from($data['repeat']) : null;

        return [
            'repeat' => $type,
            'repeat_every' => $type ? (int) ($data['repeat_every'] ?: 1) : 1,
            'repeat_on' => match ($type) {
                self::Weekly => collect($data['repeat_on'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all() ?: null,
                self::Monthly => filled($data['repeat_day'] ?? null) ? [(int) $data['repeat_day']] : null,
                default => null,
            },
            'repeat_until' => $type ? ($data['repeat_until'] ?: null) : null,
            'repeat_max' => $type ? ($data['repeat_max'] ?: null) : null,
        ];
    }

    /**
     * The first run after $from, counting from the day the task was finished.
     *
     * @param  array<int, int>|null  $on  weekdays (0=Sun…6=Sat) for weekly, [day of month] for monthly
     */
    public function nextAfter(CarbonInterface $from, int $every = 1, ?array $on = null): Carbon
    {
        $from = Carbon::instance($from)->startOfDay();

        if ($this === self::Daily) {
            return $from->addDays($every);
        }

        if ($this === self::Weekly) {
            if (! $on) {
                return $from->addWeeks($every);
            }

            // Days within a week run Monday → Sunday; later days of this week come first.
            $offsets = collect($on)->map(fn ($d) => ($d + 6) % 7)->sort()->values();
            $week = $from->copy()->startOfWeek();

            foreach ($offsets as $offset) {
                if ($week->copy()->addDays($offset)->gt($from)) {
                    return $week->addDays($offset);
                }
            }

            return $week->addWeeks($every)->addDays($offsets->first());
        }

        $day = $on[0] ?? $from->day;
        $thisMonth = $from->copy()->startOfMonth();
        $candidate = $thisMonth->copy()->day(min($day, $thisMonth->daysInMonth));

        if ($candidate->gt($from)) {
            return $candidate;
        }

        $next = $thisMonth->addMonthsNoOverflow($every);

        return $next->day(min($day, $next->daysInMonth));
    }
}
