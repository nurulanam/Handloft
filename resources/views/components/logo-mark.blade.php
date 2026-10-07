{{-- Handloft mark: a capital H as a handshake. Two pillars are two teammates; each reaches out half of
     the crossbar at a different height, the white hand passing to the lime one: work handed across, never
     dropped. Keep in sync with public/logo.svg (same drawing). Gradient ids are unique per instance,
     because a gradient referenced from a hidden copy (e.g. a logo that is display:none at this breakpoint)
     would otherwise fail to paint in the visible ones. Colours follow the theme (Settings → Appearance): the
     tile is a gradient around --color-brand and the second hand is --color-brand-lime. The hex attributes are
     the Forest colours, used where the CSS variables or relative colours aren't available. --}}
@php $id = 'hl'.\Illuminate\Support\Str::random(6); @endphp
<svg {{ $attributes->merge(['xmlns' => 'http://www.w3.org/2000/svg', 'viewBox' => '0 0 40 40', 'aria-hidden' => 'true']) }}>
    <defs>
        <linearGradient id="{{ $id }}-bg" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#1f8443" style="stop-color: oklch(from var(--color-brand, #10512a) calc(l + 0.13) calc(c * 1.4) h)"/><stop offset="1" stop-color="#0b3f20" style="stop-color: oklch(from var(--color-brand, #10512a) calc(l - 0.07) c h)"/></linearGradient>
        <radialGradient id="{{ $id }}-gloss" cx="9" cy="5" r="24" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fff" stop-opacity=".2"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
    </defs>
    <rect width="40" height="40" rx="11" fill="url(#{{ $id }}-bg)"/>
    <rect width="40" height="40" rx="11" fill="url(#{{ $id }}-gloss)"/>
    <rect x=".5" y=".5" width="39" height="39" rx="10.5" fill="none" stroke="#fff" stroke-opacity=".14"/>
    <rect x="8.5" y="8.5" width="7" height="23" rx="3.5" fill="#fff"/>
    <rect x="8.5" y="12.5" width="15" height="5.5" rx="2.75" fill="#fff"/>
    <rect x="24.5" y="8.5" width="7" height="23" rx="3.5" fill="#bfef1e" style="fill: var(--color-brand-lime, #bfef1e)"/>
    <rect x="16.5" y="22" width="15" height="5.5" rx="2.75" fill="#bfef1e" style="fill: var(--color-brand-lime, #bfef1e)"/>
</svg>
