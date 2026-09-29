<?php

use Database\Seeders\WilayahSeeder;

beforeEach(function () {
    $file = fopen(__DIR__.'/../../database/data/wilayah.csv', 'r');

    $this->header = fgetcsv($file, escape: '');
    $this->baris = [];

    while (($kolom = fgetcsv($file, escape: '')) !== false) {
        $this->baris[] = array_combine($this->header, $kolom);
    }

    fclose($file);
});

it('memakai header yang dibaca WilayahSeeder', function () {
    expect($this->header)->toBe(WilayahSeeder::KOLOM);
});

it('memuat 18 provinsi sesuai PRD', function () {
    expect(array_unique(array_column($this->baris, 'kode_provinsi')))->toHaveCount(18);
});

it('hanya memuat desa dan desa adat, tanpa kelurahan', function () {
    $salah = array_filter(
        array_column($this->baris, 'kode_desa'),
        fn ($kode) => ! preg_match('/^\d{2}\.\d{2}\.\d{2}\.[23]\d{3}$/', $kode),
    );

    expect($salah)->toBeEmpty();
});

it('memiliki kode desa unik dan hierarki kode yang konsisten', function () {
    expect(array_unique(array_column($this->baris, 'kode_desa')))->toHaveCount(count($this->baris));

    $salah = array_filter($this->baris, fn ($b) => ! preg_match('/^\d{2}$/', $b['kode_provinsi'])
        || ! str_starts_with($b['kode_kab_kota'], $b['kode_provinsi'].'.')
        || strlen($b['kode_kab_kota']) !== 5
        || ! str_starts_with($b['kode_kecamatan'], $b['kode_kab_kota'].'.')
        || strlen($b['kode_kecamatan']) !== 8
        || ! str_starts_with($b['kode_desa'], $b['kode_kecamatan'].'.'));

    expect($salah)->toBeEmpty();
});

it('memberi satu nama untuk setiap kode', function (string $kolomKode, string $kolomNama) {
    $nama = [];

    foreach ($this->baris as $b) {
        $nama[$b[$kolomKode]][$b[$kolomNama]] = true;
    }

    expect(array_filter($nama, fn ($n) => count($n) > 1))->toBeEmpty();
})->with([
    ['kode_provinsi', 'nama_provinsi'],
    ['kode_kab_kota', 'nama_kab_kota'],
    ['kode_kecamatan', 'nama_kecamatan'],
]);

it('tidak memuat nama kosong atau berspasi ganda', function () {
    $salah = array_filter($this->baris, fn ($b) => array_filter(
        $b,
        fn ($nilai) => $nilai === '' || $nilai !== trim($nilai) || str_contains($nilai, '  '),
    ));

    expect($salah)->toBeEmpty();
});
