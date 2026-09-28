<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * Tailwind text-color class for the priority flag icon. Written as full
     * literal class strings so Tailwind's build-time scanner picks them up.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Low => 'text-zinc-400',
            self::Medium => 'text-amber-500',
            self::High => 'text-orange-500',
            self::Urgent => 'text-red-600',
        };
    }
}
