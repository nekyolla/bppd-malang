<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Asrama;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KategoriPelatihan;
use App\Models\User;
use App\Services\AkunService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk lingkungan lokal (UAT & demo) saja. Data asli jabatan, kategori,
 * judul pelatihan, asrama & kamar diisi klien lewat CRUD; server produksi hanya menjalankan
 * seeder produksi (akun superadmin, wilayah, PTKP, sumber dana).
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

    /**
     * Jabatan yang memiliki periode jabatan (PF-25).
     */
    public const JABATAN_BERPERIODE = ['Kepala Desa'];

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

    /**
     * Akun admin contoh untuk mencoba menu role admin. Kata sandi sama dengan username
     * lingkungan lokal Laravel pada umumnya.
     */
    public const AKUN_PANITIA = ['panitia1', 'panitia2'];

    public const KATA_SANDI_PANITIA = 'password';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoSeeder hanya untuk lingkungan lokal.');

            return;
        }

        // DatabaseSeeder memakai WithoutModelEvents; data contoh butuh event model untuk
        // audit log, cache statistik, dan memo jabatan berperiode.
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            // Seeder produksi yang dibutuhkan data contoh; aman dijalankan ulang.
            $this->call([RoleSeeder::class, StatusPtkpSeeder::class, SumberDanaSeeder::class]);
            $this->dataMaster();
            $this->akunPanitia();
            $this->call(DemoPelatihanSeeder::class);
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
    }

    private function dataMaster(): void
    {
        foreach (self::JABATAN as $urutan => $nama) {
            Jabatan::firstOrCreate(['nama_jabatan' => $nama], [
                'urutan' => $urutan + 1,
                'memiliki_periode' => in_array($nama, self::JABATAN_BERPERIODE, true),
            ]);
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

    private function akunPanitia(): void
    {
        foreach (self::AKUN_PANITIA as $username) {
            if (User::query()->where('username', $username)->doesntExist()) {
                app(AkunService::class)->buat([
                    'username' => $username,
                    'peran' => Peran::Admin,
                    'password' => self::KATA_SANDI_PANITIA,
                ]);
            }
        }
    }
}
