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
                            ['label' => 'Projects', 'route' => 'projects.index'],
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
