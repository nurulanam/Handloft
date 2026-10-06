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

            {{-- Form column, on frosted glass. A colourful backdrop sits behind it for the glass to blur: on phones
                 the brand green (with lime glows) fills the whole screen under a hero and a glass sheet that overlaps
                 it like a native sign-in screen; from lg up, soft brand glows sit behind a glass card. --}}
            <div class="relative isolate flex min-h-dvh w-full flex-1 flex-col overflow-hidden lg:min-h-0 lg:items-center lg:justify-center lg:px-12 lg:py-10">
                <div class="pointer-events-none absolute inset-0 -z-10 bg-brand lg:bg-zinc-50" aria-hidden="true">
                    <div class="absolute inset-0 opacity-10 lg:hidden" style="background-image: radial-gradient(circle, #fff 1.5px, transparent 1.5px); background-size: 22px 22px;"></div>
                    <div class="absolute -right-16 -top-20 size-72 rounded-full bg-brand-lime/25 blur-3xl lg:hidden"></div>
                    {{-- Bright, slowly drifting glows behind the glass sheet / card, so the frosting reads clearly. --}}
                    <div class="glow-drift absolute -bottom-10 -right-16 size-80 rounded-full bg-brand-lime/70 blur-3xl lg:hidden"></div>
                    <div class="glow-drift-alt absolute bottom-32 -left-20 size-72 rounded-full bg-emerald-300/60 blur-3xl lg:hidden"></div>
                    <div class="glow-drift absolute bottom-0 left-1/3 size-56 rounded-full bg-white/40 blur-3xl [animation-delay:-5s] lg:hidden"></div>

                    {{-- Desktop: calm on a big screen. A faint dot grid and two very soft, low-saturation glows. --}}
                    <div class="absolute inset-0 hidden opacity-30 lg:block" style="background-image: radial-gradient(circle, #d4d4d8 1px, transparent 1px); background-size: 24px 24px;"></div>
                    <div class="glow-drift absolute right-[10%] top-[15%] hidden size-[26rem] rounded-full bg-brand-lime/15 blur-3xl [animation-duration:30s] lg:block"></div>
                    <div class="glow-drift-alt absolute bottom-[10%] left-[15%] hidden size-[26rem] rounded-full bg-emerald-200/25 blur-3xl [animation-duration:36s] lg:block"></div>
                </div>

                <div class="auth-hero relative flex flex-1 flex-col px-6 pb-16 pt-[max(2.25rem,env(safe-area-inset-top))] text-white lg:hidden">
                    <div class="flex items-center gap-2.5">
                        <x-logo-mark class="size-10 shrink-0" />
                        <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                    </div>

                    <h2 class="mt-7 max-w-xs text-[1.7rem] font-semibold leading-tight">Work handed off, <span class="text-brand-lime">never dropped.</span></h2>
                    <p class="mt-3 max-w-xs text-pretty text-sm leading-relaxed text-white/75 [@media(max-height:700px)]:hidden">Assign, track and hand off tasks across your team — every handoff, hour and deadline in one place.</p>

                    {{-- A glimpse of the product: a notification-style card with a "QA approved" badge pinned to its
                         corner, floating gently as one piece. --}}
                    <div class="float-slow relative mr-3 mt-7 self-start [@media(max-height:700px)]:hidden" aria-hidden="true">
                        <div class="relative flex items-center gap-3 rounded-2xl bg-white/12 pb-3 pl-3 pr-6 pt-4.5 shadow-lg shadow-black/10 ring-1 ring-white/20 backdrop-blur-md">
                            <span class="flex -space-x-2">
                                <span class="flex size-8 items-center justify-center rounded-full bg-brand-lime text-[11px] font-bold text-brand ring-2 ring-[#1c5a33]">RA</span>
                                <span class="flex size-8 items-center justify-center rounded-full bg-white text-[11px] font-bold text-brand ring-2 ring-[#1c5a33]">AN</span>
                            </span>
                            <span class="text-xs leading-snug">
                                <span class="block font-semibold text-white">AMD-24 handed to Anam</span>
                                <span class="block text-white/65">Website audit · just now</span>
                            </span>
                        </div>
                        <span class="absolute -right-3 -top-3.5 flex items-center gap-1 rounded-full bg-brand-lime py-1 pl-1.5 pr-2.5 text-[11px] font-bold text-brand shadow-lg shadow-black/25 ring-2 ring-[#1c5a33]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                            QA approved
                        </span>
                    </div>
                </div>

                <div class="auth-sheet relative -mt-8 w-full rounded-t-[2rem] border-t border-white/70 bg-white/72 px-6 pb-[max(2.5rem,env(safe-area-inset-bottom))] pt-8 shadow-[0_-16px_32px_-16px_rgb(0_0_0/0.35),inset_0_1px_0_rgb(255_255_255/0.8)] backdrop-blur-2xl backdrop-saturate-150 lg:mt-0 lg:max-w-md lg:rounded-3xl lg:border lg:border-zinc-900/5 lg:bg-white/85 lg:p-9 lg:shadow-xl lg:shadow-zinc-900/5">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
