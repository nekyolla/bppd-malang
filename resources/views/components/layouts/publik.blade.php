<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <title>{{ $title ?? 'Registrasi Peserta' }} · BBPD Malang</title>

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
            <div class="mx-auto max-w-2xl px-4 py-4">
                <p class="text-lg font-semibold">BBPD Malang</p>
                <p class="text-sm text-gray-600">Balai Besar Pemerintahan Desa</p>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-6">
            {{ $slot }}
        </main>

        @filamentScripts
    </body>
</html>
