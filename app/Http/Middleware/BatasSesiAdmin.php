<?php

namespace App\Http\Middleware;

use App\Filament\Admin\Livewire\LoncengVerifikasi;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan akun panel setelah tidak aktif (`session.batas_diam`) atau terlalu lama login
 * (`session.batas_mutlak`). Polling lonceng tidak dihitung aktivitas, sehingga tab yang
 * dibiarkan terbuka tetap keluar. Persisten: ikut berjalan pada permintaan update Livewire.
 */
class BatasSesiAdmin
{
    public const KUNCI_MULAI = 'batas_sesi.mulai';

    public const KUNCI_AKTIF = 'batas_sesi.aktif';

    /**
     * Ditarik halaman login untuk menjelaskan kenapa akun keluar.
     */
    public const KUNCI_BERAKHIR = 'batas_sesi.berakhir';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $sesi = $request->session();
        $sekarang = now()->getTimestamp();
        $mulai = $sesi->get(self::KUNCI_MULAI, $sekarang);
        $aktif = $sesi->get(self::KUNCI_AKTIF, $sekarang);

        $alasan = match (true) {
            $sekarang - $aktif > config('session.batas_diam') * 60 => 'diam',
            $sekarang - $mulai > config('session.batas_mutlak') * 60 => 'mutlak',
            default => null,
        };

        if ($alasan !== null) {
            return $this->keluarkan($request, $alasan);
        }

        $sesi->put(self::KUNCI_MULAI, $mulai);

        if (! $this->pasif($request)) {
            $sesi->put(self::KUNCI_AKTIF, $sekarang);
        }

        return $next($request);
    }

    private function keluarkan(Request $request, string $alasan): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(self::KUNCI_BERAKHIR, $alasan);

        // Livewire menjalankan middleware persisten tanpa memakai responsnya, jadi 401 dilempar.
        // Skrip panel memuat ulang halaman saat menerima 401 (lihat AdminPanelProvider).
        if ($request->hasHeader('X-Livewire')) {
            throw new HttpResponseException(response()->json(['message' => 'Sesi berakhir.'], 401));
        }

        return redirect()->to(Filament::getLoginUrl());
    }

    /**
     * Update Livewire yang hanya menyegarkan lonceng (polling) bukan aktivitas pengguna.
     */
    private function pasif(Request $request): bool
    {
        if (! $request->hasHeader('X-Livewire')) {
            return false;
        }

        $komponen = collect($request->input('components', []));

        return $komponen->isNotEmpty() && $komponen->every(
            fn ($k): bool => (json_decode($k['snapshot'] ?? '', true)['memo']['name'] ?? null) === LoncengVerifikasi::NAMA,
        );
    }
}
