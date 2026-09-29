<?php

namespace Database\Seeders;

use App\Models\StatusPtkp;
use Illuminate\Database\Seeder;

/**
 * Status PTKP untuk pajak uang saku. Aman dijalankan ulang: baris yang sudah
 * ada (termasuk yang dinonaktifkan klien) tidak diubah.
 */
class StatusPtkpSeeder extends Seeder
{
    public const DATA = [
        'TK/0' => 'Tidak kawin, tanpa tanggungan',
        'TK/1' => 'Tidak kawin, 1 tanggungan',
        'TK/2' => 'Tidak kawin, 2 tanggungan',
        'TK/3' => 'Tidak kawin, 3 tanggungan',
        'K/0' => 'Kawin, tanpa tanggungan',
        'K/1' => 'Kawin, 1 tanggungan',
        'K/2' => 'Kawin, 2 tanggungan',
        'K/3' => 'Kawin, 3 tanggungan',
    ];

    public function run(): void
    {
        foreach (self::DATA as $kode => $nama) {
            StatusPtkp::firstOrCreate(['kode' => $kode], ['nama' => $nama]);
        }
    }
}
