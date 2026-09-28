<?php

namespace App\Support;

class Avatar
{
    /**
     * Up to two initials from a person's name, e.g. "Shamim Rahman" -> "SR".
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $words = array_filter($words);

        if ($words === []) {
            return '?';
        }

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }

        return strtoupper(substr($words[0], 0, 1).substr(end($words), 0, 1));
    }
}
