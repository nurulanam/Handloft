<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-100 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div class="mb-8 text-2xl font-semibold text-slate-800">
                {{ config('app.name') }}
            </div>

            <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
                {{ $slot }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>
