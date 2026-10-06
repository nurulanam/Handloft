{{-- Signed-in visitors get the not-found page inside the app shell (sidebar, header, shortcut bar), so it
     feels like part of the dashboard rather than a dead end; guests get it on the sign-in layout. The
     catch-all Route::fallback in routes/web.php is what makes the session — and auth() — available here
     for unknown URLs. --}}
@php
    $illustration = <<<'SVG'
        <svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg" class="relative size-full">
            <rect x="34" y="22" width="76" height="96" rx="12" fill="#fff" stroke="#e4e4e7" stroke-width="2"/>
            <rect x="48" y="40" width="34" height="7" rx="3.5" fill="#10512a" opacity=".85"/>
            <rect x="48" y="56" width="48" height="5" rx="2.5" fill="#e4e4e7"/>
            <rect x="48" y="68" width="40" height="5" rx="2.5" fill="#e4e4e7"/>
            <rect x="48" y="80" width="30" height="5" rx="2.5" fill="#e4e4e7"/>
            <circle cx="106" cy="100" r="24" fill="#f7fee7" stroke="#10512a" stroke-width="7"/>
            <path d="m123.5 117.5 15 15" stroke="#10512a" stroke-width="10" stroke-linecap="round"/>
            <path d="M99.5 94.5a6.5 6.5 0 1 1 9.4 5.8c-1.8.9-2.9 2.2-2.9 4.2v1" stroke="#10512a" stroke-width="4" stroke-linecap="round"/>
            <circle cx="106" cy="112" r="2.6" fill="#10512a"/>
        </svg>
        SVG;
@endphp

@auth
    @component('layouts.app', ['title' => 'Page not found · '.config('app.name')])
        <div class="flex min-h-[calc(100vh-12rem)] items-center justify-center py-8">
            <div class="w-full max-w-lg text-center">
                <div class="relative mx-auto size-36 sm:size-44">
                    <div class="absolute inset-4 rounded-full bg-brand-lime/30 blur-2xl"></div>
                    {!! $illustration !!}
                </div>

                <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">
                    <span class="size-1.5 rounded-full bg-brand"></span>
                    Error 404
                </span>
                <h1 class="mt-3 text-2xl font-semibold text-zinc-900 sm:text-3xl">This page wandered off</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm text-zinc-500">
                    The link may be broken, or the page was moved or deleted. Let's get you back to work.
                </p>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                    <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ route('dashboard') }}')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                        Go back
                    </button>
                    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90">
                        <x-nav-icon name="dashboard" class="size-4" />
                        Back to dashboard
                    </a>
                </div>

                <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-2">
                    <p class="px-2 pb-1 pt-1.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Or jump to</p>
                    <div class="grid grid-cols-3 gap-1">
                        @foreach ([['For You', 'for-you', 'for-you'], ['Tasks', 'tasks.index', 'tasks'], ['Projects', 'projects.index', 'projects']] as [$linkLabel, $linkRoute, $linkIcon])
                            <a href="{{ route($linkRoute) }}" wire:navigate class="flex flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-xs font-medium text-zinc-600 hover:bg-brand/5 hover:text-brand">
                                <x-nav-icon :name="$linkIcon" class="size-5" />
                                {{ $linkLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endcomponent
@else
    @component('layouts.guest', ['title' => 'Page not found · '.config('app.name')])
        <div class="text-center lg:text-left">
            <div class="relative mx-auto size-32 lg:mx-0">
                <div class="absolute inset-4 rounded-full bg-brand-lime/30 blur-2xl"></div>
                {!! $illustration !!}
            </div>
            <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">
                <span class="size-1.5 rounded-full bg-brand"></span>
                Error 404
            </span>
            <h1 class="mt-3 text-2xl font-semibold text-zinc-900">Page not found</h1>
            <p class="mt-2 text-sm text-zinc-500">The link may be broken, or the page was moved. Sign in to get back to your work.</p>
            <a href="{{ route('login') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand/90">Go to sign in</a>
        </div>
    @endcomponent
@endauth
