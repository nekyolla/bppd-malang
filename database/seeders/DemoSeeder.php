<?php

namespace Database\Seeders;

use App\Models\Asrama;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KategoriPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        $this->pelatihanDanPendaftar();
    }

    /**
     * Pelatihan dan pendaftar contoh agar dashboard & peta berisi data. Hanya dibuat
     * jika belum ada pelatihan sama sekali; peserta tersebar di desa dari data wilayah.
     */
    private function pelatihanDanPendaftar(): void
    {
        if (Pelatihan::query()->exists() || Desa::query()->doesntExist()) {
            return;
        }

        $this->call([StatusPtkpSeeder::class, SumberDanaSeeder::class]);

        $judul = JudulPelatihan::query()->orderBy('id')->get();
        $master = fn () => [
            'jabatan_id' => Jabatan::query()->inRandomOrder()->value('id'),
            'status_ptkp_id' => StatusPtkp::query()->inRandomOrder()->value('id'),
        ];
        $sumberDana = SumberDana::query()->where('butuh_keterangan', false)->value('id');
        // Pakai akun yang sudah ada (superadmin) agar tidak menambah akun palsu.
        $admin = User::query()->value('id') ?? User::factory()->create()->id;

        $lalu = Pelatihan::factory()->selesai()->tanggal('2025-06-02', '2025-06-05')->create(['judul_pelatihan_id' => $judul[0]->id]);
        $kini = Pelatihan::factory()->dibuka()->tanggal('2026-11-02', '2026-11-05')->create(['judul_pelatihan_id' => $judul[1]->id ?? $judul[0]->id]);

        // Satu kab/kota per provinsi, beberapa desa per kab/kota.
        $desa = Desa::query()
            ->whereIn('kecamatan_id', fn ($q) => $q->select('id')->from('kecamatan')->whereIn('kab_kota_id', fn ($q) => $q->select(DB::raw('min(id)'))->from('kab_kota')->groupBy('provinsi_id')))
            ->inRandomOrder()
            ->limit(80)
            ->pluck('id');

        foreach ($desa as $i => $desaId) {
            $pelatihan = $i % 3 === 0 ? $kini : $lalu;
            $factory = PelatihanPeserta::factory()->for($pelatihan);
            $factory = match (true) {
                $pelatihan->is($lalu) => $factory->selesai(),
                $i % 2 === 0 => $factory->terverifikasi(),
                default => $factory,
            };

            $factory->create([
                'peserta_id' => Peserta::factory()->create([...$master(), 'desa_id' => $desaId])->id,
                'sumber_dana_id' => $sumberDana,
                ...($pelatihan->is($lalu) || $i % 2 === 0 ? ['diverifikasi_oleh' => $admin] : []),
            ]);
        }
    }
}
