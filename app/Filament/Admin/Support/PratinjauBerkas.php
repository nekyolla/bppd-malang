<?php

namespace App\Filament\Admin\Support;

use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use Filament\Schemas\Components\View;

/**
 * Pratinjau KTP & pas foto di samping isian (modal verifikasi, halaman Peserta). Berkas tetap
 * dibuka lewat route privat `berkas.*` yang memeriksa policy (CLAUDE.md aturan 1).
 */
class PratinjauBerkas
{
    public static function pendaftaran(PelatihanPeserta $pendaftaran): View
    {
        $url = fn (string $jenis): string => route('berkas.pendaftaran', [$pendaftaran, $jenis]);

        return self::view([
            self::berkas('KTP', $pendaftaran->data_isian['file_ktp'] ?? null, $url('ktp')),
            self::berkas('Pas foto', $pendaftaran->data_isian['foto'] ?? null, $url('foto')),
            self::berkas('Surat tugas', $pendaftaran->file_surat_tugas, $url('surat-tugas'), tautanSaja: true),
        ]);
    }

    public static function peserta(Peserta $peserta): View
    {
        $url = fn (string $jenis): string => route('berkas.peserta', [$peserta, $jenis]);

        return self::view([
            self::berkas('KTP', $peserta->file_ktp, $url('ktp')),
            self::berkas('Pas foto', $peserta->foto, $url('foto')),
        ]);
    }

    /**
     * @param  list<array{label: string, url: ?string, pdf: bool, tautanSaja: bool}>  $berkas
     */
    private static function view(array $berkas): View
    {
        return View::make('filament.admin.partials.pratinjau-berkas')->viewData(['berkas' => $berkas]);
    }

    /**
     * @return array{label: string, url: ?string, pdf: bool, tautanSaja: bool}
     */
    private static function berkas(string $label, ?string $path, string $url, bool $tautanSaja = false): array
    {
        return [
            'label' => $label,
            'url' => filled($path) ? $url : null,
            'pdf' => str_ends_with((string) $path, '.pdf'),
            'tautanSaja' => $tautanSaja,
        ];
    }
}
