<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Services\Concerns\MenolakAturan;
use App\Support\FormatTanggal;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export daftar hadir per kelas per hari (PF-18, PF-23), dicetak di kertas A4 tegak.
 * Presensi diisi di kertas; sistem tidak menyimpannya (PF-22). Tidak memuat NIK
 * (CLAUDE.md aturan 9) dan setiap unduhan tercatat di audit log.
 *
 * Satu lembar = satu hari: judul, baris keterangan, nama lembar (sheet Excel), kolom,
 * baris isi, dan jumlah kolom tanda tangan di ujung kanan.
 *
 * @phpstan-type DataExport array{judul: string, keterangan: list<string>, namaLembar: string, kolom: list<string>, baris: list<list<string|int>>, kolomTtd: int}
 */
class ExportService
{
    use MenolakAturan;

    /**
     * Pilihan hari pelatihan untuk daftar hadir, mis. `'2026-11-02' => 'Sen, 2 Nov'`.
     *
     * @return array<string, string>
     */
    public function pilihanHari(Pelatihan $pelatihan): array
    {
        return collect(CarbonPeriod::create($pelatihan->tanggal_mulai, $pelatihan->tanggal_selesai))
            ->mapWithKeys(fn (CarbonInterface $hari): array => [$hari->toDateString() => $hari->locale('id')->translatedFormat('D, j M')])
            ->all();
    }

    /**
     * Daftar hadir cetak satu kelas, satu lembar per tanggal terpilih (urut tanggal). Kolom
     * tanda tangan selang-seling: nomor ganjil di sel kiri, genap di sel kanan.
     *
     * @param  list<string>  $tanggal  Tanggal `Y-m-d` dalam rentang pelatihan.
     * @return list<DataExport>
     *
     * @throws ValidationException
     */
    public function daftarHadirKelas(KelasPelatihan $kelas, array $tanggal): array
    {
        $pelatihan = $kelas->pelatihan()->with('judulPelatihan')->firstOrFail();
        $hari = $this->hariTerpilih($pelatihan, $tanggal);

        $baris = $kelas->pendaftaran()
            ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai])
            ->with(['peserta', 'jabatanSaatPelatihan', 'desaSaatPelatihan.kecamatan.kabKota'])
            ->get()
            ->sortBy('peserta.nama_lengkap')
            ->values()
            ->map(fn (PelatihanPeserta $p, int $i): array => [
                $i + 1,
                $p->peserta->nama_lengkap,
                $p->peserta->jenis_kelamin->value,
                $p->jabatanSaatPelatihan?->nama_jabatan ?? '',
                // Desa & kab/kota satu kolom agar muat di A4 tegak.
                $p->desaSaatPelatihan ? "{$p->desaSaatPelatihan->nama}, {$p->desaSaatPelatihan->kecamatan->kabKota->nama}" : '',
                ...(($i + 1) % 2 === 1 ? [($i + 1).'.', ''] : ['', ($i + 1).'.']),
            ])
            ->all();

        return array_map(fn (CarbonInterface $tgl): array => [
            'judul' => 'Daftar Hadir Peserta',
            'keterangan' => [
                $pelatihan->nama_tampilan,
                "Kelas {$kelas->nama_kelas} · ".FormatTanggal::rentang($pelatihan->tanggal_mulai, $pelatihan->tanggal_selesai),
                'Hari/tanggal: '.$tgl->locale('id')->translatedFormat('l, j F Y'),
            ],
            'namaLembar' => $tgl->locale('id')->translatedFormat('D j M'),
            'kolom' => ['No', 'Nama', 'L/P', 'Jabatan', 'Desa', 'Tanda tangan', ''],
            'baris' => $baris,
            'kolomTtd' => 2,
        ], $hari);
    }

    /**
     * @param  list<string>  $tanggal
     * @return list<CarbonInterface>
     *
     * @throws ValidationException
     */
    private function hariTerpilih(Pelatihan $pelatihan, array $tanggal): array
    {
        $tanggal = array_values(array_unique(array_filter($tanggal, 'filled')));
        sort($tanggal);

        if ($tanggal === []) {
            $this->tolak('tanggal', 'Pilih minimal satu hari.');
        }

        return array_map(function (string $nilai) use ($pelatihan): CarbonInterface {
            $hari = Carbon::parse($nilai)->startOfDay();

            if ($hari->lt($pelatihan->tanggal_mulai) || $hari->gt($pelatihan->tanggal_selesai)) {
                $this->tolak('tanggal', 'Tanggal '.FormatTanggal::tanggal($hari).' di luar jadwal pelatihan.');
            }

            return $hari;
        }, $tanggal);
    }

    /**
     * Satu berkas Excel, satu sheet per lembar.
     *
     * @param  list<DataExport>  $lembar
     */
    public function unduhExcel(array $lembar, string $namaFile, Model $subjek): StreamedResponse
    {
        $this->catatUnduhan($subjek, "{$namaFile}.xlsx", $lembar);

        return response()->streamDownload(function () use ($lembar): void {
            $opsi = new Options;
            $opsi->setPageSetup(new PageSetup(PageOrientation::PORTRAIT, PaperSize::A4, fitToWidth: 1));
            $writer = new Writer($opsi);
            $writer->openToFile('php://output');
            $tebal = (new Style)->setFontBold();

            foreach ($lembar as $i => $data) {
                if ($i > 0) {
                    $writer->addNewSheetAndMakeItCurrent();
                }

                $writer->getCurrentSheet()->setName($data['namaLembar']);
                $writer->addRow(Row::fromValues([$data['judul']], $tebal));

                foreach ($data['keterangan'] as $baris) {
                    $writer->addRow(Row::fromValues([$baris]));
                }

                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues($data['kolom'], $tebal));

                foreach ($data['baris'] as $baris) {
                    $writer->addRow(Row::fromValues($baris));
                }
            }

            $writer->close();
        }, "{$namaFile}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Satu berkas PDF, satu halaman per lembar.
     *
     * @param  list<DataExport>  $lembar
     */
    public function unduhPdf(array $lembar, string $namaFile, Model $subjek): StreamedResponse
    {
        $this->catatUnduhan($subjek, "{$namaFile}.pdf", $lembar);

        $pdf = Pdf::loadView('export.tabel', ['lembar' => $lembar, 'dicetak' => now()->locale('id')->translatedFormat('j F Y, H:i')])
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print ($pdf->output()), "{$namaFile}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Unduhan berisi data pribadi tercatat di audit log (FR-AUD-02).
     */
    /**
     * @param  list<DataExport>  $lembar
     */
    private function catatUnduhan(Model $subjek, string $namaFile, array $lembar): void
    {
        activity('unduhan')
            ->performedOn($subjek)
            ->causedBy(auth()->user())
            ->event('unduh')
            ->withProperties(['berkas' => $namaFile, 'lembar' => array_column($lembar, 'namaLembar')])
            ->log("Mengunduh {$namaFile}");
    }
}
