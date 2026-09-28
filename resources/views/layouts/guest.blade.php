<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div class="mb-8 flex items-center gap-2 text-xl font-semibold text-zinc-900">
                <span class="h-2.5 w-2.5 rounded-full bg-brand-lime"></span>
                {{ config('app.name') }}
            </div>

            <div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-8">
                {{ $slot }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>
