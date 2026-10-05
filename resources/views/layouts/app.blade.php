<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
                @if ($__loadingScreen->loading_screen_style === 'spinner')
                    <div class="size-16 animate-spin rounded-full border-4 border-brand/20 border-t-brand-lime"></div>
                @elseif ($__loadingScreen->loading_screen_style === 'bars')
                    <div class="bars-loader">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                @elseif ($__loadingScreen->loading_screen_style === 'hand')
                    <div class="hand-loader">
                        <div class="hand-finger hand-finger-1">
                            <div class="hand-finger-item">
                                <span></span>
                                <i></i>
                            </div>
                        </div>
                        <div class="hand-finger hand-finger-2">
                            <div class="hand-finger-item">
                                <span></span>
                                <i></i>
                            </div>
                        </div>
                        <div class="hand-finger hand-finger-3">
                            <div class="hand-finger-item">
                                <span></span>
                                <i></i>
                            </div>
                        </div>
                        <div class="hand-finger hand-finger-4">
                            <div class="hand-finger-item">
                                <span></span>
                                <i></i>
                            </div>
                        </div>
                        <div class="hand-last-finger">
                            <div class="hand-last-finger-item">
                                <i></i>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="jampe-loader">
                        <div class="jampe-box"></div>
                        <div class="jampe-box"></div>
                        <div class="jampe-box"></div>
                        <div class="jampe-box"></div>
                        <div class="jampe-box"></div>
                    </div>
                @endif
                <div class="flex items-center gap-2.5 text-xl font-semibold text-zinc-900">
                    <x-logo-mark class="size-9 shrink-0" />
                    {{ config('app.name') }}
                </div>
            </div>
        @endif

        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside
                class="sidebar-notch fixed inset-y-0 left-0 z-30 w-64 transform overflow-x-hidden overflow-y-auto bg-zinc-900 text-zinc-300 transition-[transform,width] duration-300 ease-in-out lg:translate-x-0"
                :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'lg:w-20' : 'lg:w-64']"
                @scroll="flyout.show = false"
            >
                <div class="flex h-16 items-center gap-2.5 px-6 text-lg font-semibold text-white" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-0' : ''">
                    <x-logo-mark class="size-8 shrink-0" />
                    <span class="overflow-hidden whitespace-nowrap transition-all duration-200" :class="sidebarCollapsed ? 'lg:w-0 lg:opacity-0' : 'w-auto opacity-100'">{{ config('app.name') }}</span>
                </div>

                <nav class="space-y-1 px-3 pb-8">
                    @php
                        $icons = [
                            'dashboard' => 'M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z',
                            'for-you' => 'M10.75 2a2.25 2.25 0 00-2.236 2H6.75A2.75 2.75 0 004 6.75v10.5A2.75 2.75 0 006.75 20h6.5A2.75 2.75 0 0016 17.25V6.75A2.75 2.75 0 0013.25 4h-1.764A2.25 2.25 0 0010.75 2zM6.5 10a.75.75 0 000 1.5h5a.75.75 0 000-1.5h-5zm0 3a.75.75 0 000 1.5h3a.75.75 0 000-1.5h-3z',
                            'star' => 'M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z',
                            'users' => 'M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z',
                            'folder' => 'M2 6a2 2 0 012-2h4.586a1 1 0 01.707.293l1.414 1.414a1 1 0 00.707.293H16a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z',
                            'clipboard' => 'M10.75 2a2.25 2.25 0 00-2.236 2H6.75A2.75 2.75 0 004 6.75v10.5A2.75 2.75 0 006.75 20h6.5A2.75 2.75 0 0016 17.25V6.75A2.75 2.75 0 0013.25 4h-1.764A2.25 2.25 0 0010.75 2zM8.5 12.75a.75.75 0 000 1.5h3a.75.75 0 000-1.5h-3zm-2.5.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm.75-3.75a.75.75 0 100-1.5.75.75 0 000 1.5zm2.5 0a.75.75 0 000-1.5h3a.75.75 0 000 1.5h-3z',
                            'clock' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z',
                            'calendar' => 'M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zM3.5 8.5v6.75c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25V8.5h-13z',
                            'chart' => 'M15.5 2A1.5 1.5 0 0014 3.5v13a1.5 1.5 0 001.5 1.5h1a1.5 1.5 0 001.5-1.5v-13A1.5 1.5 0 0016.5 2h-1zM9.5 6A1.5 1.5 0 008 7.5v9A1.5 1.5 0 009.5 18h1a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0010.5 6h-1zM3.5 10A1.5 1.5 0 002 11.5v5A1.5 1.5 0 003.5 18h1A1.5 1.5 0 006 16.5v-5A1.5 1.5 0 004.5 10h-1z',
                            'bell' => 'M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM8.5 16a1.5 1.5 0 003 0h-3z',
                            'cog' => 'M11.078 2.25c-.917-1.5-3.239-1.5-4.156 0l-.114.185c-.494.804-1.454 1.201-2.373.98a2.638 2.638 0 00-3.223 3.222c.22.919-.177 1.88-.98 2.374l-.185.113c-1.5.917-1.5 3.24 0 4.156l.185.114c.803.494 1.2 1.454.98 2.373a2.638 2.638 0 003.222 3.223c.919-.22 1.88.177 2.374.98l.113.185c.917 1.5 3.24 1.5 4.156 0l.114-.185c.494-.803 1.454-1.2 2.373-.98a2.638 2.638 0 003.223-3.222c-.22-.919.177-1.88.98-2.374l.185-.113c1.5-.917 1.5-3.24 0-4.156l-.185-.114c-.803-.494-1.2-1.454-.98-2.373a2.638 2.638 0 00-3.222-3.223c-.919.22-1.88-.177-2.374-.98l-.113-.185zM10 13a3 3 0 100-6 3 3 0 000 6z',
                        ];

                        $chevron = 'M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z';

                        $navigation = [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => $icons['dashboard']],
                            ['label' => 'For You', 'route' => 'for-you', 'icon' => $icons['for-you']],
                            ['label' => 'Starred', 'route' => 'starred', 'icon' => $icons['star']],
                            ['label' => 'Team', 'route' => 'users.index', 'icon' => $icons['users'], 'hidden' => auth()->user()->cannot('manage-users')],
                            ['label' => 'Projects', 'icon' => $icons['folder'], 'children' => [
                                ['label' => 'All Projects', 'route' => 'projects.index'],
                                ['label' => 'Create Project', 'route' => 'projects.create', 'hidden' => auth()->user()->cannot('create', \App\Models\Project::class)],
                            ]],
                            ['label' => 'Tasks', 'icon' => $icons['clipboard'], 'children' => [
                                ['label' => 'All Tasks', 'route' => 'tasks.index'],
                                ['label' => 'Create Task', 'route' => 'tasks.create'],
                            ]],
                            ['label' => 'Work History', 'route' => 'work-history.index', 'icon' => $icons['clock']],
                            ['label' => 'Calendar', 'route' => 'calendar.index', 'icon' => $icons['calendar']],
                            ['label' => 'Reports', 'icon' => $icons['chart'], 'disabled' => true],
                            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => $icons['bell']],
                            ['label' => 'Settings', 'route' => 'settings', 'icon' => $icons['cog'], 'hidden' => auth()->user()->cannot('manage-settings')],
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
                                    class="flex w-full items-center gap-2.5 overflow-hidden rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors duration-200 ease-in-out {{ $childActive ? 'bg-brand text-white' : 'text-zinc-400 hover:bg-zinc-800 hover:text-white' }}"
                                    :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 shrink-0"><path fill-rule="evenodd" d="{{ $item['icon'] }}" clip-rule="evenodd" /></svg>
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
                                    <div x-ref="submenuPanel" class="ml-4 mt-1 space-y-0.5 border-l border-zinc-800 pl-4">
                                        @foreach ($visibleChildren as $child)
                                            <a
                                                href="{{ route($child['route']) }}"
                                                wire:navigate
                                                class="block rounded-lg px-3 py-1.5 text-sm transition {{ request()->routeIs($child['route']) ? 'font-medium text-white' : 'text-zinc-400 hover:text-white' }}"
                                            >
                                                {{ $child['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @elseif (! empty($item['disabled']))
                            <span
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-zinc-500"
                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                                @mouseenter="showFlyout($el, @js($item['label'].' · Soon'))"
                                @mouseleave="flyout.show = false"
                            >
                                <span class="flex items-center gap-2.5 overflow-hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 shrink-0"><path fill-rule="evenodd" d="{{ $item['icon'] }}" clip-rule="evenodd" /></svg>
                                    <span class="whitespace-nowrap transition-all duration-200" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                                </span>
                                <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide" :class="sidebarCollapsed ? 'lg:hidden' : ''">Soon</span>
                            </span>
                        @else
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @mouseenter="showFlyout($el, @js($item['label']))"
                                @mouseleave="flyout.show = false"
                                class="flex items-center gap-2.5 overflow-hidden rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200 ease-in-out {{ request()->routeIs($item['route'], \Illuminate\Support\Str::before($item['route'], '.').'.*') ? 'bg-brand text-white' : 'text-zinc-400 hover:bg-zinc-800 hover:text-white' }}"
                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 shrink-0"><path fill-rule="evenodd" d="{{ $item['icon'] }}" clip-rule="evenodd" /></svg>
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

            <div
                x-show="sidebarOpen"
                x-cloak
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-zinc-900/50 lg:hidden"
            ></div>

            {{-- Main column --}}
            <div class="flex flex-1 flex-col" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'">
                <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-zinc-200 bg-white px-4 sm:px-6">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 lg:hidden"
                        @click="sidebarOpen = ! sidebarOpen"
                    >
                        <span class="sr-only">Toggle sidebar</span>
                        &#9776;
                    </button>

                    <div class="flex flex-1 items-center justify-end gap-4">
                        <livewire:notifications.bell />

                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-zinc-900">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-zinc-500">{{ auth()->user()->getRoleNames()->first() ?? 'No role' }}</p>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-50">
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="flex-1 p-4 sm:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Toast notifications: any Livewire component can trigger one with
             $this->dispatch('notify', message: '...', type: 'error'|'success'). --}}
        <div
            x-data="{ toasts: [] }"
            x-on:notify.window="
                const id = Date.now() + Math.random();
                toasts.push({ id, message: $event.detail.message, type: $event.detail.type ?? 'info' });
                setTimeout(() => { toasts = toasts.filter((t) => t.id !== id) }, 5000);
            "
            class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-4"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-show="true"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2 sm:translate-x-4 sm:translate-y-0"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border bg-white p-4 shadow-lg"
                    :class="toast.type === 'error' ? 'border-red-200' : (toast.type === 'success' ? 'border-brand/30' : 'border-zinc-200')"
                >
                    <span
                        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full"
                        :class="toast.type === 'error' ? 'bg-red-100 text-red-600' : (toast.type === 'success' ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500')"
                    >
                        <template x-if="toast.type === 'error'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.63-1.516 2.63H3.72c-1.347 0-2.189-1.463-1.516-2.63L8.485 2.495ZM10 5.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5.5Zm0 8a1 1 0 100-2 1 1 0 000 2Z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="toast.type === 'success'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="toast.type === 'info'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                            </svg>
                        </template>
                    </span>
                    <p class="flex-1 pt-0.5 text-sm text-zinc-700" x-text="toast.message"></p>
                    <button type="button" @click="toasts = toasts.filter((t) => t.id !== toast.id)" class="shrink-0 text-zinc-400 hover:text-zinc-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        @livewireScripts
    </body>
</html>
