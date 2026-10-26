<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\KelasPelatihan;
use App\Models\PelatihanPeserta;
use App\Support\FormatTanggal;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export daftar hadir per kelas (PF-18), dicetak di kertas A4 tegak. Tidak memuat NIK
 * (CLAUDE.md aturan 9) dan setiap unduhan tercatat di audit log.
 *
 * Bentuk data export: judul, baris keterangan, kolom, baris isi, dan jumlah kolom tanda
 * tangan di ujung kanan (daftar hadir).
 *
 * @phpstan-type DataExport array{judul: string, keterangan: list<string>, kolom: list<string>, baris: list<list<string|int>>, kolomTtd: int}
 */
class ExportService
{
    /**
     * Daftar hadir cetak satu kelas: tanpa NIK, satu kolom tanda tangan per hari pelatihan.
     *
     * @return DataExport
     */
    public function daftarHadirKelas(KelasPelatihan $kelas): array
    {
        $pelatihan = $kelas->pelatihan()->with('judulPelatihan')->firstOrFail();
        $hari = collect(CarbonPeriod::create($pelatihan->tanggal_mulai, $pelatihan->tanggal_selesai))
            ->map(fn ($tanggal): string => $tanggal->locale('id')->translatedFormat('D, j/n'))
            ->values()
            ->all();

        $baris = $kelas->pendaftaran()
            ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai])
            ->with(['peserta', 'jabatanSaatPelatihan', 'desaSaatPelatihan.kecamatan.kabKota'])
            ->get()
            ->sortBy('peserta.nama_lengkap')
            ->values()
            ->map(fn (PelatihanPeserta $p, int $i): array => [
                $i + 1,
                $p->peserta->nama_lengkap,
                $p->jabatanSaatPelatihan?->nama_jabatan ?? '',
                // Desa & kab/kota satu kolom agar muat di A4 tegak.
                $p->desaSaatPelatihan ? "{$p->desaSaatPelatihan->nama}, {$p->desaSaatPelatihan->kecamatan->kabKota->nama}" : '',
                ...array_fill(0, count($hari), ''),
            ])
            ->all();

        return [
            'judul' => 'Daftar Hadir Peserta',
            'keterangan' => [
                $pelatihan->nama_tampilan,
                "Kelas {$kelas->nama_kelas} · ".FormatTanggal::rentang($pelatihan->tanggal_mulai, $pelatihan->tanggal_selesai),
            ],
            'kolom' => ['No', 'Nama', 'Jabatan', 'Desa', ...$hari],
            'baris' => $baris,
            'kolomTtd' => count($hari),
        ];
    }

    /**
     * @param  DataExport  $data
     */
    public function unduhExcel(array $data, string $namaFile, Model $subjek): StreamedResponse
    {
        $this->catatUnduhan($subjek, "{$namaFile}.xlsx");

        return response()->streamDownload(function () use ($data): void {
            $opsi = new Options;
            $opsi->setPageSetup(new PageSetup(PageOrientation::PORTRAIT, PaperSize::A4, fitToWidth: 1));
            $writer = new Writer($opsi);
            $writer->openToFile('php://output');
            $tebal = (new Style)->setFontBold();

            $writer->addRow(Row::fromValues([$data['judul']], $tebal));

            foreach ($data['keterangan'] as $baris) {
                $writer->addRow(Row::fromValues([$baris]));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues($data['kolom'], $tebal));

            foreach ($data['baris'] as $baris) {
                $writer->addRow(Row::fromValues($baris));
            }

            $writer->close();
        }, "{$namaFile}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param  DataExport  $data
     */
    public function unduhPdf(array $data, string $namaFile, Model $subjek): StreamedResponse
    {
        $this->catatUnduhan($subjek, "{$namaFile}.pdf");

        $pdf = Pdf::loadView('export.tabel', [...$data, 'dicetak' => now()->locale('id')->translatedFormat('j F Y, H:i')])
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print ($pdf->output()), "{$namaFile}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Unduhan berisi data pribadi tercatat di audit log (FR-AUD-02).
     */
    private function catatUnduhan(Model $subjek, string $namaFile): void
    {
        activity('unduhan')
            ->performedOn($subjek)
            ->causedBy(auth()->user())
            ->event('unduh')
            ->withProperties(['berkas' => $namaFile])
            ->log("Mengunduh {$namaFile}");
    }
}
