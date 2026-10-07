<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * The time window a report covers: a day, week, month or year around an
 * anchor date, or a custom from–to range. Knows how to label itself, step to
 * the previous/next window, and split itself into chart buckets (days for
 * spans up to ~two months, months beyond that).
 */
final class ReportPeriod
{
    public const TYPES = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];

    /** Longest custom range accepted, to keep reports fast. */
    private const MAX_CUSTOM_DAYS = 366 * 5;

    private function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function fromInput(?string $type, ?string $date = null, ?string $from = null, ?string $to = null): self
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'monthly';

        if ($type === 'custom') {
            $start = self::parse($from) ?? CarbonImmutable::today()->startOfMonth();
            $end = self::parse($to) ?? CarbonImmutable::today();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end, $start];
            }

            if ($start->diffInDays($end) > self::MAX_CUSTOM_DAYS) {
                $start = $end->subDays(self::MAX_CUSTOM_DAYS);
            }

            return new self('custom', $start->startOfDay(), $end->endOfDay());
        }

        return self::around($type, self::parse($date) ?? CarbonImmutable::today());
    }

    private static function around(string $type, CarbonImmutable $anchor): self
    {
        return match ($type) {
            'daily' => new self($type, $anchor->startOfDay(), $anchor->endOfDay()),
            'weekly' => new self($type, $anchor->startOfWeek(), $anchor->endOfWeek()),
            'yearly' => new self($type, $anchor->startOfYear(), $anchor->endOfYear()),
            default => new self('monthly', $anchor->startOfMonth(), $anchor->endOfMonth()),
        };
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function previous(): self
    {
        return $this->type === 'custom'
            ? new self('custom', $this->start->subDays($this->days()), $this->end->subDays($this->days()))
            : self::around($this->type, $this->start->subDay());
    }

    public function next(): self
    {
        return $this->type === 'custom'
            ? new self('custom', $this->start->addDays($this->days()), $this->end->addDays($this->days()))
            : self::around($this->type, $this->end->addDay());
    }

    public function containsToday(): bool
    {
        return CarbonImmutable::now()->betweenIncluded($this->start, $this->end);
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end->startOfDay()) + 1;
    }

    public function label(): string
    {
        return match ($this->type) {
            'daily' => $this->start->format('l, d M Y'),
            'weekly', 'custom' => match (true) {
                $this->start->isSameMonth($this->end) => $this->start->format('d').' – '.$this->end->format('d M Y'),
                $this->start->isSameYear($this->end) => $this->start->format('d M').' – '.$this->end->format('d M Y'),
                default => $this->start->format('d M Y').' – '.$this->end->format('d M Y'),
            },
            'yearly' => $this->start->format('Y'),
            default => $this->start->format('F Y'),
        };
    }

    /** A filename-safe slug, e.g. "2026-10" or "2026-09-01_2026-10-06". */
    public function slug(): string
    {
        return match ($this->type) {
            'daily' => $this->start->format('Y-m-d'),
            'monthly' => $this->start->format('Y-m'),
            'yearly' => $this->start->format('Y'),
            default => $this->start->format('Y-m-d').'_'.$this->end->format('Y-m-d'),
        };
    }

    /**
     * The query-string parameters that reproduce this period.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return $this->type === 'custom'
            ? ['period' => 'custom', 'from' => $this->start->toDateString(), 'to' => $this->end->toDateString()]
            : ['period' => $this->type, 'date' => $this->start->toDateString()];
    }

    public function granularity(): string
    {
        return $this->days() <= 62 ? 'day' : 'month';
    }

    /**
     * SQL that turns a date/datetime column into this period's bucket key ("Y-m-d" or "Y-m").
     * date() and substr() behave the same in MySQL and SQLite.
     */
    public function bucketSql(string $column): string
    {
        return $this->granularity() === 'day' ? "date({$column})" : "substr(date({$column}), 1, 7)";
    }

    /**
     * @return list<array{key: string, label: string, long: string}>
     */
    public function buckets(): array
    {
        $buckets = [];

        if ($this->granularity() === 'day') {
            for ($day = $this->start; $day->lessThanOrEqualTo($this->end); $day = $day->addDay()) {
                $buckets[] = ['key' => $day->toDateString(), 'label' => $day->format($this->days() <= 7 ? 'D' : 'j'), 'long' => $day->format('D, d M Y')];
            }

            return $buckets;
        }

        for ($month = $this->start->startOfMonth(); $month->lessThanOrEqualTo($this->end); $month = $month->addMonth()) {
            $buckets[] = ['key' => $month->format('Y-m'), 'label' => $month->format($this->days() > 400 ? 'M y' : 'M'), 'long' => $month->format('F Y')];
        }

        return $buckets;
    }
}
