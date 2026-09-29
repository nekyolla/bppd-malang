<?php

namespace Database\Seeders;

use App\Models\Desa;
use App\Models\KabKota;
use App\Models\Kecamatan;
use App\Models\Provinsi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengisi provinsi, kab/kota, kecamatan, dan desa dari database/data/wilayah.csv
 * (satu baris per desa, kode Kemendagri berpemisah titik).
 *
 * Aman dijalankan ulang: baris dicocokkan lewat kode, nama diperbarui, tidak ada yang dihapus.
 */
class WilayahSeeder extends Seeder
{
    public const KOLOM = [
        'kode_provinsi', 'nama_provinsi',
        'kode_kab_kota', 'nama_kab_kota',
        'kode_kecamatan', 'nama_kecamatan',
        'kode_desa', 'nama_desa',
    ];

    /**
     * Urutan tingkat: [model, kolom kode, kolom nama, foreign key induk, kolom kode induk].
     *
     * @var list<array{class-string<Model>, string, string, ?string, ?string}>
     */
    private const TINGKAT = [
        [Provinsi::class, 'kode_provinsi', 'nama_provinsi', null, null],
        [KabKota::class, 'kode_kab_kota', 'nama_kab_kota', 'provinsi_id', 'kode_provinsi'],
        [Kecamatan::class, 'kode_kecamatan', 'nama_kecamatan', 'kab_kota_id', 'kode_kab_kota'],
        [Desa::class, 'kode_desa', 'nama_desa', 'kecamatan_id', 'kode_kecamatan'],
    ];

    public function run(?string $path = null): void
    {
        $path ??= database_path('data/wilayah.csv');
        $baris = $this->baca($path);

        DB::transaction(function () use ($baris) {
            $idInduk = [];

            foreach (self::TINGKAT as [$model, $kolomKode, $kolomNama, $foreignKey, $kolomKodeInduk]) {
                $data = [];

                foreach ($baris as $b) {
                    $data[$b[$kolomKode]] = ['kode' => $b[$kolomKode], 'nama' => $b[$kolomNama]]
                        + ($foreignKey ? [$foreignKey => $idInduk[$b[$kolomKodeInduk]]] : []);
                }

                $diperbarui = array_values(array_filter(['nama', $foreignKey]));

                foreach (array_chunk($data, 1000) as $potongan) {
                    $model::upsert($potongan, ['kode'], $diperbarui);
                }

                $idInduk = $model::pluck('id', 'kode')->all();
            }
        });

        $this->command?->info(count($baris).' desa dimuat dari '.basename($path).'.');
    }

    /**
     * @return list<array<string, string>>
     */
    private function baca(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Berkas wilayah tidak dapat dibuka: {$path}");
        }

        $file = fopen($path, 'r');

        if (fgetcsv($file, escape: '') !== self::KOLOM) {
            throw new RuntimeException('Header berkas wilayah harus: '.implode(',', self::KOLOM));
        }

        $baris = [];

        while (($kolom = fgetcsv($file, escape: '')) !== false) {
            if ($kolom === [null]) {
                continue;
            }

            $baris[] = array_combine(self::KOLOM, array_map(trim(...), $kolom));
        }

        fclose($file);

        return $baris;
    }
}
