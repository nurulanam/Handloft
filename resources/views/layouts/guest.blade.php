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
    </head>
    <body class="min-h-screen bg-white font-sans antialiased">
        <div class="flex min-h-screen">
            {{-- Brand panel — hidden below lg so the form is never squeezed
                 on a phone or tablet; the small inline logo in the form
                 column carries the brand there instead. --}}
            <div class="relative hidden w-full max-w-xl shrink-0 flex-col justify-between overflow-hidden bg-brand px-12 py-12 text-white lg:flex">
                <div class="pointer-events-none absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, #fff 1.5px, transparent 1.5px); background-size: 24px 24px;"></div>
                <div class="pointer-events-none absolute -top-28 -right-24 size-96 rounded-full bg-brand-lime/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-36 -left-20 size-96 rounded-full bg-brand-lime/10 blur-3xl"></div>

                <div class="relative z-10 flex items-center gap-3">
                    <x-logo-mark class="size-11 shrink-0" />
                    <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                </div>

                <div class="relative z-10 max-w-md">
                    <h2 class="text-3xl font-semibold leading-tight text-balance">Work handed off, never dropped.</h2>
                    <p class="mt-4 text-sm leading-relaxed text-white/70">
                        Assign, track and hand off tasks across your team — every reassignment, every hour, every deadline, kept in one place.
                    </p>

                    <ul class="mt-10 space-y-5">
                        <li class="flex items-start gap-3.5">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 text-brand-lime"><path fill-rule="evenodd" d="M4.755 10.059a7.5 7.5 0 0112.548-3.364l1.903 1.903h-3.183a.75.75 0 100 1.5h4.992a.75.75 0 00.75-.75V4.356a.75.75 0 00-1.5 0v3.18l-1.9-1.9A9 9 0 003.306 9.67a.75.75 0 101.45.388zm15.408 3.352a.75.75 0 00-.919.53 7.5 7.5 0 01-12.548 3.364l-1.902-1.903h3.183a.75.75 0 000-1.5H2.984a.75.75 0 00-.75.75v4.992a.75.75 0 001.5 0v-3.18l1.9 1.9a9 9 0 0015.059-4.035.75.75 0 00-.53-.918z" clip-rule="evenodd" /></svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-white">Full handoff history</p>
                                <p class="text-xs text-white/60">Every reassignment recorded — nothing lost in the shuffle.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3.5">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 text-brand-lime"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd" /></svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-white">Automatic work history</p>
                                <p class="text-xs text-white/60">Daily hours logged and rolled up, with zero extra data entry.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3.5">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 text-brand-lime"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zM3.5 8.5v6.75c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25V8.5h-13z" clip-rule="evenodd" /></svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-white">One shared calendar</p>
                                <p class="text-xs text-white/60">Every task and project deadline, visible to whoever needs it.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <p class="relative z-10 text-xs text-white/40">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>

            {{-- Form column --}}
            <div class="flex w-full flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6 lg:px-12">
                <div class="w-full max-w-sm">
                    <div class="mb-8 flex items-center gap-2.5 text-xl font-semibold text-zinc-900 lg:hidden">
                        <x-logo-mark class="size-9 shrink-0" />
                        {{ config('app.name') }}
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
