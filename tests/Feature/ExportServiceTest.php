<?php

use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Services\ExportService;

beforeEach(function () {
    $this->service = app(ExportService::class);
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-11-02', '2026-11-04')->create();
});

it('menyusun daftar hadir kelas tanpa NIK dengan satu kolom tanda tangan per hari', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
    $budi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Budi']))->create(['kelas_pelatihan_id' => $kelas->id]);
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Ani']))->create(['kelas_pelatihan_id' => $kelas->id]);
    PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $kelas->id]);

    $data = $this->service->daftarHadirKelas($kelas);

    expect($data['kolom'])->toBe(['No', 'Nama', 'Jabatan', 'Desa', 'Sen, 2/11', 'Sel, 3/11', 'Rab, 4/11'])
        ->and($data['kolomTtd'])->toBe(3)
        ->and(array_column($data['baris'], 1))->toBe(['Ani', 'Budi'])
        ->and($data['baris'][1][3])->toBe("{$budi->desaSaatPelatihan->nama}, {$budi->desaSaatPelatihan->kecamatan->kabKota->nama}")
        ->and($data['keterangan'][1])->toStartWith('Kelas A')
        ->and(json_encode($data))->not->toContain($budi->peserta->nik);
});
