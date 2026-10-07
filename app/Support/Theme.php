<?php

namespace App\Support;

/**
 * Ready-made colour themes (Settings → Look). Each sets the two brand colours the whole UI is built
 * on: "brand" (buttons, active states, headings) and "accent" (highlights, focus rings, badges).
 * Tailwind's bg-brand / text-brand-lime etc. read these as CSS variables, and the logo mark derives
 * its colours from them too.
 *
 * The look is chosen per person, per browser (kept in localStorage, see
 * resources/views/layouts/partials/appearance.blade.php), not stored on the server.
 */
final class Theme
{
    public const DEFAULT = 'forest';

    public const PRESETS = [
        'forest' => ['name' => 'Forest', 'brand' => '#10512a', 'accent' => '#bfef1e'],
        'ocean' => ['name' => 'Ocean', 'brand' => '#0b4f6c', 'accent' => '#5eead4'],
        'indigo' => ['name' => 'Indigo', 'brand' => '#3730a3', 'accent' => '#a5b4fc'],
        'plum' => ['name' => 'Plum', 'brand' => '#6b21a8', 'accent' => '#f0abfc'],
        'ember' => ['name' => 'Ember', 'brand' => '#9a3412', 'accent' => '#fdba74'],
        'graphite' => ['name' => 'Graphite', 'brand' => '#27272a', 'accent' => '#a3e635'],
    ];
}
