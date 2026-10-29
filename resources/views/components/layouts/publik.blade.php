<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <title>{{ $title ?? 'Registrasi Peserta' }} · {{ \App\Support\Instansi::NAMA }}</title>

        @if ($favicon = \App\Support\Logo::favicon())
            <link rel="icon" href="{{ $favicon }}">
        @endif

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        @filamentStyles
        @vite('resources/css/app.css')
    </head>

    <body class="fi-publik min-h-screen bg-gray-50 text-gray-950 antialiased">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto h-16 max-w-2xl px-4 py-3">
                <x-merek />
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-6">
            {{ $slot }}
        </main>

        @filamentScripts
    </body>
</html>
