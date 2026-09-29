<?php

/**
 * Data peta (resources/geo) harus cocok dengan data wilayah (database/data/wilayah.csv).
 * Jika data wilayah berubah, test ini menandai wilayah yang belum punya geometri.
 */
beforeEach(function () {
    $file = fopen(__DIR__.'/../../database/data/wilayah.csv', 'r');
    $header = fgetcsv($file, escape: '');
    $this->kabKota = [];

    while (($kolom = fgetcsv($file, escape: '')) !== false) {
        $baris = array_combine($header, $kolom);
        $this->kabKota[$baris['kode_kab_kota']] = $baris['kode_provinsi'];
    }

    fclose($file);

    $this->geo = fn (string $berkas): array => json_decode(file_get_contents(__DIR__."/../../resources/geo/{$berkas}"), true)['features'];
});

it('punya geometri provinsi untuk setiap provinsi di data wilayah', function () {
    $kode = array_column(array_column(($this->geo)('provinsi.json'), 'properties'), 'kode');

    expect(array_diff(array_unique($this->kabKota), $kode))->toBe([]);
});

it('punya satu geometri kab/kota untuk setiap kab/kota di data wilayah, di berkas provinsinya', function () {
    $hilang = [];

    foreach (array_unique($this->kabKota) as $provinsi) {
        $kode = array_filter(array_column(array_column(($this->geo)("kab-kota/{$provinsi}.json"), 'properties'), 'kode'));

        expect(array_unique($kode))->toHaveCount(count($kode), "kode ganda di kab-kota/{$provinsi}.json");

        foreach (array_keys(array_filter($this->kabKota, fn ($p) => $p === $provinsi)) as $kabKota) {
            if (! in_array($kabKota, $kode, true)) {
                $hilang[] = $kabKota;
            }
        }
    }

    expect($hilang)->toBe([]);
});

it('tidak menyimpan kab/kota di luar data wilayah dengan kode', function () {
    foreach (glob(__DIR__.'/../../resources/geo/kab-kota/*.json') as $berkas) {
        foreach (($this->geo)('kab-kota/'.basename($berkas)) as $fitur) {
            $kode = $fitur['properties']['kode'];

            expect($kode === null || array_key_exists($kode, $this->kabKota))->toBeTrue("kode {$kode} tidak ada di data wilayah")
                ->and($kode === null ? filled($fitur['properties']['keterangan'] ?? null) : true)->toBeTrue();
        }
    }
});
