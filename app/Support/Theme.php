<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Ready-made colour themes (Settings → Appearance). Each sets the two brand colours the whole UI is
 * built on: "brand" (buttons, active states, headings) and "accent" (highlights, focus rings, badges).
 * Tailwind's bg-brand / text-brand-lime etc. read these CSS variables, so a theme is just two overrides.
 * The logo mark derives its colours from the same two variables.
 */
final class Theme
{
    public const PRESETS = [
        'forest' => ['name' => 'Forest', 'brand' => '#10512a', 'accent' => '#bfef1e'],
        'ocean' => ['name' => 'Ocean', 'brand' => '#0b4f6c', 'accent' => '#5eead4'],
        'indigo' => ['name' => 'Indigo', 'brand' => '#3730a3', 'accent' => '#a5b4fc'],
        'plum' => ['name' => 'Plum', 'brand' => '#6b21a8', 'accent' => '#f0abfc'],
        'ember' => ['name' => 'Ember', 'brand' => '#9a3412', 'accent' => '#fdba74'],
        'graphite' => ['name' => 'Graphite', 'brand' => '#27272a', 'accent' => '#a3e635'],
    ];

    public static function current(): array
    {
        return self::PRESETS[AppSetting::current()->theme] ?? self::PRESETS['forest'];
    }

    public static function isStatic(): bool
    {
        return AppSetting::current()->ui_style === 'static';
    }

    /**
     * Inline CSS for the app layout's <body>: the theme's base colours, which app.css turns into
     * --color-brand / --color-brand-lime (lifted for dark mode). The colours live on <body> rather than in a <head> <style>,
     * because wire:navigate swaps the body on every page change but keeps <head> styles it has already
     * seen, so a theme saved in Settings shows on the very next page without a full reload.
     */
    public static function bodyStyle(): string
    {
        $theme = self::current();

        return '--brand-base: '.$theme['brand'].'; --brand-accent: '.$theme['accent'].';';
    }

    /**
     * A <style> block overriding the brand colours, or '' for the default theme (already in the CSS build).
     */
    public static function styleTag(): string
    {
        $theme = self::current();

        if ($theme === self::PRESETS['forest']) {
            return '';
        }

        return '<style>:root{--brand-base:'.$theme['brand'].';--brand-accent:'.$theme['accent'].';--color-brand:'.$theme['brand'].';--color-brand-lime:'.$theme['accent'].';}</style>';
    }
}
