<?php

use App\Enums\JenisKelamin;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(ExportService::class);
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-11-02', '2026-11-04')->create();
    $this->kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
});

/**
 * @return array<string, list<string>>
 */
function galatExport(Closure $aksi): array
{
    try {
        $aksi();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('menyusun satu lembar per hari terpilih dengan kolom L/P dan tanda tangan selang-seling', function () {
    $budi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Budi', 'jenis_kelamin' => JenisKelamin::LakiLaki]))
        ->create(['kelas_pelatihan_id' => $this->kelas->id]);
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Ani', 'jenis_kelamin' => JenisKelamin::Perempuan]))
        ->create(['kelas_pelatihan_id' => $this->kelas->id]);
    PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelas->id]);

    $lembar = $this->service->daftarHadirKelas($this->kelas, ['2026-11-04', '2026-11-02', '2026-11-02']);
    $senin = $lembar[0];

    expect($lembar)->toHaveCount(2)
        ->and(array_column($lembar, 'namaLembar'))->toBe(['Sen 2 Nov', 'Rab 4 Nov'])
        ->and($senin['keterangan'][1])->toStartWith('Kelas A')
        ->and($senin['keterangan'][2])->toBe('Hari/tanggal: Senin, 2 November 2026')
        ->and($senin['kolom'])->toBe(['No', 'Nama', 'L/P', 'Jabatan', 'Desa', 'Tanda tangan', ''])
        ->and($senin['kolomTtd'])->toBe(2)
        ->and(array_column($senin['baris'], 1))->toBe(['Ani', 'Budi'])
        ->and($senin['baris'][0][2])->toBe('P')
        ->and($senin['baris'][1][2])->toBe('L')
        ->and($senin['baris'][1][4])->toBe("{$budi->desaSaatPelatihan->nama}, {$budi->desaSaatPelatihan->kecamatan->kabKota->nama}")
        ->and(array_slice($senin['baris'][0], 5))->toBe(['1.', ''])
        ->and(array_slice($senin['baris'][1], 5))->toBe(['', '2.'])
        ->and(json_encode($lembar))->not->toContain($budi->peserta->nik);
});

it('menolak tanggal di luar jadwal atau pilihan kosong', function () {
    expect(galatExport(fn () => $this->service->daftarHadirKelas($this->kelas, ['2026-11-05']))['tanggal'][0])
        ->toBe('Tanggal 5 November 2026 di luar jadwal pelatihan.')
        ->and(galatExport(fn () => $this->service->daftarHadirKelas($this->kelas, []))['tanggal'][0])
        ->toBe('Pilih minimal satu hari.');
});

it('tetap membuat lembar untuk kelas tanpa peserta aktif', function () {
    $lembar = $this->service->daftarHadirKelas($this->kelas, ['2026-11-02', '2026-11-03']);

    expect($lembar)->toHaveCount(2)
        ->and($lembar[0]['baris'])->toBe([])
        ->and($lembar[1]['baris'])->toBe([]);
});

it('menawarkan pilihan hari sepanjang pelatihan', function () {
    expect($this->service->pilihanHari($this->pelatihan))->toBe([
        '2026-11-02' => 'Sen, 2 Nov',
        '2026-11-03' => 'Sel, 3 Nov',
        '2026-11-04' => 'Rab, 4 Nov',
    ]);
});

it('mencetak PDF tepat satu halaman per hari tanpa halaman kosong', function () {
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelas->id]);
    $this->actingAs(User::factory()->create());

    $pdf = $this->service->unduhPdf($this->service->daftarHadirKelas($this->kelas, ['2026-11-02', '2026-11-03']), 'uji', $this->kelas);

    ob_start();
    $pdf->sendContent();
    $isi = ob_get_clean();

    expect($isi)->toContain('/Count 2')->not->toContain('/Count 3');
});
