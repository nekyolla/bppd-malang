<?php

namespace App\Http\Controllers;

use App\Models\PelatihanPeserta;
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

    public function pendaftaran(PelatihanPeserta $pelatihanPeserta, string $jenis): StreamedResponse
    {
        Gate::authorize('view', $pelatihanPeserta);

        [$kunci, $nama] = self::JENIS_PENDAFTARAN[$jenis];
        $path = data_get($pelatihanPeserta, $kunci);
        $disk = Storage::disk(RegistrasiService::DISK);

        abort_unless(filled($path) && $disk->exists($path), 404);

        $ekstensi = pathinfo($path, PATHINFO_EXTENSION);

        // Dibuka langsung di browser (inline) agar admin dapat memeriksa tanpa mengunduh.
        return $disk->response($path, "{$nama}-{$pelatihanPeserta->id}.{$ekstensi}", [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
