<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-id" content="{{ auth()->id() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/logo.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles

        {{-- Carves a notch into the sidebar's right edge around the collapse
             toggle, with rounded (filleted) shoulders so the edge flows into
             the notch instead of meeting it at a sharp corner. The cut shape
             is an SVG (notch r=22 around the 18px-radius button, leaving a
             4px gap ring; fillets r=10), subtracted from a solid layer.
             Its 31px vertical center sits on the button's center (42px from
             the bottom). Desktop-only, matching where the toggle is shown. --}}
        <style>
            @media (min-width: 1024px) {
                .sidebar-notch {
                    --notch: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 62'%3E%3Cpath d='M24 0.603A10 10 0 0 1 17.125 10.102A22 22 0 0 0 17.125 51.898A10 10 0 0 1 24 61.397Z' fill='black'/%3E%3C/svg%3E");
                    mask-image: linear-gradient(#000 0 0), var(--notch);
                    mask-size: 100% 100%, 24px 62px;
                    mask-position: 0 0, right 0 bottom 11px;
                    mask-repeat: no-repeat;
                    mask-composite: exclude;
                    -webkit-mask-image: linear-gradient(#000 0 0), var(--notch);
                    -webkit-mask-size: 100% 100%, 24px 62px;
                    -webkit-mask-position: 0 0, right 0 bottom 11px;
                    -webkit-mask-repeat: no-repeat;
                    -webkit-mask-composite: xor;
                }
            }

            /* Post-login loading animation: a line of rounded squares that
               leapfrog over each other left to right (squash-and-stretch +
               flip), each picking up the brand gradient as it lands. Adapted
               from https://sm-amzad-hossain.github.io/Jampe-Slider-Loading/,
               recolored to the app's brand green → lime instead of blue. */
            .jampe-loader {
                --jampe-duration: 1.4s;
                --jampe-container: 140px;
                --jampe-box: 20px;
                width: var(--jampe-container);
                height: var(--jampe-box);
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
            }

            .jampe-box {
                width: var(--jampe-box);
                height: var(--jampe-box);
                position: relative;
                display: block;
                transform-origin: -50% center;
                border-radius: 30%;
            }

            .jampe-box::after {
                content: '';
                width: 100%;
                height: 100%;
                position: absolute;
                top: 0;
                right: 0;
                border-radius: 30%;
            }

            .jampe-box:nth-child(1) { animation: jampe-slide var(--jampe-duration) ease-in-out infinite alternate; }
            .jampe-box:nth-child(1)::after { animation: jampe-color var(--jampe-duration) ease-in-out infinite alternate; }

            .jampe-box:nth-child(2) { animation: jampe-flip-1 var(--jampe-duration) ease-in-out infinite alternate; }
            .jampe-box:nth-child(2)::after { animation: jampe-squidge-1 var(--jampe-duration) ease-in-out infinite alternate; background-color: #1a6b35; }

            .jampe-box:nth-child(3) { animation: jampe-flip-2 var(--jampe-duration) ease-in-out infinite alternate; }
            .jampe-box:nth-child(3)::after { animation: jampe-squidge-2 var(--jampe-duration) ease-in-out infinite alternate; background-color: #4c9a3f; }

            .jampe-box:nth-child(4) { animation: jampe-flip-3 var(--jampe-duration) ease-in-out infinite alternate; }
            .jampe-box:nth-child(4)::after { animation: jampe-squidge-3 var(--jampe-duration) ease-in-out infinite alternate; background-color: #85c230; }

            .jampe-box:nth-child(5) { animation: jampe-flip-4 var(--jampe-duration) ease-in-out infinite alternate; }
            .jampe-box:nth-child(5)::after { animation: jampe-squidge-4 var(--jampe-duration) ease-in-out infinite alternate; background-color: #bfef1e; }

            @keyframes jampe-slide {
                0% { background-color: #10512a; transform: translateX(0); }
                100% { background-color: #bfef1e; transform: translateX(calc(var(--jampe-container) - (var(--jampe-box) * 1.25))); }
            }

            @keyframes jampe-color {
                0% { background-color: #10512a; }
                100% { background-color: #bfef1e; }
            }

            @keyframes jampe-flip-1 { 0%, 15% { transform: rotate(0); } 35%, 100% { transform: rotate(-180deg); } }
            @keyframes jampe-flip-2 { 0%, 30% { transform: rotate(0); } 50%, 100% { transform: rotate(-180deg); } }
            @keyframes jampe-flip-3 { 0%, 45% { transform: rotate(0); } 65%, 100% { transform: rotate(-180deg); } }
            @keyframes jampe-flip-4 { 0%, 60% { transform: rotate(0); } 80%, 100% { transform: rotate(-180deg); } }

            @keyframes jampe-squidge-1 {
                5% { transform-origin: center bottom; transform: scaleX(1) scaleY(1); }
                15% { transform-origin: center bottom; transform: scaleX(1.3) scaleY(0.7); }
                20%, 25% { transform-origin: center bottom; transform: scaleX(0.8) scaleY(1.4); }
                40% { transform-origin: center top; transform: scaleX(1.3) scaleY(0.7); }
                55%, 100% { transform-origin: center top; transform: scaleX(1) scaleY(1); }
            }

            @keyframes jampe-squidge-2 {
                20% { transform-origin: center bottom; transform: scaleX(1) scaleY(1); }
                30% { transform-origin: center bottom; transform: scaleX(1.3) scaleY(0.7); }
                35%, 40% { transform-origin: center bottom; transform: scaleX(0.8) scaleY(1.4); }
                55% { transform-origin: center top; transform: scaleX(1.3) scaleY(0.7); }
                70%, 100% { transform-origin: center top; transform: scaleX(1) scaleY(1); }
            }

            @keyframes jampe-squidge-3 {
                35% { transform-origin: center bottom; transform: scaleX(1) scaleY(1); }
                45% { transform-origin: center bottom; transform: scaleX(1.3) scaleY(0.7); }
                50%, 55% { transform-origin: center bottom; transform: scaleX(0.8) scaleY(1.4); }
                70% { transform-origin: center top; transform: scaleX(1.3) scaleY(0.7); }
                85%, 100% { transform-origin: center top; transform: scaleX(1) scaleY(1); }
            }

            @keyframes jampe-squidge-4 {
                50% { transform-origin: center bottom; transform: scaleX(1) scaleY(1); }
                60% { transform-origin: center bottom; transform: scaleX(1.3) scaleY(0.7); }
                65%, 70% { transform-origin: center bottom; transform: scaleX(0.8) scaleY(1.4); }
                85% { transform-origin: center top; transform: scaleX(1.3) scaleY(0.7); }
                100% { transform-origin: center top; transform: scaleX(1) scaleY(1); }
            }

            /* Post-login loading animation: five equalizer-style bars
               pulsing height on a staggered delay. Adapted from
               https://sm-amzad-hossain.github.io/Loading-Animation/,
               recolored to the app's brand green → lime instead of the
               original red-to-green gradient. */
            .bars-loader {
                --bars-duration: 0.9s;
                --bars-gap: 6px;
                --bars-height: 50px;
                display: flex;
                justify-content: center;
                align-items: center;
                height: var(--bars-height);
                gap: var(--bars-gap);
            }

            .bars-loader span {
                width: 6px;
                height: 100%;
                border-radius: 2px;
                display: block;
                animation: bars-scale var(--bars-duration) ease-in-out infinite;
            }

            .bars-loader span:nth-child(1) { background: #10512a; animation-delay: 0s; }
            .bars-loader span:nth-child(2) { background: #1a6b35; animation-delay: -0.8s; }
            .bars-loader span:nth-child(3) { background: #4c9a3f; animation-delay: -0.7s; }
            .bars-loader span:nth-child(4) { background: #85c230; animation-delay: -0.6s; }
            .bars-loader span:nth-child(5) { background: #bfef1e; animation-delay: -0.5s; }

            @keyframes bars-scale {
                0%, 40%, 100% { transform: scaleY(0.09); }
                20% { transform: scaleY(1); }
            }

            /* Mobile shortcut bar: the same curved-notch trick as
               .sidebar-notch, but the bite sits on the bar's top edge and
               slides. --notch-x (set by Alpine to the active item's center,
               in px) drives both the bite and the floating circle above it.
               Bite: r=30 around a 24px-radius circle (6px gap ring), with
               r=10 fillets where it meets the edge. preserveAspectRatio=none
               lets the bite flatten to nothing (mask height 0) when no
               shortcut page is active, so it closes smoothly instead of
               popping. Only the background layer is masked — a mask clips
               every descendant, and the circle and risen icon sit above the
               bar's edge. These rules are only the resting state: the motion
               between states is run from Alpine with the Web Animations API,
               because wire:navigate moves the persisted bar to the new page
               mid-tap, which cancels CSS transitions but not script ones. */
            .notch-bar {
                --notch: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 80 32' preserveAspectRatio='none'%3E%3Cpath d='M1.27 0A10 10 0 0 1 10.95 7.5A30 30 0 0 0 69.05 7.5A10 10 0 0 1 78.73 0Z' fill='black'/%3E%3C/svg%3E");
                mask-image: linear-gradient(#000 0 0), var(--notch);
                mask-size: 100% 100%, 80px 0;
                mask-position: 0 0, calc(var(--notch-x) - 40px) 0;
                mask-repeat: no-repeat;
                mask-composite: exclude;
                -webkit-mask-image: linear-gradient(#000 0 0), var(--notch);
                -webkit-mask-size: 100% 100%, 80px 0;
                -webkit-mask-position: 0 0, calc(var(--notch-x) - 40px) 0;
                -webkit-mask-repeat: no-repeat;
                -webkit-mask-composite: xor;
            }

            /* Glass needs something behind it to frost, and the page under the bar is mostly flat white —
               two faint brand tints (.notch-backdrop) drift slowly *behind* the bar, and the bar itself is
               frosted glass on top. Both layers carry the notch mask, and nothing else is drawn there, so the
               cut-out shows the page straight through. */
            .notch-backdrop span {
                position: absolute;
                border-radius: 9999px;
                filter: blur(16px);
                animation: notch-drift var(--drift, 14s) ease-in-out infinite alternate;
            }

            @keyframes notch-drift {
                from { transform: translate(0, 0) scale(1); }
                to { transform: translate(var(--dx, 40px), var(--dy, -8px)) scale(1.15); }
            }

            @media (prefers-reduced-motion: reduce) {
                .notch-backdrop span { animation: none; }
            }

            .notch-shade {
                filter: blur(5px);
                translate: 0 -2px;
            }

            .notch-glass {
                background-color: rgb(255 255 255 / 0.45);
                -webkit-backdrop-filter: blur(18px) saturate(1.6);
                backdrop-filter: blur(18px) saturate(1.6);
            }

            .notch-open .notch-bar {
                mask-size: 100% 100%, 80px 32px;
                -webkit-mask-size: 100% 100%, 80px 32px;
            }

            /* Resting position in `translate` and size in `scale`; the slide
               animates `transform` on top. CSS applies these as translate,
               then scale, then transform — so the circle scales in place and
               the slide offset rides along without fighting either. */
            .notch-circle {
                translate: calc(var(--notch-x) - 24px) -24px;
                scale: 0;
            }

            .notch-open .notch-circle {
                scale: 1;
            }

        </style>

        <style>
            /* Post-login loading animation: a cartoon hand with four fingers
               and a thumb "typing", each lifting and curling in sequence.
               Adapted from https://sm-amzad-hossain.github.io/Hand-Animation/,
               recolored (brand green fingers, white knuckle/nail details)
               instead of white fingers on a solid blue background, and
               switched from page-absolute centering to sitting inline in
               the overlay's own centered flex layout. */
            .hand-loader {
                position: relative;
                width: 112px;
                height: 70px;
                transform: scale(0.85);
            }

            .hand-loader::before,
            .hand-loader::after {
                display: table;
                content: '';
            }

            .hand-loader::after {
                clear: both;
            }

            .hand-finger {
                float: left;
                margin: 0 2px 0 0;
                width: 20px;
                height: 100%;
            }

            .hand-finger-1 { animation: hand-finger-1-animation 2s infinite ease-out; }
            .hand-finger-1 span { animation: hand-finger-1-animation-span 2s infinite ease-out; }
            .hand-finger-1 i { animation: hand-finger-1-animation-i 2s infinite ease-out; }

            .hand-finger-2 { animation: hand-finger-2-animation 2s infinite ease-out; }
            .hand-finger-2 span { animation: hand-finger-2-animation-span 2s infinite ease-out; }
            .hand-finger-2 i { animation: hand-finger-2-animation-i 2s infinite ease-out; }

            .hand-finger-3 { animation: hand-finger-3-animation 2s infinite ease-out; }
            .hand-finger-3 span { animation: hand-finger-3-animation-span 2s infinite ease-out; }
            .hand-finger-3 i { animation: hand-finger-3-animation-i 2s infinite ease-out; }

            .hand-finger-4 { animation: hand-finger-4-animation 2s infinite ease-out; }
            .hand-finger-4 span { animation: hand-finger-4-animation-span 2s infinite ease-out; }
            .hand-finger-4 i { animation: hand-finger-4-animation-i 2s infinite ease-out; }

            .hand-finger-item {
                position: relative;
                width: 100%;
                height: 100%;
                border-radius: 6px 6px 8px 8px;
                background: #10512a;
            }

            .hand-finger-item span {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: auto;
                padding: 5px 5px 0 5px;
            }

            .hand-finger-item span::before,
            .hand-finger-item span::after {
                content: '';
                position: relative;
                display: block;
                margin: 0 0 2px 0;
                width: 100%;
                height: 2px;
                background: #ffffff;
            }

            .hand-finger-item i {
                position: absolute;
                left: 3px;
                bottom: 3px;
                width: 14px;
                height: 14px;
                border-radius: 10px 10px 7px 7px;
                background: #bfef1e;
            }

            .hand-last-finger {
                position: relative;
                float: left;
                width: 24px;
                height: 100%;
                overflow: hidden;
            }

            .hand-last-finger-item {
                position: absolute;
                right: 0;
                top: 32px;
                width: 110%;
                height: 20px;
                border-radius: 0 5px 14px 0;
                background: #10512a;
                animation: hand-finger-5-animation 2s infinite linear;
            }

            .hand-last-finger-item i {
                position: absolute;
                left: 0;
                top: -8px;
                width: 22px;
                height: 8px;
                background: #10512a;
                overflow: hidden;
            }

            .hand-last-finger-item i::after {
                content: '';
                position: absolute;
                left: 0;
                bottom: 0;
                width: 34px;
                height: 20px;
                border-radius: 0 0 15px 15px;
                /* Matches the overlay's own backdrop (not the hand's brand
                   color) so this reads as a notch carved out of the thumb,
                   revealing what's behind it — the actual illusion this
                   shape is for, same trick the original used against its
                   page background. */
                background: #ffffff;
            }

            @keyframes hand-finger-1-animation {
                0%, 20%, 41%, 100% { padding: 12px 0 5px 0; }
                29%, 35% { padding: 4px 0 24px 0; }
            }
            @keyframes hand-finger-1-animation-span {
                0%, 20%, 41%, 100% { top: 0; }
                29%, 35% { top: -7px; }
            }
            @keyframes hand-finger-1-animation-i {
                0%, 20%, 41%, 100% { bottom: 3px; height: 14px; border-radius: 10px 10px 7px 7px; }
                29%, 35% { bottom: 8px; height: 12px; border-radius: 7px 7px 4px 4px; }
            }

            @keyframes hand-finger-2-animation {
                0%, 24%, 45%, 100% { padding: 6px 0 2px 0; }
                33%, 39% { padding: 2px 0 16px 0; }
            }
            @keyframes hand-finger-2-animation-span {
                0%, 24%, 45%, 100% { top: 0; }
                33%, 39% { top: -7px; }
            }
            @keyframes hand-finger-2-animation-i {
                0%, 24%, 45%, 100% { bottom: 3px; height: 14px; border-radius: 10px 10px 7px 7px; }
                33%, 39% { bottom: 8px; height: 12px; border-radius: 7px 7px 4px 4px; }
            }

            @keyframes hand-finger-3-animation {
                0%, 28%, 49%, 100% { padding: 0 0 0 0; }
                37%, 43% { padding: 0 0 12px 0; }
            }
            @keyframes hand-finger-3-animation-span {
                0%, 28%, 49%, 100% { top: 0; }
                37%, 43% { top: -7px; }
            }
            @keyframes hand-finger-3-animation-i {
                0%, 28%, 49%, 100% { bottom: 3px; height: 14px; border-radius: 10px 10px 7px 7px; }
                37%, 43% { bottom: 8px; height: 12px; border-radius: 7px 7px 4px 4px; }
            }

            @keyframes hand-finger-4-animation {
                0%, 32%, 53%, 100% { padding: 8px 0 3px 0; }
                41%, 47% { padding: 4px 0 20px 0; }
            }
            @keyframes hand-finger-4-animation-span {
                0%, 32%, 53%, 100% { top: 0; }
                41%, 47% { top: -7px; }
            }
            @keyframes hand-finger-4-animation-i {
                0%, 32%, 53%, 100% { bottom: 3px; height: 14px; border-radius: 10px 10px 7px 7px; }
                41%, 47% { bottom: 8px; height: 12px; border-radius: 7px 7px 4px 4px; }
            }

            @keyframes hand-finger-5-animation {
                0%, 34%, 60%, 100% { top: 32px; right: 0; border-radius: 0 5px 14px 0; transform: rotate(0deg); }
                43%, 50% { top: 20px; right: 2px; border-radius: 0 8px 20px 0; transform: rotate(-12deg); }
            }
        </style>
    </head>
    <body
        class="min-h-screen bg-zinc-50 font-sans antialiased"
        x-data="{
            sidebarOpen: false,
            drag: null,
            dragStart(e) {
                if (! this.sidebarOpen || window.innerWidth >= 1024) return;
                this.drag = { x: e.touches[0].clientX, y: e.touches[0].clientY, dx: 0, on: null };
            },
            dragMove(e) {
                if (! this.drag) return;
                const dx = e.touches[0].clientX - this.drag.x, dy = e.touches[0].clientY - this.drag.y;
                if (this.drag.on === null && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) this.drag.on = Math.abs(dx) > Math.abs(dy);
                if (this.drag.on) this.drag.dx = Math.min(0, dx);
            },
            dragEnd() {
                if (this.drag?.on && this.drag.dx < -60) this.sidebarOpen = false;
                this.drag = null;
            },
            sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
            flyout: { show: false, top: 0, label: '' },
            toggleCollapsed() {
                this.sidebarCollapsed = ! this.sidebarCollapsed;
                this.flyout.show = false;
                localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
            },
            showFlyout(el, label) {
                if (! this.sidebarCollapsed || window.innerWidth < 1024) return;
                const rect = el.getBoundingClientRect();
                this.flyout = { show: true, top: rect.top + rect.height / 2, label };
            },
        }"
    >
        @if (session('just_logged_in'))
            @php $__loadingScreen = \App\Models\AppSetting::current(); @endphp
            {{-- A frosted-glass overlay shown once, right after login, over the
                 real dashboard underneath (rather than on the login page before
                 navigating) so it's actually visible faintly through the blur.
                 Fades itself out after the configured duration. Transparency
                 and blur are configurable from Settings, so they're applied as
                 inline styles rather than fixed Tailwind classes. --}}
            <div
                x-data="{ visible: true }"
                x-init="setTimeout(() => visible = false, {{ (int) session('loading_screen_seconds', 3) * 1000 }})"
                x-show="visible"
                x-transition:leave="transition ease-in-out duration-700"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-60 flex flex-col items-center justify-center gap-6"
                style="background-color: rgba(255, 255, 255, {{ $__loadingScreen->loading_screen_opacity / 100 }}); backdrop-filter: blur({{ $__loadingScreen->loading_screen_blur }}px); -webkit-backdrop-filter: blur({{ $__loadingScreen->loading_screen_blur }}px);"
            >
                <x-loading-animation :name="$__loadingScreen->loading_screen_style" />
                <div class="flex items-center gap-2.5 text-xl font-semibold text-zinc-900">
                    <x-logo-mark class="size-9 shrink-0" />
                    {{ config('app.name') }}
                </div>
            </div>
        @endif

        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            {{-- Phones: a frosted-glass drawer that slides in from the left, matching the notification
                 sheet's glass and easing, and follows the finger for swipe-to-close. Desktop keeps the dark rail.
                 The closed/expanded state is in the static classes so the first paint (before Alpine boots, on
                 every load and wire:navigate) is already right; Alpine only adds the open/collapsed overrides. --}}
            <aside
                class="sidebar-notch fixed inset-y-0 left-0 z-30 w-72 overflow-x-hidden overflow-y-auto rounded-r-3xl border-r border-white/60 bg-white/70 text-zinc-700 backdrop-blur-xl backdrop-saturate-150 -translate-x-full transition-[translate,width,box-shadow] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] lg:w-64 lg:translate-x-0 lg:rounded-none lg:border-0 lg:bg-zinc-900 lg:text-zinc-300 lg:shadow-none lg:backdrop-blur-none lg:backdrop-saturate-100 lg:ease-in-out"
                :class="[sidebarOpen && 'translate-x-0! shadow-2xl', sidebarCollapsed && 'lg:w-20!']"
                :style="drag?.on ? { transform: `translateX(${drag.dx}px)`, transition: 'none' } : {}"
                @scroll="flyout.show = false"
                @touchstart.passive="dragStart($event)"
                @touchmove.passive="dragMove($event)"
                @touchend="dragEnd()"
                @touchcancel="dragEnd()"
                @click="$event.target.closest('a[href]') && (sidebarOpen = false)"
            >
                <div class="flex h-16 items-center gap-2.5 px-6 text-lg font-semibold text-zinc-900 lg:text-white" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-0' : ''">
                    <x-logo-mark class="size-8 shrink-0" />
                    <span class="overflow-hidden whitespace-nowrap transition-all duration-200" :class="sidebarCollapsed ? 'lg:w-0 lg:opacity-0' : 'w-auto opacity-100'">{{ config('app.name') }}</span>
                    <button type="button" @click="sidebarOpen = false" class="-mr-3 ml-auto rounded-full p-2 text-zinc-500 hover:bg-white/60 active:bg-zinc-900/5 lg:hidden" title="Close menu">
                        <span class="sr-only">Close menu</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                    </button>
                </div>

                <nav class="space-y-1 px-3 pb-8">
                    @php
                        $chevron = 'M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z';

                        $navigation = [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
                            ['label' => 'For You', 'route' => 'for-you', 'icon' => 'for-you'],
                            ['label' => 'Starred', 'route' => 'starred', 'icon' => 'starred'],
                            ['label' => 'Team', 'route' => 'users.index', 'icon' => 'team', 'hidden' => auth()->user()->cannot('manage-users')],
                            ['label' => 'Projects', 'icon' => 'projects', 'children' => [
                                ['label' => 'All Projects', 'route' => 'projects.index'],
                                ['label' => 'Create Project', 'route' => 'projects.create', 'hidden' => auth()->user()->cannot('create', \App\Models\Project::class)],
                            ]],
                            ['label' => 'Tasks', 'icon' => 'tasks', 'children' => [
                                ['label' => 'All Tasks', 'route' => 'tasks.index'],
                                ['label' => 'Create Task', 'route' => 'tasks.create'],
                            ]],
                            ['label' => 'Work History', 'route' => 'work-history.index', 'icon' => 'work-history'],
                            ['label' => 'Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
                            ['label' => 'Reports', 'icon' => 'reports', 'disabled' => true],
                            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'notifications'],
                            ['label' => 'Settings', 'route' => 'settings', 'icon' => 'settings', 'hidden' => auth()->user()->cannot('manage-settings')],
                        ];
                    @endphp

                    @foreach ($navigation as $item)
                        @continue(! empty($item['hidden']))

                        @if (! empty($item['children']))
                            @php
                                $visibleChildren = collect($item['children'])->reject(fn ($child) => ! empty($child['hidden']))->values();
                                $childActive = $visibleChildren->contains(
                                    fn ($child) => request()->routeIs($child['route'], \Illuminate\Support\Str::before($child['route'], '.').'.*')
                                );
                            @endphp
                            <div
                                x-data="{ open: {{ $childActive ? 'true' : 'false' }}, height: '0px' }"
                                x-effect="height = open ? $refs.submenuPanel.scrollHeight + 'px' : '0px'"
                            >
                                <button
                                    type="button"
                                    @mouseenter="showFlyout($el, @js($item['label']))"
                                    @mouseleave="flyout.show = false"
                                    @click="sidebarCollapsed ? Livewire.navigate('{{ route($visibleChildren->first()['route']) }}') : (open = ! open)"
                                    class="flex w-full items-center gap-2.5 overflow-hidden rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors duration-200 ease-in-out {{ $childActive ? 'bg-brand text-white shadow-sm shadow-brand/20 lg:shadow-none' : 'text-zinc-600 hover:bg-zinc-900/5 hover:text-zinc-900 lg:text-zinc-400 lg:hover:bg-zinc-800 lg:hover:text-white' }}"
                                    :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                                >
                                    <x-nav-icon :name="$item['icon']" class="size-4.5 shrink-0" />
                                    <span class="flex-1 whitespace-nowrap text-left transition-all duration-200" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                        class="size-3.5 shrink-0 opacity-60 transition-transform duration-300 ease-in-out"
                                        :class="[open ? 'rotate-90' : '', sidebarCollapsed ? 'lg:hidden' : '']"
                                    ><path fill-rule="evenodd" d="{{ $chevron }}" clip-rule="evenodd" /></svg>
                                </button>

                                {{-- Animates the exact measured pixel height of the panel (via
                                     scrollHeight) rather than relying on the Alpine collapse
                                     plugin, which isn't actually bundled with Livewire's Alpine
                                     build — x-collapse there is silently a no-op. This works in
                                     every browser with no plugin and no "jumps early" artifact
                                     that a generous max-height guess would cause. --}}
                                <div
                                    class="overflow-hidden transition-[height] duration-300 ease-in-out"
                                    :style="{ height: height }"
                                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                                >
                                    <div x-ref="submenuPanel" class="ml-4 mt-1 space-y-0.5 border-l border-zinc-900/10 pl-4 lg:border-zinc-800">
                                        @foreach ($visibleChildren as $child)
                                            <a
                                                href="{{ route($child['route']) }}"
                                                wire:navigate
                                                class="block rounded-lg px-3 py-1.5 text-sm transition {{ request()->routeIs($child['route']) ? 'font-semibold text-brand lg:font-medium lg:text-white' : 'text-zinc-500 hover:text-zinc-900 lg:text-zinc-400 lg:hover:text-white' }}"
                                            >
                                                {{ $child['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @elseif (! empty($item['disabled']))
                            <span
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-zinc-400 lg:text-zinc-500"
                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                                @mouseenter="showFlyout($el, @js($item['label'].' · Soon'))"
                                @mouseleave="flyout.show = false"
                            >
                                <span class="flex items-center gap-2.5 overflow-hidden">
                                    <x-nav-icon :name="$item['icon']" class="size-4.5 shrink-0" />
                                    <span class="whitespace-nowrap transition-all duration-200" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                                </span>
                                <span class="rounded bg-zinc-900/5 px-1.5 py-0.5 text-[10px] lg:bg-zinc-800 uppercase tracking-wide" :class="sidebarCollapsed ? 'lg:hidden' : ''">Soon</span>
                            </span>
                        @else
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @mouseenter="showFlyout($el, @js($item['label']))"
                                @mouseleave="flyout.show = false"
                                class="flex items-center gap-2.5 overflow-hidden rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200 ease-in-out {{ request()->routeIs($item['route'], \Illuminate\Support\Str::before($item['route'], '.').'.*') ? 'bg-brand text-white shadow-sm shadow-brand/20 lg:shadow-none' : 'text-zinc-600 hover:bg-zinc-900/5 hover:text-zinc-900 lg:text-zinc-400 lg:hover:bg-zinc-800 lg:hover:text-white' }}"
                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                            >
                                <x-nav-icon :name="$item['icon']" class="size-4.5 shrink-0" />
                                <span class="whitespace-nowrap transition-all duration-200" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </nav>
            </aside>

            {{-- Collapse toggle: a dark circular handle seated in the
                 sidebar's notch (see .sidebar-notch), sliding along with the
                 edge as it collapses/expands. --}}
            <button
                type="button"
                @click="toggleCollapsed()"
                class="fixed bottom-6 z-40 hidden size-9 -translate-x-1/2 items-center justify-center rounded-full bg-zinc-900 text-zinc-300 transition-[left,background-color,color] duration-300 ease-in-out hover:bg-brand hover:text-white lg:flex"
                :style="{ left: (sidebarCollapsed ? 80 : 256) + 'px' }"
                title="Toggle sidebar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M7.79 5.23a.75.75 0 01-.02 1.06L3.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Collapsed-sidebar label: a tab that grows out of the sidebar
                 edge beside the hovered nav item. Lives outside the <aside>
                 because the aside clips its overflow (and its notch mask
                 would clip it too). The two 10px shoulder pieces are filled
                 everywhere except a quarter circle, giving the concave curve
                 where the tab meets the edge. --}}
            <div
                x-show="flyout.show"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-none fixed left-20 z-40 hidden -translate-y-1/2 lg:block"
                :style="{ top: flyout.top + 'px' }"
            >
                <div class="relative">
                    <span class="absolute bottom-full left-0 size-2.5" style="background: radial-gradient(circle at 100% 0, transparent 10px, #18181b 10.5px);"></span>
                    <span class="block whitespace-nowrap rounded-r-lg bg-zinc-900 py-2 pl-3 pr-4 text-sm font-medium text-white" x-text="flyout.label"></span>
                    <span class="absolute left-0 top-full size-2.5" style="background: radial-gradient(circle at 100% 100%, transparent 10px, #18181b 10.5px);"></span>
                </div>
            </div>

            {{-- Mobile shortcut bar. The active icon rises into a floating
                 circle with a curved notch under it (see .notch-bar); tapping
                 another icon slides the notch and circle over and swaps which
                 icon is raised. State lives in Alpine and is matched against
                 the URL, so the bar is persisted across wire:navigate — the
                 slide finishes instead of the bar being redrawn mid-move.
                 Placed before the drawer overlay (same z-index) so an open
                 drawer dims it like the rest of the page. --}}
            {{-- Shortcuts are the pages people reach for on a phone: home,
                 what's waiting on them, all tasks, and the short list they're
                 tracking. Calendar stays in the drawer — its month grid doesn't
                 fit a phone, and it's an occasional glance, not a daily one. --}}
            @php
                $mobileNav = collect([
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
                    ['label' => 'For You', 'route' => 'for-you', 'icon' => 'for-you'],
                    ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'tasks'],
                    ['label' => 'Starred', 'route' => 'starred', 'icon' => 'starred'],
                ])->map(fn ($item) => $item + ['path' => parse_url(route($item['route']), PHP_URL_PATH)]);
            @endphp
            @persist('mobile-shortcuts')
                <nav
                    x-data="{
                        paths: @js($mobileNav->pluck('path')),
                        active: -1,
                        x: -100,
                        matchIndex() {
                            const path = location.pathname;
                            return this.paths.findIndex(p => path === p || path.startsWith(p + '/'));
                        },
                        centerOf(index) {
                            const item = this.$refs.items.children[index];
                            return item.offsetLeft + item.offsetWidth / 2;
                        },
                        {{-- Where the circle is on screen right now, including any slide in flight, so a tap mid-animation continues from there instead of jumping. --}}
                        currentX() {
                            if (this.active < 0) return this.x;
                            const circle = this.$refs.circle.getBoundingClientRect();
                            return circle.left - this.$root.getBoundingClientRect().left + circle.width / 2;
                        },
                        {{-- Animations start before the state changes, in the same task, so the browser never paints a frame of the new resting state before the motion begins (that flash was a visible jitter on tap). --}}
                        go(index) {
                            if (index === this.active) return;
                            const prev = this.active;
                            const fromX = this.currentX();
                            const toX = index >= 0 ? this.centerOf(index) : this.x;
                            this.animate(prev, index, fromX, toX);
                            this.active = index;
                            this.x = toX;
                        },
                        animate(prev, next, fromX, toX) {
                            const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
                            const move = { duration: reduce ? 0 : 450, easing: 'cubic-bezier(0.65, 0, 0.35, 1)' };
                            const pop = { ...move, easing: 'cubic-bezier(0.34, 1.45, 0.64, 1)' };
                            const { bar, backdrop, shade, circle } = this.$refs;
                            const icons = this.$refs.items.querySelectorAll('svg');
                            const labels = this.$refs.items.querySelectorAll('[data-label]');
                            const maskAt = (x) => ({ maskPosition: `0 0, ${x - 40}px 0`, webkitMaskPosition: `0 0, ${x - 40}px 0` });
                            const maskDepth = (h) => ({ maskSize: `100% 100%, 80px ${h}px`, webkitMaskSize: `100% 100%, 80px ${h}px` });

                            [bar, shade, circle, ...icons, ...labels].forEach(el => el.getAnimations().forEach(a => a.cancel()));
                            backdrop.getAnimations({ subtree: false }).forEach(a => a.cancel());

                            if (prev >= 0 && next >= 0) {
                                circle.animate([{ transform: `translateX(${fromX - toX}px)` }, { transform: 'none' }], move);
                                [bar, backdrop, shade].forEach(el => el.animate([maskAt(fromX), maskAt(toX)], move));
                            } else if (next >= 0) {
                                circle.animate([{ scale: 0 }, { scale: 1 }], pop);
                                [bar, backdrop, shade].forEach(el => el.animate([maskDepth(0), maskDepth(32)], move));
                            } else if (prev >= 0) {
                                circle.animate([{ scale: 1 }, { scale: 0 }], move);
                                [bar, backdrop, shade].forEach(el => el.animate([maskDepth(32), maskDepth(0)], move));
                            }

                            if (prev >= 0) {
                                icons[prev].animate([{ translate: '0 -1.5rem' }, { translate: '0 0' }], move);
                            }
                            if (next >= 0) {
                                {{-- The icon waits for the circle to arrive, then pops up into it; rising at once would leave it floating above the bar on its own. --}}
                                icons[next].animate([{ translate: '0 0' }, { translate: '0 -1.5rem' }], { ...pop, duration: move.duration * 0.65, delay: move.duration * 0.5, fill: 'backwards' });
                                labels[next].animate([{ scale: 1 }, { scale: 1.08 }, { scale: 1 }], { duration: move.duration * 0.6, delay: move.duration * 0.55, easing: 'ease-out' });
                            }
                        },
                    }"
                    x-init="
                        active = matchIndex();
                        if (active >= 0) x = centerOf(active);
                        document.addEventListener('livewire:navigated', () => go(matchIndex()));
                    "
                    @resize.window="if (active >= 0) x = centerOf(active)"
                    :class="{ 'notch-open': active >= 0 }"
                    :style="{ '--notch-x': x + 'px' }"
                    class="fixed inset-x-0 bottom-0 z-20 lg:hidden"
                    style="--notch-x: -100px; height: calc(4rem + env(safe-area-inset-bottom));"
                    aria-label="Shortcuts"
                >
                    {{-- A blurred, slightly raised copy of the bar's shape behind the glass: a soft shadow that traces the
                         top edge and the notch curve, and deepens the glass a touch so the cut-out reads clearly. The blur
                         sits on the wrapper so it softens the already-notched shape (a filter on the masked layer itself
                         would be cut off by its own mask). --}}
                    <div class="notch-shade pointer-events-none absolute inset-0" aria-hidden="true">
                        <div x-ref="shade" class="notch-bar absolute inset-0 bg-zinc-900/15"></div>
                    </div>
                    <div x-ref="backdrop" class="notch-bar notch-backdrop pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                        <span class="-left-10 -top-6 h-28 w-56 bg-brand-lime/30" style="--drift: 16s; --dx: 70px; --dy: 6px;"></span>
                        <span class="-right-10 -top-2 h-28 w-56 bg-brand/15" style="--drift: 20s; --dx: -70px; --dy: -6px;"></span>
                    </div>
                    <div x-ref="bar" class="notch-bar notch-glass absolute inset-0"></div>
                    <div x-ref="circle" class="notch-circle absolute left-0 top-0 size-12 rounded-full bg-brand"></div>

                    <div x-ref="items" class="relative flex h-16">
                        @foreach ($mobileNav as $index => $item)
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @click="go({{ $index }})"
                                class="relative flex flex-1 flex-col items-center pt-3"
                                :aria-current="active === {{ $index }} ? 'page' : null"
                            >
                                {{-- Icon centers sit 24px down, so the active one rises 1.5rem to land in the circle; labels share one baseline below the notch and stay put. --}}
                                <x-nav-icon
                                    :name="$item['icon']"
                                    class="size-6 transition-colors duration-300"
                                    x-bind:class="active === {{ $index }} ? '-translate-y-6 text-white' : 'text-zinc-700'"
                                />
                                <span
                                    data-label
                                    class="mt-1 text-[11px] leading-3.5 transition-colors duration-300"
                                    :class="active === {{ $index }} ? 'font-semibold text-brand' : 'font-medium text-zinc-700'"
                                >{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endpersist

            <div
                x-show="sidebarOpen"
                x-cloak
                x-transition:enter="transition-opacity duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity duration-300"
                x-transition:leave-end="opacity-0"
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-zinc-900/25 backdrop-blur-sm lg:hidden"
            ></div>

            {{-- Main column --}}
            <div class="flex min-w-0 flex-1 flex-col" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'">
                <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-zinc-200 bg-white px-4 sm:px-6">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 lg:hidden"
                        @click="sidebarOpen = ! sidebarOpen"
                    >
                        <span class="sr-only">Toggle sidebar</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10zm0 5.25a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75z" clip-rule="evenodd" /></svg>
                    </button>

                    {{-- The sidebar (and its logo) is hidden below lg, so the brand lives here on smaller screens. --}}
                    <a href="{{ route('dashboard') }}" wire:navigate class="ml-1 flex items-center gap-2 lg:hidden">
                        <x-logo-mark class="size-7 shrink-0" />
                        <span class="text-base font-semibold text-zinc-900">{{ config('app.name') }}</span>
                    </a>

                    <div class="flex flex-1 items-center justify-end gap-2 sm:gap-3">
                        <livewire:notifications.bell />

                        @php $role = auth()->user()->getRoleNames()->first(); @endphp
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = ! open" class="flex items-center gap-2.5 rounded-full p-0.5 hover:bg-zinc-100 sm:rounded-lg sm:py-1 sm:pl-1 sm:pr-2" :aria-expanded="open">
                                <span class="flex size-8 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white">{{ \App\Support\Avatar::initials(auth()->user()->name) }}</span>
                                <span class="hidden text-left sm:block">
                                    <span class="block text-sm font-medium leading-tight text-zinc-900">{{ auth()->user()->name }}</span>
                                    <span class="block text-xs leading-tight text-zinc-500">{{ $role ? \Illuminate\Support\Str::headline($role) : 'No role' }}</span>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="hidden size-4 text-zinc-400 sm:block"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                @click.outside="open = false"
                                x-transition:enter="transition duration-200 ease-[cubic-bezier(0.32,0.72,0,1)]"
                                x-transition:enter-start="-translate-y-1 scale-95 opacity-0"
                                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                                x-transition:leave="transition duration-150 ease-in"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="-translate-y-1 scale-95 opacity-0"
                                class="absolute right-0 z-30 mt-2 w-64 origin-top-right overflow-hidden rounded-2xl border border-white/60 bg-white/70 p-1.5 shadow-2xl shadow-zinc-900/20 backdrop-blur-xl backdrop-saturate-150"
                            >
                                <div class="flex items-center gap-3 rounded-xl bg-white/85 px-3 py-2.5 shadow-sm">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white">{{ \App\Support\Avatar::initials(auth()->user()->name) }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-zinc-900">{{ auth()->user()->name }}</span>
                                        <span class="block truncate text-xs text-zinc-500">{{ auth()->user()->email }}</span>
                                    </span>
                                </div>
                                <button type="button" @click="open = false; $dispatch('confirm-logout')" class="mt-1.5 flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm font-medium text-zinc-700 transition-colors hover:bg-red-500/10 hover:text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                                    Log out
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 p-4 pb-24 sm:p-6 lg:pb-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Log-out confirmation (opened by dispatching `confirm-logout`). Phones: a frosted-glass sheet that
             slides up from the bottom and can be swiped down to dismiss. sm and up: a centered glass dialog. --}}
        <div
            x-data="{
                open: false,
                submitting: false,
                dragY: 0,
                startY: null,
                show() { this.dragY = 0; this.startY = null; this.submitting = false; this.open = true; this.$nextTick(() => this.$refs.cancel?.focus()); },
                dragStart(e) { this.startY = e.touches[0].clientY; },
                dragMove(e) { if (this.startY !== null) this.dragY = Math.max(0, e.touches[0].clientY - this.startY); },
                dragEnd() {
                    this.startY = null;
                    if (this.dragY > 80) { this.open = false; }
                    this.dragY = 0;
                },
            }"
            @confirm-logout.window="show()"
            @keydown.escape.window="open = false"
            x-effect="document.body.classList.toggle('overflow-hidden', open)"
        >
            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition-opacity duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity duration-300"
                x-transition:leave-end="opacity-0"
                @click="open = false"
                class="fixed inset-0 z-65 bg-zinc-900/30 backdrop-blur-sm"
            ></div>

            <div class="pointer-events-none fixed inset-x-0 bottom-0 z-65 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                    x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                    x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                    x-transition:leave="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] sm:duration-200"
                    x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                    x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                    :style="startY !== null ? `transform: translateY(${dragY}px); transition: none` : ''"
                    class="pointer-events-auto w-full rounded-t-[2rem] border-t border-white/60 bg-white/75 px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-2 shadow-2xl shadow-zinc-900/25 backdrop-blur-xl backdrop-saturate-150 sm:max-w-sm sm:rounded-3xl sm:border sm:p-6"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="logout-title"
                >
                    {{-- Drag handle: swipe down here (or on the header) to dismiss. --}}
                    <div class="flex justify-center pb-3 pt-1 sm:hidden" @touchstart.passive="dragStart($event)" @touchmove.passive="dragMove($event)" @touchend="dragEnd()">
                        <span class="h-1.5 w-10 rounded-full bg-zinc-900/20"></span>
                    </div>

                    <div class="text-center sm:text-left" @touchstart.passive="dragStart($event)" @touchmove.passive="dragMove($event)" @touchend="dragEnd()">
                        <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-500/10 text-red-600 sm:mx-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5.5"><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                        </span>
                        <h2 id="logout-title" class="mt-4 text-lg font-semibold text-zinc-900">Log out?</h2>
                        <p class="mt-1 text-sm text-zinc-500">You'll need to sign in again to get back to {{ config('app.name') }}.</p>
                    </div>

                    <div class="mt-4 flex items-center gap-3 rounded-2xl bg-white/80 px-3 py-2.5 shadow-sm">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white">{{ \App\Support\Avatar::initials(auth()->user()->name) }}</span>
                        <span class="min-w-0 text-left">
                            <span class="block truncate text-sm font-medium text-zinc-900">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs text-zinc-500">{{ auth()->user()->email }}</span>
                        </span>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" @submit="submitting = true" class="mt-5 flex flex-col gap-2.5 sm:flex-row-reverse">
                        @csrf
                        <button type="submit" :disabled="submitting" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-70 sm:py-2.5">
                            <span x-text="submitting ? 'Logging out…' : 'Log out'">Log out</span>
                        </button>
                        <button type="button" x-ref="cancel" @click="open = false" class="flex-1 rounded-xl bg-zinc-900/5 px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-900/10 sm:py-2.5">Cancel</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Toast notifications: any Livewire component can trigger one with
             $this->dispatch('notify', message: '...', type: 'success'|'error'|'info'|'star'|'unstar').
             Phones: a dark Dynamic Island pill that drops from the top and widens open, matching the live
             notification notch (tap it, or its close button, to dismiss). sm and up: a crisp card at the top
             right with a solid status icon, title, message and countdown bar; hovering pauses the countdown.
             Each plays its exit before removal; newest on top; at most three stack. --}}
        <div
            x-data="{
                toasts: [],
                titles: { success: 'Done', error: 'Something went wrong', info: 'Heads up', star: 'Added to Starred', unstar: 'Removed from Starred' },
                add(detail) {
                    this.toasts.unshift({ id: Date.now() + Math.random(), message: detail.message, type: detail.type ?? 'info', show: false, remaining: 4000, started: Date.now(), timer: null });
                    const toast = this.toasts[0];
                    this.$nextTick(() => { toast.show = true; });
                    toast.timer = setTimeout(() => this.dismiss(toast), toast.remaining);
                    this.toasts.filter((t) => t.show).slice(2).forEach((t) => this.dismiss(t));
                },
                {{-- Pause/resume only with a real pointer: a tap fires mouseenter with no mouseleave, which would freeze it. --}}
                pause(toast) {
                    if (! matchMedia('(hover: hover)').matches) return;
                    clearTimeout(toast.timer);
                    toast.remaining -= Date.now() - toast.started;
                },
                resume(toast) {
                    if (! matchMedia('(hover: hover)').matches || ! toast.show) return;
                    toast.started = Date.now();
                    toast.timer = setTimeout(() => this.dismiss(toast), Math.max(toast.remaining, 800));
                },
                dismiss(toast) {
                    clearTimeout(toast.timer);
                    toast.show = false;
                    setTimeout(() => { this.toasts = this.toasts.filter((t) => t.id !== toast.id); }, 250);
                },
            }"
            x-on:notify.window="add($event.detail)"
            class="pointer-events-none fixed inset-x-0 top-[max(0.625rem,env(safe-area-inset-top))] z-60 flex flex-col items-center gap-2 px-3 sm:inset-x-auto sm:right-5 sm:top-5 sm:items-end sm:gap-2.5 sm:px-0"
            aria-live="polite"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-show="toast.show"
                    x-transition:enter="transition duration-300 ease-[cubic-bezier(0.34,1.4,0.64,1)] sm:ease-[cubic-bezier(0.32,0.72,0,1)]"
                    x-transition:enter-start="-translate-y-4 scale-75 opacity-0 sm:translate-x-8 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="translate-x-0 translate-y-0 scale-100 opacity-100"
                    x-transition:leave="transition duration-250 ease-in"
                    x-transition:leave-start="scale-100 opacity-100"
                    x-transition:leave-end="-translate-y-3 scale-75 opacity-0 sm:translate-x-8 sm:translate-y-0 sm:scale-95"
                    @click="window.innerWidth < 640 && dismiss(toast)"
                    @mouseenter="pause(toast)"
                    @mouseleave="resume(toast)"
                    class="toast-island group pointer-events-auto relative flex w-[min(92vw,24rem)] items-center gap-3 overflow-hidden rounded-[1.75rem] bg-zinc-950 py-2.5 pl-2.5 pr-3 text-white shadow-2xl shadow-zinc-950/40 ring-1 ring-white/10 sm:w-[22rem] sm:rounded-2xl sm:bg-white/90 sm:py-3 sm:pl-3 sm:pr-2.5 sm:text-zinc-900 sm:shadow-[0_16px_40px_-12px_rgb(0_0_0/0.22),0_4px_10px_-4px_rgb(0_0_0/0.08)] sm:ring-zinc-900/[0.07] sm:backdrop-blur-xl"
                    role="status"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full sm:size-8"
                        :class="{
                            'bg-red-500/20 text-red-400 sm:bg-red-500 sm:text-white': toast.type === 'error',
                            'bg-brand-lime text-brand sm:bg-brand sm:text-white': toast.type === 'success',
                            'bg-amber-400 text-zinc-950 sm:text-white': toast.type === 'star',
                            'bg-white/15 text-white sm:bg-zinc-100 sm:text-zinc-500': toast.type === 'info' || toast.type === 'unstar',
                        }"
                    >
                        <template x-if="toast.type === 'error'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5 sm:size-4"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.63-1.516 2.63H3.72c-1.347 0-2.189-1.463-1.516-2.63L8.485 2.495ZM10 5.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5.5Zm0 8a1 1 0 100-2 1 1 0 000 2Z" clip-rule="evenodd" /></svg>
                        </template>
                        <template x-if="toast.type === 'success'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5 sm:size-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        </template>
                        <template x-if="toast.type === 'star'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5 sm:size-4"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" /></svg>
                        </template>
                        <template x-if="toast.type === 'unstar'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 sm:size-4"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>
                        </template>
                        <template x-if="toast.type === 'info'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5 sm:size-4"><path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" /></svg>
                        </template>
                    </span>
                    <span class="toast-island-body min-w-0 flex-1">
                        <span
                            class="block text-[11px] font-semibold uppercase tracking-wide sm:text-sm sm:normal-case sm:tracking-normal sm:text-zinc-900"
                            :class="{ 'text-red-400': toast.type === 'error', 'text-amber-400': toast.type === 'star', 'text-brand-lime': toast.type === 'success', 'text-white/60': toast.type === 'info' || toast.type === 'unstar' }"
                            x-text="titles[toast.type] ?? titles.info"
                        ></span>
                        <span class="line-clamp-2 block text-sm font-medium leading-snug text-white sm:text-[13px] sm:font-normal sm:text-zinc-500" x-text="toast.message"></span>
                    </span>
                    <button type="button" @click.stop="dismiss(toast)" class="-mr-1.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-white/70 hover:bg-white/20 hover:text-white sm:mr-0 sm:size-7 sm:bg-transparent sm:text-zinc-400 sm:opacity-0 sm:transition-opacity sm:hover:bg-zinc-100 sm:hover:text-zinc-700 sm:focus-visible:opacity-100 sm:group-hover:opacity-100" title="Dismiss">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                    </button>
                    <span
                        class="toast-timer absolute inset-x-0 bottom-0 hidden h-[3px] origin-left sm:block"
                        :class="{ 'bg-red-500': toast.type === 'error', 'bg-amber-400': toast.type === 'star', 'bg-brand': toast.type === 'success', 'bg-zinc-300': toast.type === 'info' || toast.type === 'unstar' }"
                    ></span>
                </div>
            </template>
        </div>

        @livewireScripts
    </body>
</html>
