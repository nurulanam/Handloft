<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    /**
     * Tailwind classes for the status pill. Written as full literal class
     * strings (not interpolated) so Tailwind's build-time scanner picks them up.
     */
    public function pillClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-sky-100 text-sky-700',
            self::Completed => 'bg-brand/10 text-brand',
            self::Archived => 'bg-zinc-100 text-zinc-600',
        };
    }
}
