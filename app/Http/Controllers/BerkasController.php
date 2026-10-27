<?php

namespace App\Http\Controllers;

use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Services\RegistrasiService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu-satunya jalan membuka berkas pribadi di disk privat, setelah policy
 * diperiksa (CLAUDE.md aturan 1, NFR-02).
 */
class BerkasController extends Controller
{
    /**
     * Jenis berkas di URL => [kunci path, nama file unduhan].
     */
    public const JENIS_PENDAFTARAN = [
        'ktp' => ['data_isian.file_ktp', 'ktp'],
        'foto' => ['data_isian.foto', 'pas-foto'],
        'surat-tugas' => ['file_surat_tugas', 'surat-tugas'],
    ];

    /**
     * Jenis berkas peserta di URL => [kolom path, nama file unduhan] (FR-PST-03).
     */
    public const JENIS_PESERTA = [
        'ktp' => ['file_ktp', 'ktp'],
        'foto' => ['foto', 'pas-foto'],
    ];

    public function pendaftaran(PelatihanPeserta $pelatihanPeserta, string $jenis): StreamedResponse
    {
        Gate::authorize('view', $pelatihanPeserta);

        [$kunci, $nama] = self::JENIS_PENDAFTARAN[$jenis];

        return $this->tampilkan(data_get($pelatihanPeserta, $kunci), "{$nama}-{$pelatihanPeserta->id}");
    }

    /**
     * KTP & pas foto terbaru peserta (yang diterapkan admin saat verifikasi).
     */
    public function peserta(Peserta $peserta, string $jenis): StreamedResponse
    {
        Gate::authorize('view', $peserta);

        [$kolom, $nama] = self::JENIS_PESERTA[$jenis];

        return $this->tampilkan($peserta->{$kolom}, "{$nama}-peserta-{$peserta->id}");
    }

    /**
     * Sertifikat PDF peserta (PF-27).
     */
    public function sertifikat(PelatihanPeserta $pelatihanPeserta): StreamedResponse
    {
        Gate::authorize('view', $pelatihanPeserta);

        return $this->tampilkan($pelatihanPeserta->sertifikat?->file_sertifikat, "sertifikat-{$pelatihanPeserta->id}");
    }

    private function tampilkan(?string $path, string $namaDasar): StreamedResponse
    {
        $disk = Storage::disk(RegistrasiService::DISK);

        abort_unless(filled($path) && $disk->exists($path), 404);

        $ekstensi = pathinfo($path, PATHINFO_EXTENSION);

        // Dibuka langsung di browser (inline) agar admin dapat memeriksa tanpa mengunduh.
        return $disk->response($path, "{$namaDasar}.{$ekstensi}", [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
