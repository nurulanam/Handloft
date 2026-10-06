{{-- A full error page. Signed-in visitors get it inside the app shell (sidebar, header, shortcut bar), so it
     feels like part of the dashboard rather than a dead end; guests get it on the sign-in layout. Unknown
     URLs reach this with the session loaded thanks to the catch-all Route::fallback in routes/web.php. --}}
@props([
    'code',
    'title',
    'message',
    'guestTitle',
    'guestMessage',
    'illustration',
])

@auth
    @component('layouts.app', ['title' => $guestTitle.' · '.config('app.name')])
        <div class="flex min-h-[calc(100vh-12rem)] items-center justify-center py-8">
            <div class="w-full max-w-lg text-center">
                <div class="relative mx-auto size-36 sm:size-44">
                    <div class="absolute inset-4 rounded-full bg-brand-lime/30 blur-2xl"></div>
                    {!! $illustration !!}
                </div>

                <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">
                    <span class="size-1.5 rounded-full bg-brand"></span>
                    Error {{ $code }}
                </span>
                <h1 class="mt-3 text-2xl font-semibold text-zinc-900 sm:text-3xl">{{ $title }}</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm text-zinc-500">{{ $message }}</p>

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
    @component('layouts.guest', ['title' => $guestTitle.' · '.config('app.name')])
        <div class="text-center lg:text-left">
            <div class="relative mx-auto size-32 lg:mx-0">
                <div class="absolute inset-4 rounded-full bg-brand-lime/30 blur-2xl"></div>
                {!! $illustration !!}
            </div>
            <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">
                <span class="size-1.5 rounded-full bg-brand"></span>
                Error {{ $code }}
            </span>
            <h1 class="mt-3 text-2xl font-semibold text-zinc-900">{{ $guestTitle }}</h1>
            <p class="mt-2 text-sm text-zinc-500">{{ $guestMessage }}</p>
            <a href="{{ route('login') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand/90">Go to sign in</a>
        </div>
    @endcomponent
@endauth
