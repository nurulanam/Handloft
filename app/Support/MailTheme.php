<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * The look of every notification email (Settings → Email Template): a layout, a brand colour (header
 * accent and button) and the button's text colour, plus an optional footer note.
 *
 * The mail layout (resources/views/vendor/mail/html/message.blade.php) reads current(); the Settings
 * page renders a live preview of unsaved choices through preview().
 */
final class MailTheme
{
    /** @var array<string, array{0: string, 1: string}> key => [name, description] */
    public const LAYOUTS = [
        'modern' => ['Modern', 'Logo header and a rounded card with a brand-coloured top edge.'],
        'classic' => ['Classic', 'The familiar centred card on a soft grey page.'],
        'minimal' => ['Minimal', 'Plain white and left-aligned, like a personal email.'],
    ];

    public const DEFAULT_LAYOUT = 'modern';

    /**
     * Ready-made colour pairs: the app themes' brand colours, each with the button text colour that
     * reads best on it.
     *
     * @var array<string, array{name: string, brand: string, text: string}>
     */
    public const PRESETS = [
        'forest' => ['name' => 'Forest', 'brand' => '#10512a', 'text' => '#ffffff'],
        'lime' => ['name' => 'Lime', 'brand' => '#bfef1e', 'text' => '#10512a'],
        'ocean' => ['name' => 'Ocean', 'brand' => '#0b4f6c', 'text' => '#ffffff'],
        'indigo' => ['name' => 'Indigo', 'brand' => '#3730a3', 'text' => '#ffffff'],
        'plum' => ['name' => 'Plum', 'brand' => '#6b21a8', 'text' => '#ffffff'],
        'ember' => ['name' => 'Ember', 'brand' => '#9a3412', 'text' => '#ffffff'],
        'graphite' => ['name' => 'Graphite', 'brand' => '#27272a', 'text' => '#ffffff'],
    ];

    /** @var array{layout: string, brand: string, text: string, footer: ?string}|null */
    private static ?array $override = null;

    /**
     * @return array{layout: string, brand: string, text: string, footer: ?string}
     */
    public static function current(): array
    {
        if (self::$override) {
            return self::$override;
        }

        $settings = AppSetting::current();

        return [
            'layout' => array_key_exists((string) $settings->mail_layout, self::LAYOUTS) ? $settings->mail_layout : self::DEFAULT_LAYOUT,
            'brand' => $settings->mailBrandColor(),
            'text' => $settings->mailButtonTextColor(),
            'footer' => $settings->mail_footer_note,
        ];
    }

    /**
     * Render something (e.g. a sample email) as if these settings were saved.
     *
     * @template T
     *
     * @param  array{layout: string, brand: string, text: string, footer: ?string}  $theme
     * @param  callable(): T  $render
     * @return T
     */
    public static function preview(array $theme, callable $render): mixed
    {
        self::$override = $theme;

        try {
            return $render();
        } finally {
            self::$override = null;
        }
    }

    /**
     * The button text colour that reads best on this background: white or near-black.
     */
    public static function suggestedText(string $background): string
    {
        return self::contrast($background, '#ffffff') >= self::contrast($background, '#18181b') ? '#ffffff' : '#18181b';
    }

    /**
     * WCAG contrast ratio between two #rrggbb colours (1 to 21; 4.5+ reads well for button text).
     */
    public static function contrast(string $a, string $b): float
    {
        [$light, $dark] = [self::luminance($a), self::luminance($b)];

        return round((max($light, $dark) + 0.05) / (min($light, $dark) + 0.05), 1);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return 0.0;
        }

        $channel = function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 0, 2)) + 0.7152 * $channel(substr($hex, 2, 2)) + 0.0722 * $channel(substr($hex, 4, 2));
    }
}
