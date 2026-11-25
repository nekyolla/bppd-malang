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
            {{-- Perpindahan /daftar ↔ /sertifikat lewat tombol di isi halaman, bukan menu header (revisi 17 Nov). --}}
            <div class="mx-auto max-w-2xl px-4 py-3">
                <div class="h-10"><x-merek /></div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-6">
            {{ $slot }}
        </main>

        {{--
            Sesi kedaluwarsa (419) karena halaman dibiarkan lebih lama dari SESSION_LIFETIME. Isian
            tersimpan di snapshot Livewire di halaman, yang tidak valid hanya token CSRF-nya. Jadi
            token baru diambil tanpa memuat ulang halaman, alih-alih dialog bawaan "This page has
            expired" yang memuat ulang dan menghapus isian. Panel admin punya penanganan sendiri
            (AdminPanelProvider: muat ulang ke halaman login).
        --}}
        <script>
            document.addEventListener('livewire:init', () => {
                let sedangMemperbarui = false;

                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status !== 419) {
                            return;
                        }

                        preventDefault();

                        if (sedangMemperbarui) {
                            return;
                        }

                        sedangMemperbarui = true;

                        fetch(window.location.pathname, { headers: { Accept: 'text/html' } })
                            .then((respons) => (respons.ok ? respons.text() : Promise.reject()))
                            .then((html) => {
                                const token = new DOMParser()
                                    .parseFromString(html, 'text/html')
                                    .querySelector('meta[name="csrf-token"]')?.content;

                                if (! token) {
                                    throw new Error('Token tidak ditemukan');
                                }

                                document.querySelector('meta[name="csrf-token"]').content = token;
                                alert('Halaman ini sudah lama tidak digunakan, jadi sambungannya diperbarui. Isian Anda tetap tersimpan. Bila tombol yang baru Anda tekan belum berjalan, tekan sekali lagi.');
                            })
                            .catch(() => {
                                if (confirm('Halaman ini sudah lama tidak digunakan. Muat ulang halaman sekarang? Isian yang belum dikirim perlu diisi ulang.')) {
                                    window.location.reload();
                                }
                            })
                            .finally(() => {
                                sedangMemperbarui = false;
                            });
                    });
                });
            });
        </script>

        @filamentScripts
    </body>
</html>
