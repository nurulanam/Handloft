{{-- One of the post-login loading animations (styles live in layouts/app; colours follow the theme).
     `size` scales it: lg is the real overlay, md the Settings preview, sm a Settings picker tile. --}}
@props(['name' => 'jampe', 'size' => 'lg'])

@php
    $jampe = ['sm' => '--jampe-container: 90px; --jampe-box: 14px;', 'md' => '--jampe-container: 110px; --jampe-box: 18px;', 'lg' => ''][$size];
    $bars = ['sm' => '--bars-height: 32px;', 'md' => '--bars-height: 44px;', 'lg' => ''][$size];
    $handoff = ['sm' => '--handoff-size: 36px;', 'md' => '--handoff-size: 48px;', 'lg' => ''][$size];
    $spinner = ['sm' => 'size-8', 'md' => 'size-12', 'lg' => 'size-16'][$size];
@endphp

@if ($name === 'spinner')
    <div class="{{ $spinner }} animate-spin rounded-full border-4 border-brand/20 border-t-brand-lime"></div>
@elseif ($name === 'bars')
    <div class="bars-loader" style="{{ $bars }}">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>
@elseif ($name === 'handoff')
    <div class="handoff-loader" style="{{ $handoff }}" role="img" aria-label="Loading">
        <span class="handoff-pillar"></span>
        <span class="handoff-bar"></span>
        <span class="handoff-pillar"></span>
    </div>
@else
    <div class="jampe-loader" style="{{ $jampe }}">
        <div class="jampe-box"></div>
        <div class="jampe-box"></div>
        <div class="jampe-box"></div>
        <div class="jampe-box"></div>
        <div class="jampe-box"></div>
    </div>
@endif
