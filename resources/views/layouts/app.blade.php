<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles

        {{-- Carves a round notch into the sidebar's right edge around the
             collapse toggle, so the edge itself curves smoothly away from the
             button instead of the button sitting on top of a straight line.
             Desktop-only, matching where the toggle button is shown. --}}
        <style>
            @media (min-width: 1024px) {
                .sidebar-notch {
                    mask-image: radial-gradient(circle at 100% calc(100% - 42px), transparent 24px, black 24px);
                    -webkit-mask-image: radial-gradient(circle at 100% calc(100% - 42px), transparent 24px, black 24px);
                }
            }
        </style>
    </head>
    <body
        class="min-h-screen bg-zinc-50 font-sans antialiased"
        x-data="{
            sidebarOpen: false,
            sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
            toggleCollapsed() {
                this.sidebarCollapsed = ! this.sidebarCollapsed;
                localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
            },
        }"
    >
        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside
                class="sidebar-notch fixed inset-y-0 left-0 z-30 w-64 transform overflow-x-hidden overflow-y-auto bg-zinc-900 text-zinc-300 transition-[transform,width] duration-300 ease-in-out lg:translate-x-0"
                :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'lg:w-20' : 'lg:w-64']"
            >
                <div class="flex h-16 items-center gap-2 px-6 text-lg font-semibold text-white" :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-brand-lime"></span>
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
                            'user-plus' => 'M7 8a3 3 0 100-6 3 3 0 000 6zm7.75-2.5a.75.75 0 00-1.5 0v1.25H12a.75.75 0 000 1.5h1.25V9.5a.75.75 0 001.5 0V8.25H16a.75.75 0 000-1.5h-1.25V5.5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572z',
                            'megaphone' => 'M9.653 16.915l-.006-.002-.019-.01a20.759 20.759 0 01-1.162-.682 22.045 22.045 0 01-2.582-1.9C4.045 12.733 2 10.352 2 7.5 2 4.836 4.162 3 6.5 3c1.86 0 3.43.98 4.5 2.56C12.07 3.98 13.64 3 15.5 3 17.838 3 20 4.836 20 7.5c0 2.852-2.044 5.233-3.885 6.82a22.049 22.049 0 01-3.744 2.582l-.019.01-.005.003h-.002a.739.739 0 01-.692 0z',
                            'calendar' => 'M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zM3.5 8.5v6.75c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25V8.5h-13z',
                            'chart' => 'M15.5 2A1.5 1.5 0 0014 3.5v13a1.5 1.5 0 001.5 1.5h1a1.5 1.5 0 001.5-1.5v-13A1.5 1.5 0 0016.5 2h-1zM9.5 6A1.5 1.5 0 008 7.5v9A1.5 1.5 0 009.5 18h1a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0010.5 6h-1zM3.5 10A1.5 1.5 0 002 11.5v5A1.5 1.5 0 003.5 18h1A1.5 1.5 0 006 16.5v-5A1.5 1.5 0 004.5 10h-1z',
                            'bell' => 'M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM8.5 16a1.5 1.5 0 003 0h-3z',
                            'cog' => 'M11.078 2.25c-.917-1.5-3.239-1.5-4.156 0l-.114.185c-.494.804-1.454 1.201-2.373.98a2.638 2.638 0 00-3.223 3.222c.22.919-.177 1.88-.98 2.374l-.185.113c-1.5.917-1.5 3.24 0 4.156l.185.114c.803.494 1.2 1.454.98 2.373a2.638 2.638 0 003.222 3.223c.919-.22 1.88.177 2.374.98l.113.185c.917 1.5 3.24 1.5 4.156 0l.114-.185c.494-.803 1.454-1.2 2.373-.98a2.638 2.638 0 003.223-3.222c-.22-.919.177-1.88.98-2.374l.185-.113c1.5-.917 1.5-3.24 0-4.156l-.185-.114c-.803-.494-1.2-1.454-.98-2.373a2.638 2.638 0 00-3.222-3.223c-.919.22-1.88-.177-2.374-.98l-.113-.185zM10 13a3 3 0 100-6 3 3 0 000 6z',
                        ];

                        $navigation = [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => $icons['dashboard']],
                            ['label' => 'For You', 'route' => 'for-you', 'icon' => $icons['for-you']],
                            ['label' => 'Starred', 'route' => 'starred', 'icon' => $icons['star']],
                            ['label' => 'Team', 'route' => 'users.index', 'icon' => $icons['users'], 'hidden' => auth()->user()->cannot('manage-users')],
                            ['label' => 'Projects', 'route' => 'projects.index', 'icon' => $icons['folder']],
                            ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => $icons['clipboard']],
                            ['label' => 'Work History', 'route' => 'work-history.index', 'icon' => $icons['clock']],
                            ['label' => 'Leads', 'icon' => $icons['user-plus'], 'disabled' => true],
                            ['label' => 'Outreach', 'icon' => $icons['megaphone'], 'disabled' => true],
                            ['label' => 'Calendar', 'icon' => $icons['calendar'], 'disabled' => true],
                            ['label' => 'Reports', 'icon' => $icons['chart'], 'disabled' => true],
                            ['label' => 'Notifications', 'icon' => $icons['bell'], 'disabled' => true],
                            ['label' => 'Settings', 'icon' => $icons['cog'], 'disabled' => true],
                        ];
                    @endphp

                    @foreach ($navigation as $item)
                        @continue(! empty($item['hidden']))

                        @if (! empty($item['disabled']))
                            <span
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-zinc-500"
                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                                title="{{ $item['label'] }} (coming soon)"
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
                                title="{{ $item['label'] }}"
                                class="flex items-center gap-2.5 overflow-hidden rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs($item['route'], \Illuminate\Support\Str::before($item['route'], '.').'.*') ? 'bg-brand text-white' : 'text-zinc-400 hover:bg-zinc-800 hover:text-white' }}"
                                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4.5 shrink-0"><path fill-rule="evenodd" d="{{ $item['icon'] }}" clip-rule="evenodd" /></svg>
                                <span class="whitespace-nowrap transition-all duration-200" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </nav>
            </aside>

            {{-- Collapse toggle: a dark circular handle anchored to the
                 sidebar's edge, sliding along with it as it collapses/expands.
                 Same color as the sidebar with only a soft shadow (no hard
                 ring) so it reads as an extension of it, not a separate chip. --}}
            <button
                type="button"
                @click="toggleCollapsed()"
                class="fixed bottom-6 z-40 hidden size-9 -translate-x-1/2 items-center justify-center rounded-full bg-zinc-900 text-zinc-300 shadow-[0_2px_10px_rgba(0,0,0,0.35)] transition-[left,background-color,color] duration-300 ease-in-out hover:bg-brand hover:text-white lg:flex"
                :style="{ left: (sidebarCollapsed ? 80 : 256) + 'px' }"
                title="Toggle sidebar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M7.79 5.23a.75.75 0 01-.02 1.06L3.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                </svg>
            </button>

            <div
                x-show="sidebarOpen"
                x-cloak
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-zinc-900/50 lg:hidden"
            ></div>

            {{-- Main column --}}
            <div class="flex flex-1 flex-col transition-[padding] duration-300 ease-in-out" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'">
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
                        <button type="button" class="relative rounded-full p-2 text-zinc-500 hover:bg-zinc-100" title="Notifications (coming soon)">
                            <span aria-hidden="true">&#128276;</span>
                        </button>

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
