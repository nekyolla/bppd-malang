<?php

use App\Models\Desa;
use App\Models\KabKota;
use App\Models\Kecamatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\Provinsi;
use App\Services\StatistikService;

beforeEach(function () {
    $this->service = app(StatistikService::class);

    $this->jatim = Provinsi::factory()->create(['kode' => '35', 'nama' => 'Jawa Timur']);
    $this->bali = Provinsi::factory()->create(['kode' => '51', 'nama' => 'Bali']);
    $this->malang = KabKota::factory()->for($this->jatim)->create(['nama' => 'Kab. Malang']);
    $this->batu = KabKota::factory()->for($this->jatim)->create(['nama' => 'Kota Batu']);
    $this->badung = KabKota::factory()->for($this->bali)->create(['nama' => 'Kab. Badung']);

    $desa = fn (KabKota $kab, string $nama) => Desa::factory()->for(Kecamatan::factory()->for($kab))->create(['nama' => $nama]);
    $this->kedungsalam = $desa($this->malang, 'Kedungsalam');
    $this->banjarejo = $desa($this->malang, 'Banjarejo');
    $this->oro = $desa($this->batu, 'Oro-oro Ombo');
    $this->kuta = $desa($this->badung, 'Kuta');

    $this->pelatihan2026 = Pelatihan::factory()->berjalan()->tanggal('2026-10-05', '2026-10-08')->create();
    $this->pelatihan2025 = Pelatihan::factory()->selesai()->tanggal('2025-05-05', '2025-05-08')->create();

    $this->daftar = fn (Desa $desa, Pelatihan $pelatihan, ?string $state = null) => ($state ? PelatihanPeserta::factory()->{$state}() : PelatihanPeserta::factory())
        ->for($pelatihan)
        ->for(Peserta::factory()->for($desa))
        ->create();

    // Terlatih: 2 peserta Kedungsalam (2026), 1 peserta Oro-oro Ombo (2025).
    ($this->daftar)($this->kedungsalam, $this->pelatihan2026, 'selesai');
    ($this->daftar)($this->kedungsalam, $this->pelatihan2026, 'selesai');
    ($this->daftar)($this->oro, $this->pelatihan2025, 'selesai');
    // Belum terlatih: terverifikasi, menunggu, dan batal tidak dihitung.
    ($this->daftar)($this->banjarejo, $this->pelatihan2026, 'terverifikasi');
    ($this->daftar)($this->banjarejo, $this->pelatihan2026);
    ($this->daftar)($this->kuta, $this->pelatihan2026, 'batal');
});

it('meringkas pendaftar dan wilayah terlatih untuk semua tahun', function () {
    expect($this->service->ringkasan())->toBe([
        'pendaftar' => 5,
        'per_status' => ['terdaftar' => 1, 'terverifikasi' => 1, 'selesai' => 3, 'batal' => 1],
        'menunggu' => 1,
        'peserta_terlatih' => 3,
        'desa_terlatih' => 2,
        'total_desa' => 4,
        'kab_kota_terlatih' => 2,
        'total_kab_kota' => 3,
    ]);
});

it('menyaring statistik per tahun anggaran', function () {
    $ringkasan = $this->service->ringkasan(2026);

    expect($ringkasan['pendaftar'])->toBe(4)
        ->and($ringkasan['peserta_terlatih'])->toBe(2)
        ->and($ringkasan['desa_terlatih'])->toBe(1)
        ->and($ringkasan['kab_kota_terlatih'])->toBe(1)
        ->and($this->service->ringkasan(2025)['desa_terlatih'])->toBe(1)
        ->and($this->service->ringkasan(2024)['desa_terlatih'])->toBe(0)
        ->and($this->service->daftarTahun())->toBe([2026, 2025]);
});

it('menghitung kab/kota dan desa terlatih per provinsi untuk peta', function () {
    $perProvinsi = $this->service->perProvinsi()->keyBy('kode');

    expect($perProvinsi['35'])->toMatchArray([
        'nama' => 'Jawa Timur',
        'total_kab_kota' => 2,
        'kab_kota_terlatih' => 2,
        'total_desa' => 3,
        'desa_terlatih' => 2,
        'desa_belum_terlatih' => 1,
        'peserta_terlatih' => 3,
    ])->and($perProvinsi['51'])->toMatchArray([
        'total_kab_kota' => 1,
        'kab_kota_terlatih' => 0,
        'total_desa' => 1,
        'desa_terlatih' => 0,
        'desa_belum_terlatih' => 1,
    ])->and($this->service->perProvinsi(2025)->keyBy('kode')['35']['kab_kota_terlatih'])->toBe(1);
});

it('merinci kab/kota dan desa terlatih tanpa nama peserta', function () {
    $perKab = $this->service->perKabKota($this->jatim->id)->keyBy('nama');

    expect($perKab['Kab. Malang'])->toMatchArray(['total_desa' => 2, 'desa_terlatih' => 1, 'desa_belum_terlatih' => 1, 'peserta_terlatih' => 2])
        ->and($perKab['Kota Batu'])->toMatchArray(['total_desa' => 1, 'desa_terlatih' => 1])
        ->and($this->service->desaTerlatih($this->malang->id)->all())->toBe([[
            'id' => $this->kedungsalam->id,
            'kode' => $this->kedungsalam->kode,
            'nama' => 'Kedungsalam',
            'kecamatan' => $this->kedungsalam->kecamatan->nama,
            'peserta_terlatih' => 2,
        ]])
        ->and($this->service->desaTerlatih($this->badung->id))->toBeEmpty();
});

it('memakai desa saat pelatihan, bukan desa peserta sekarang', function () {
    Peserta::query()->whereHas('pendaftaran', fn ($q) => $q->where('pelatihan_id', $this->pelatihan2025->id))
        ->update(['desa_id' => $this->kuta->id]);

    expect($this->service->perProvinsi()->keyBy('kode')['51']['desa_terlatih'])->toBe(0)
        ->and($this->service->desaTerlatih($this->batu->id)->pluck('nama')->all())->toBe(['Oro-oro Ombo']);
});

it('menghitung ulang setelah pendaftaran berubah', function () {
    expect($this->service->ringkasan()['desa_terlatih'])->toBe(2);

    ($this->daftar)($this->kuta, $this->pelatihan2026, 'selesai');

    expect($this->service->ringkasan()['desa_terlatih'])->toBe(3)
        ->and($this->service->perProvinsi()->keyBy('kode')['51']['kab_kota_terlatih'])->toBe(1);
});
