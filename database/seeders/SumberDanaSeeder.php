<?php

namespace Database\Seeders;

use App\Models\SumberDana;
use Illuminate\Database\Seeder;

/**
 * Sumber dana pendaftaran. "Lainnya" mewajibkan keterangan (FR-REG-04).
 * Aman dijalankan ulang: baris yang sudah ada tidak diubah.
 */
class SumberDanaSeeder extends Seeder
{
    public const DATA = [
        'APBN' => false,
        'APBD' => false,
        'APBDes' => false,
        'Mandiri' => false,
        'Lainnya' => true,
    ];

    public function run(): void
    {
        foreach (self::DATA as $nama => $butuhKeterangan) {
            SumberDana::firstOrCreate(['nama' => $nama], ['butuh_keterangan' => $butuhKeterangan]);
        }
    }
}
