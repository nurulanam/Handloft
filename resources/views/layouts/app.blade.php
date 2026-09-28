<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 antialiased" x-data="{ sidebarOpen: false }">
        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside
                class="fixed inset-y-0 left-0 z-30 w-64 transform bg-slate-900 text-slate-200 transition-transform lg:static lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="flex h-16 items-center px-6 text-lg font-semibold text-white">
                    {{ config('app.name') }}
                </div>

                <nav class="space-y-1 px-3 pb-8">
                    @php
                        $navigation = [
                            ['label' => 'Dashboard', 'route' => 'dashboard'],
                            ['label' => 'Team', 'disabled' => true],
                            ['label' => 'Tasks', 'disabled' => true],
                            ['label' => 'Leads', 'disabled' => true],
                            ['label' => 'Outreach', 'disabled' => true],
                            ['label' => 'Calendar', 'disabled' => true],
                            ['label' => 'Reports', 'disabled' => true],
                            ['label' => 'Notifications', 'disabled' => true],
                            ['label' => 'Settings', 'disabled' => true],
                        ];
                    @endphp

                    @foreach ($navigation as $item)
                        @if (! empty($item['disabled']))
                            <span class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-500">
                                {{ $item['label'] }}
                                <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">Soon</span>
                            </span>
                        @else
                            <a
                                href="{{ route($item['route']) }}"
                                class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                            >
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                </nav>
            </aside>

            <div
                x-show="sidebarOpen"
                x-cloak
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-slate-900/50 lg:hidden"
            ></div>

            {{-- Main column --}}
            <div class="flex flex-1 flex-col lg:pl-0">
                <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                    <button
                        type="button"
                        class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        @click="sidebarOpen = ! sidebarOpen"
                    >
                        <span class="sr-only">Toggle sidebar</span>
                        &#9776;
                    </button>

                    <div class="flex flex-1 items-center justify-end gap-4">
                        <button type="button" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100" title="Notifications (coming soon)">
                            <span aria-hidden="true">&#128276;</span>
                        </button>

                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-slate-500">{{ auth()->user()->getRoleNames()->first() ?? 'No role' }}</p>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
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

        @livewireScripts
    </body>
</html>
