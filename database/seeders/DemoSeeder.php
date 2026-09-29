<?php

namespace Database\Seeders;

use App\Models\Asrama;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KategoriPelatihan;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk lingkungan lokal saja. Data asli jabatan, kategori,
 * judul pelatihan, asrama & kamar diisi klien lewat CRUD.
 */
class DemoSeeder extends Seeder
{
    /**
     * Mengacu Permendagri 84/2015 tentang SOTK Pemerintah Desa.
     */
    public const JABATAN = [
        'Kepala Desa', 'Sekretaris Desa', 'Kaur Tata Usaha dan Umum', 'Kaur Keuangan',
        'Kaur Perencanaan', 'Kasi Pemerintahan', 'Kasi Kesejahteraan', 'Kasi Pelayanan', 'Kepala Dusun',
    ];

    public const JUDUL = [
        'Teknis' => ['Pelatihan Pengelolaan Keuangan Desa', 'Pelatihan Penyusunan RPJMDes'],
        'Manajerial' => ['Pelatihan Kepemimpinan Kepala Desa'],
        'Kepatuhan Administrasi' => ['Pelatihan Administrasi Pemerintahan Desa'],
    ];

    /**
     * Nama asrama => [jumlah kamar, kapasitas per kamar].
     */
    public const ASRAMA = [
        'Anggrek' => [10, 4],
        'Melati' => [6, 2],
    ];

    public function run(): void
    {
        foreach (self::JABATAN as $urutan => $nama) {
            Jabatan::firstOrCreate(['nama_jabatan' => $nama], ['urutan' => $urutan + 1]);
        }

        foreach (self::JUDUL as $kategori => $daftarJudul) {
            $kategori = KategoriPelatihan::firstOrCreate(['nama_kategori' => $kategori]);

            foreach ($daftarJudul as $judul) {
                JudulPelatihan::firstOrCreate(['kategori_pelatihan_id' => $kategori->id, 'judul' => $judul]);
            }
        }

        foreach (self::ASRAMA as $nama => [$jumlahKamar, $kapasitas]) {
            $asrama = Asrama::firstOrCreate(['nama_asrama' => $nama]);

            foreach (range(1, $jumlahKamar) as $nomor) {
                $asrama->kamar()->firstOrCreate(
                    ['no_kamar' => str_pad((string) $nomor, 2, '0', STR_PAD_LEFT)],
                    ['kapasitas' => $kapasitas],
                );
            }
        }
    }
}
