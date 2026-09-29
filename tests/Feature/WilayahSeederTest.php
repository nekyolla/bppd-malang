<?php

use App\Models\Desa;
use App\Models\KabKota;
use App\Models\Kecamatan;
use App\Models\Provinsi;
use Database\Seeders\WilayahSeeder;

function csvWilayah(array $baris): string
{
    $path = tempnam(sys_get_temp_dir(), 'wilayah');
    $file = fopen($path, 'w');

    fputcsv($file, WilayahSeeder::KOLOM, escape: '');

    foreach ($baris as $b) {
        fputcsv($file, $b, escape: '');
    }

    fclose($file);

    return $path;
}

function seedWilayah(string $path): void
{
    app(WilayahSeeder::class)(['path' => $path]);
}

beforeEach(function () {
    $this->baris = [
        ['35', 'Jawa Timur', '35.07', 'Kab. Malang', '35.07.01', 'Donomulyo', '35.07.01.2001', 'Kedungsalam'],
        ['35', 'Jawa Timur', '35.07', 'Kab. Malang', '35.07.01', 'Donomulyo', '35.07.01.2002', 'Banjarejo'],
        ['35', 'Jawa Timur', '35.29', 'Kab. Sumenep', '35.29.22', "Ra'as", '35.29.22.2001', 'Brakas'],
        ['91', 'Papua', '91.03', 'Kab. Jayapura', '91.03.01', 'Sentani', '91.03.01.3007', 'Desa Adat Yoboi'],
    ];
});

it('memuat hierarki wilayah dari csv', function () {
    seedWilayah(csvWilayah($this->baris));

    expect(Provinsi::count())->toBe(2)
        ->and(KabKota::count())->toBe(3)
        ->and(Kecamatan::count())->toBe(3)
        ->and(Desa::count())->toBe(4);

    $desa = Desa::where('kode', '35.29.22.2001')->firstOrFail();

    expect($desa->nama)->toBe('Brakas')
        ->and($desa->kecamatan->nama)->toBe("Ra'as")
        ->and($desa->kecamatan->kabKota->kode)->toBe('35.29')
        ->and($desa->kecamatan->kabKota->provinsi->nama)->toBe('Jawa Timur');

    expect(Kecamatan::where('kode', '35.07.01')->firstOrFail()->desa)->toHaveCount(2);
});

it('dapat dijalankan ulang tanpa duplikasi dan memperbarui nama', function () {
    seedWilayah(csvWilayah($this->baris));

    $idDesa = Desa::where('kode', '35.07.01.2001')->value('id');

    $this->baris[0][7] = 'Kedung Salam';
    $this->baris[] = ['35', 'Jawa Timur', '35.07', 'Kab. Malang', '35.07.01', 'Donomulyo', '35.07.01.2003', 'Purwodadi'];

    seedWilayah(csvWilayah($this->baris));

    expect(Provinsi::count())->toBe(2)
        ->and(Desa::count())->toBe(5)
        ->and(Desa::find($idDesa)->nama)->toBe('Kedung Salam');
});

it('tidak menghapus wilayah yang tidak ada lagi di csv', function () {
    seedWilayah(csvWilayah($this->baris));
    seedWilayah(csvWilayah(array_slice($this->baris, 0, 1)));

    expect(Desa::count())->toBe(4);
});

it('menolak csv dengan header yang salah', function () {
    $path = tempnam(sys_get_temp_dir(), 'wilayah');
    file_put_contents($path, "kode,nama\n35,Jawa Timur\n");

    seedWilayah($path);
})->throws(RuntimeException::class, 'Header berkas wilayah');

it('membuat wilayah lewat factory dengan kode yang mengikuti induknya', function () {
    $desa = Desa::factory()->count(3)->create();

    $desa->each(function (Desa $d) {
        expect($d->kode)->toMatch('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/')
            ->toStartWith($d->kecamatan->kode.'.')
            ->and($d->kecamatan->kode)->toStartWith($d->kecamatan->kabKota->kode.'.')
            ->and($d->kecamatan->kabKota->kode)->toStartWith($d->kecamatan->kabKota->provinsi->kode.'.');
    });
});
