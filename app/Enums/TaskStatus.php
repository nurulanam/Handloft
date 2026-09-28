<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Tailwind classes for the status pill. Written as full literal class
     * strings (not interpolated) so Tailwind's build-time scanner picks them up.
     */
    public function pillClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-zinc-100 text-zinc-700',
            self::InProgress => 'bg-sky-100 text-sky-700',
            self::Completed => 'bg-brand/10 text-brand',
            self::Cancelled => 'bg-red-100 text-red-700',
        };
    }
}
