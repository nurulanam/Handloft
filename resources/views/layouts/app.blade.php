<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans antialiased" x-data="{ sidebarOpen: false }">
        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside
                class="fixed inset-y-0 left-0 z-30 w-64 transform overflow-y-auto bg-zinc-900 text-zinc-300 transition-transform lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="flex h-16 items-center gap-2 px-6 text-lg font-semibold text-white">
                    <span class="h-2.5 w-2.5 rounded-full bg-brand-lime"></span>
                    {{ config('app.name') }}
                </div>

                <nav class="space-y-1 px-3 pb-8">
                    @php
                        $navigation = [
                            ['label' => 'Dashboard', 'route' => 'dashboard'],
                            ['label' => 'Team', 'route' => 'users.index', 'hidden' => auth()->user()->cannot('manage-users')],
                            ['label' => 'Tasks', 'route' => 'tasks.index'],
                            ['label' => 'Work History', 'route' => 'work-history.index'],
                            ['label' => 'Leads', 'disabled' => true],
                            ['label' => 'Outreach', 'disabled' => true],
                            ['label' => 'Calendar', 'disabled' => true],
                            ['label' => 'Reports', 'disabled' => true],
                            ['label' => 'Notifications', 'disabled' => true],
                            ['label' => 'Settings', 'disabled' => true],
                        ];
                    @endphp

                    @foreach ($navigation as $item)
                        @continue(! empty($item['hidden']))

                        @if (! empty($item['disabled']))
                            <span class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-zinc-500">
                                {{ $item['label'] }}
                                <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">Soon</span>
                            </span>
                        @else
                            <a
                                href="{{ route($item['route']) }}"
                                class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs($item['route'], \Illuminate\Support\Str::before($item['route'], '.').'.*') ? 'bg-brand text-white' : 'text-zinc-400 hover:bg-zinc-800 hover:text-white' }}"
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
                class="fixed inset-0 z-20 bg-zinc-900/50 lg:hidden"
            ></div>

            {{-- Main column --}}
            <div class="flex flex-1 flex-col lg:pl-64">
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

        @livewireScripts
    </body>
</html>
