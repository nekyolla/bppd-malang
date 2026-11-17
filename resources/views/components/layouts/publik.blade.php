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
            {{-- Tautan di baris kedua: kolom publik sempit, nama instansi tidak muat bersebelahan dengan tautan. --}}
            <div class="mx-auto flex max-w-2xl flex-col gap-2 px-4 py-3">
                <div class="h-10"><x-merek /></div>
                <nav aria-label="Halaman publik" class="flex shrink-0 gap-4 text-sm font-medium">
                    <a href="{{ route('registrasi') }}" @class(['text-gray-600 hover:text-primary-600', 'text-primary-600' => request()->routeIs('registrasi')])>Daftar</a>
                    <a href="{{ route('sertifikat.publik') }}" @class(['text-gray-600 hover:text-primary-600', 'text-primary-600' => request()->routeIs('sertifikat.publik')])>Unduh sertifikat</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-6">
            {{ $slot }}
        </main>

        @filamentScripts
    </body>
</html>
