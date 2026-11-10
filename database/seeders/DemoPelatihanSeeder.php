<?php

namespace Database\Seeders;

use App\Enums\JenisKelamin;
use App\Enums\Peran;
use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\TipeLokasi;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Models\User;
use App\Services\AsramaService;
use App\Services\KelasService;
use App\Services\PelatihanService;
use App\Services\PendaftaranService;
use App\Services\RegistrasiService;
use App\Services\SertifikatService;
use App\Support\IsianPeserta;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Skenario pelatihan untuk UAT & demo (lokal saja), dengan tanggal relatif terhadap hari
 * seeder dijalankan. Setiap langkah lewat service dengan waktu dimundurkan, sehingga aturan
 * bisnis, snapshot, dan audit log sama seperti pemakaian sungguhan:
 *
 * - tahun lalu, selesai: dashboard per tahun;
 * - bulan lalu, selesai: kelas, kamar, sertifikat, 2 batal;
 * - minggu ini, berjalan: kelas, kamar, 1 batal, sebagian sertifikat, pendaftar terlambat;
 * - bulan depan, dibuka: bahan UAT (menunggu verifikasi, NIK lama dengan isian berbeda, kepala
 *   desa, satu data iseng, terverifikasi tanpa kelas & kamar);
 * - draft di luar Balai Besar.
 */
class DemoPelatihanSeeder extends Seeder
{
    /**
     * Bobot pemilihan acak: nilai => bobot.
     */
    private const BOBOT_JABATAN = [
        'Kepala Desa' => 15, 'Sekretaris Desa' => 25, 'Kaur Keuangan' => 20, 'Kaur Perencanaan' => 10,
        'Kaur Tata Usaha dan Umum' => 8, 'Kasi Pemerintahan' => 8, 'Kasi Kesejahteraan' => 5,
        'Kasi Pelayanan' => 5, 'Kepala Dusun' => 4,
    ];

    private const BOBOT_JENJANG = ['SMA/Sederajat' => 40, 'S1' => 35, 'D3' => 10, 'S2' => 7, 'SMP/Sederajat' => 8];

    private const BOBOT_AGAMA = ['Islam' => 85, 'Kristen' => 6, 'Katolik' => 3, 'Hindu' => 5, 'Buddha' => 1];

    private const BOBOT_SUMBER_DANA = ['APBDes' => 60, 'APBD' => 25, 'APBN' => 10, 'Lainnya' => 5];

    private const JURUSAN = ['Ilmu Pemerintahan', 'Administrasi Publik', 'Akuntansi', 'Manajemen', 'Hukum', 'Pendidikan Guru SD', 'Agribisnis'];

    private const ALASAN_BATAL = [
        'Sakit, ada surat keterangan dokter.',
        'Bertepatan dengan musyawarah desa.',
        'Ditugaskan kepala desa ke kegiatan lain.',
    ];

    private Generator $faker;

    private Carbon $sekarang;

    /** @var Collection<int, Desa> */
    private Collection $desaJatim;

    /** @var Collection<int, Desa> */
    private Collection $desaLain;

    /** @var list<User> */
    private array $panitia;

    /** @var array<string, true> */
    private array $nikTerpakai = [];

    public function run(): void
    {
        if (Pelatihan::query()->exists() || Desa::query()->doesntExist()) {
            return;
        }

        $this->faker = FakerFactory::create('id_ID');
        $this->faker->seed(2026);
        $this->sekarang = now();
        $this->panitia = User::role(Peran::Admin->value)->orderBy('id')->get()->all() ?: [User::query()->orderBy('id')->firstOrFail()];
        $this->pilihDesa();

        $waktuAsli = Carbon::getTestNow();

        try {
            $hariIni = today();
            $tahunLalu = $this->skenario('Pelatihan Administrasi Pemerintahan Desa', 1, $hariIni->copy()->subYear()->startOfWeek(), [
                'peserta' => 20, 'kelas' => ['A'], 'batal' => 1, 'sertifikat' => 'semua',
            ]);
            $bulanLalu = $this->skenario('Pelatihan Pengelolaan Keuangan Desa', 1, $hariIni->copy()->subDays(35)->startOfWeek(), [
                'peserta' => 24, 'kelas' => ['A', 'B'], 'batal' => 2, 'kamar' => true, 'sertifikat' => 'semua',
            ]);
            $this->skenario('Pelatihan Pengelolaan Keuangan Desa', 2, $hariIni->copy()->subDay(), [
                'peserta' => 28, 'kelas' => ['A', 'B'], 'batal' => 1, 'kamar' => true,
                'sertifikat' => 5, 'terlambat' => 2, 'nikLama' => $tahunLalu->take(2),
            ]);
            $this->pelatihanDibuka($hariIni->copy()->addDays(28), $tahunLalu->slice(2, 2)->merge($bulanLalu->take(1)));
            $this->pada($this->sekarang->copy()->subHours(2), $this->panitia[0], fn () => $this->buatPelatihan(
                'Pelatihan Kepemimpinan Kepala Desa', 1, $hariIni->copy()->addDays(56), TipeLokasi::Luar, 'Aula Kantor Kecamatan Kepanjen, Kab. Malang',
            ));
        } finally {
            Carbon::setTestNow($waktuAsli);
            Auth::forgetUser();
        }
    }

    /**
     * Menjalankan alur pelatihan dari dibuka sampai (bila tanggalnya sudah lewat) selesai.
     * Langkah yang jatuh setelah saat ini tidak dijalankan.
     *
     * @param  array{peserta: int, kelas: list<string>, batal: int, kamar?: bool, sertifikat: 'semua'|int, terlambat?: int, nikLama?: Collection<int, Peserta>}  $opsi
     * @return Collection<int, Peserta> Peserta yang selesai, untuk dipakai ulang sebagai NIK lama.
     */
    private function skenario(string $judul, int $batch, Carbon $mulai, array $opsi): Collection
    {
        $admin = $this->panitia[0];
        $pelatihan = $this->pada($mulai->copy()->subDays(30)->setTime(8, 0), $admin, function () use ($judul, $batch, $mulai): Pelatihan {
            $pelatihan = $this->buatPelatihan($judul, $batch, $mulai);
            app(PelatihanService::class)->ubahStatus($pelatihan, StatusPelatihan::Dibuka);

            return $pelatihan;
        });
        $selesai = $pelatihan->tanggal_selesai;

        $pendaftaran = $this->pendaftarBaru($pelatihan, $opsi['peserta'], $mulai->copy()->subDays(28), $mulai->copy()->subDays(8));

        foreach ($opsi['nikLama'] ?? [] as $i => $peserta) {
            $pendaftaran->push($this->pada($mulai->copy()->subDays(20)->setTime(10, $i * 7), null, fn () => $this->daftar($pelatihan, IsianPeserta::dariPeserta($peserta))));
        }

        $this->verifikasi($pendaftaran, $mulai->copy()->subDays(7)->setTime(9, 0));

        $this->pada($mulai->copy()->subDays(5)->setTime(10, 0), $admin, function () use ($pelatihan, $opsi): void {
            foreach ($opsi['kelas'] as $nama) {
                app(KelasService::class)->simpan($pelatihan, $nama);
            }

            app(KelasService::class)->bagiAcak($pelatihan);
        });

        $batal = collect($this->faker->randomElements($pendaftaran->all(), $opsi['batal']));

        foreach ($batal as $i => $dibatalkan) {
            $this->pada($mulai->copy()->subDays(4)->setTime(9, $i * 10), $admin, fn () => app(PendaftaranService::class)->batalkan($dibatalkan, $admin, self::ALASAN_BATAL[$i % count(self::ALASAN_BATAL)]));
        }

        if ($opsi['kamar'] ?? false) {
            $this->pada($mulai->copy()->subDays(2)->setTime(10, 0), $admin, fn () => app(AsramaService::class)->isiOtomatis($pelatihan, $admin));
        }

        if (! $this->sudahLewat($mulai->copy()->setTime(7, 30))) {
            return collect();
        }

        $this->pada($mulai->copy()->setTime(7, 30), $admin, fn () => app(PelatihanService::class)->ubahStatus($pelatihan, StatusPelatihan::Berjalan));

        if ($opsi['terlambat'] ?? 0) {
            $this->pendaftarBaru($pelatihan, $opsi['terlambat'], $mulai->copy()->setTime(8, 0), $this->sekarang->copy()->subMinutes(30));
        }

        $aktif = $pelatihan->pendaftaran()->where('status', StatusPendaftaran::Terverifikasi)->orderBy('id')->get();
        $waktuSertifikat = $selesai->copy()->setTime(14, 0)->min($this->sekarang->copy()->subMinutes(20));

        foreach ($opsi['sertifikat'] === 'semua' ? $aktif : $aktif->take($opsi['sertifikat']) as $i => $penerima) {
            $this->pada($waktuSertifikat->copy()->addSeconds($i * 20), $admin, fn () => app(SertifikatService::class)->unggah($penerima, $this->berkas('sertifikat.pdf'), $admin));
        }

        if (! $this->sudahLewat($selesai->copy()->setTime(16, 0))) {
            return collect();
        }

        $this->pada($selesai->copy()->setTime(16, 0), $admin, fn () => app(PelatihanService::class)->ubahStatus($pelatihan, StatusPelatihan::Selesai));

        return $aktif->map(fn (PelatihanPeserta $p): Peserta => $p->peserta);
    }

    /**
     * Pelatihan yang masih dibuka: bahan utama UAT verifikasi, kelas, dan kamar.
     *
     * @param  Collection<int, Peserta>  $nikLama  Peserta lama yang mendaftar lagi dengan isian berubah.
     */
    private function pelatihanDibuka(Carbon $mulai, Collection $nikLama): void
    {
        $admin = $this->panitia[0];
        $pelatihan = $this->pada($this->sekarang->copy()->subDays(12)->setTime(8, 0), $admin, function () use ($mulai): Pelatihan {
            $pelatihan = $this->buatPelatihan('Pelatihan Penyusunan RPJMDes', 1, $mulai);
            app(PelatihanService::class)->ubahStatus($pelatihan, StatusPelatihan::Dibuka);

            return $pelatihan;
        });

        $baru = $this->pendaftarBaru($pelatihan, 20, $this->sekarang->copy()->subDays(11), $this->sekarang->copy()->subDays(3));

        foreach (range(0, 1) as $i) {
            $this->pada($this->sekarang->copy()->subDays(2)->setTime(19, $i * 15), null, fn () => $this->daftar($pelatihan, $this->isian($this->desaAcak(), jabatan: 'Kepala Desa')));
        }

        // NIK lama: nomor HP & alamat berubah, sebagian naik jabatan, sehingga admin melihat perbedaan isian.
        foreach ($nikLama->values() as $i => $peserta) {
            $isian = [
                ...IsianPeserta::dariPeserta($peserta),
                'no_hp' => $this->noHp(),
                'alamat_domisili' => $this->alamatDomisili($peserta->desa),
                ...($i === 0 ? ['jabatan_id' => Jabatan::query()->where('nama_jabatan', 'Sekretaris Desa')->value('id'), 'periode_jabatan' => null] : []),
            ];
            $this->pada($this->sekarang->copy()->subDay()->setTime(8 + $i, 20), null, fn () => $this->daftar($pelatihan, $isian));
        }

        // Data iseng yang belum diverifikasi, untuk mencoba Hapus.
        $this->pada($this->sekarang->copy()->subHours(5), null, fn () => $this->daftar($pelatihan, [
            ...$this->isian($this->desaAcak()),
            'nama_lengkap' => 'Coba Coba',
            'alamat_domisili' => 'tes',
            'alamat_kantor_desa' => 'tes',
        ]));

        $this->verifikasi($baru->take(7), $this->sekarang->copy()->subDays(2)->setTime(9, 0));
    }

    /**
     * @return Collection<int, PelatihanPeserta>
     */
    private function pendaftarBaru(Pelatihan $pelatihan, int $jumlah, Carbon $dari, Carbon $sampai): Collection
    {
        $sampai = $sampai->min($this->sekarang);

        return collect(range(1, $jumlah))
            ->map(fn (): Carbon => Carbon::instance($this->faker->dateTimeBetween($dari, $sampai->max($dari))))
            ->sort()
            ->values()
            ->map(fn (Carbon $waktu): PelatihanPeserta => $this->pada($waktu, null, fn () => $this->daftar($pelatihan, $this->isian($this->desaAcak()))));
    }

    /**
     * @param  iterable<PelatihanPeserta>  $daftar
     */
    private function verifikasi(iterable $daftar, Carbon $mulai): void
    {
        foreach ($daftar as $i => $pendaftaran) {
            $admin = $this->panitia[$i % count($this->panitia)];
            $this->pada($mulai->copy()->addMinutes($i * 4), $admin, fn () => app(PendaftaranService::class)->verifikasi($pendaftaran, $admin));
        }
    }

    private function buatPelatihan(string $judul, int $batch, Carbon $mulai, TipeLokasi $lokasi = TipeLokasi::Bbpd, ?string $keterangan = null): Pelatihan
    {
        return app(PelatihanService::class)->simpan([
            'judul_pelatihan_id' => JudulPelatihan::query()->where('judul', $judul)->value('id'),
            'batch_ke' => $batch,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $mulai->copy()->addDays(3),
            'tipe_lokasi' => $lokasi,
            'keterangan_lokasi' => $keterangan,
        ]);
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function daftar(Pelatihan $pelatihan, array $isian): PelatihanPeserta
    {
        $sumberDana = SumberDana::query()->aktif()->where('nama', $this->acak(self::BOBOT_SUMBER_DANA))->first()
            ?? SumberDana::query()->aktif()->where('butuh_keterangan', false)->firstOrFail();

        return app(RegistrasiService::class)->daftar([
            ...$isian,
            'pelatihan_id' => $pelatihan->id,
            'sumber_dana_id' => $sumberDana->id,
            'sumber_dana_keterangan' => $sumberDana->butuh_keterangan ? 'Hibah pemerintah kabupaten' : null,
            'ktp' => $this->berkas('ktp.jpg'),
            'foto' => $this->berkas('foto.jpg'),
            'surat_tugas' => $this->berkas('surat-tugas.pdf'),
        ]);
    }

    /**
     * Isian form registrasi untuk orang baru di desa tertentu.
     *
     * @return array<string, mixed>
     */
    private function isian(Desa $desa, ?string $jabatan = null): array
    {
        $jenisKelamin = $this->faker->boolean(60) ? JenisKelamin::LakiLaki : JenisKelamin::Perempuan;
        $gender = $jenisKelamin === JenisKelamin::LakiLaki ? 'male' : 'female';
        $lahir = Carbon::instance($this->faker->dateTimeBetween(now()->subYears(56), now()->subYears(24)));
        $jabatan = Jabatan::query()->where('nama_jabatan', $jabatan ?? $this->acak(self::BOBOT_JABATAN))->firstOrFail();
        $jenjang = $this->acak(self::BOBOT_JENJANG);
        $nama = $this->faker->firstName($gender).' '.$this->faker->lastName($gender);

        return [
            'nik' => $this->nik($desa, $lahir, $jenisKelamin),
            'nama_lengkap' => $nama,
            'jenis_kelamin' => $jenisKelamin->value,
            'tempat_lahir' => preg_replace('/^(Kab\.|Kota)\s+/', '', $desa->kecamatan->kabKota->nama),
            'tanggal_lahir' => $lahir->toDateString(),
            'agama' => $this->acak(self::BOBOT_AGAMA),
            'alamat_domisili' => $this->alamatDomisili($desa),
            'no_hp' => $this->noHp(),
            'email' => $this->faker->boolean(40) ? Str::slug($nama, '.').$this->faker->numberBetween(1, 99).'@example.com' : null,
            'jenjang_pendidikan' => $jenjang,
            'jurusan_pendidikan' => Peserta::jenjangKuliah($jenjang) ? $this->faker->randomElement(self::JURUSAN) : null,
            'jabatan_id' => $jabatan->id,
            'periode_jabatan' => $jabatan->memiliki_periode ? $this->faker->randomElement(array_keys(Peserta::PERIODE_JABATAN)) : null,
            'waktu_pelantikan' => $this->faker->dateTimeBetween($lahir->copy()->addYears(21)->max(now()->subYears(9)), now()->subMonths(3))->format('Y-m-d'),
            'desa_id' => $desa->id,
            'alamat_kantor_desa' => sprintf('Jl. %s No. %d, Desa %s, Kec. %s', $this->faker->streetName(), $this->faker->numberBetween(1, 120), $desa->nama, $desa->kecamatan->nama),
            'npwp' => $this->faker->boolean(40) ? $this->faker->numerify('################') : null,
            'status_ptkp_id' => StatusPtkp::query()->aktif()->inRandomOrder()->value('id'),
        ];
    }

    /**
     * NIK palsu berpola asli: kode kecamatan desa, tanggal lahir (+40 untuk perempuan), nomor urut.
     */
    private function nik(Desa $desa, Carbon $lahir, JenisKelamin $jenisKelamin): string
    {
        $wilayah = str_pad(substr(preg_replace('/\D/', '', $desa->kode), 0, 6), 6, '0');
        $hari = $lahir->day + ($jenisKelamin === JenisKelamin::Perempuan ? 40 : 0);

        do {
            $nik = $wilayah.sprintf('%02d', $hari).$lahir->format('my').$this->faker->numerify('0###');
        } while (isset($this->nikTerpakai[$nik]) || Peserta::query()->where('nik', $nik)->exists());

        $this->nikTerpakai[$nik] = true;

        return $nik;
    }

    private function noHp(): string
    {
        return '08'.$this->faker->randomElement(['12', '13', '21', '52', '57', '77', '81', '95']).$this->faker->numerify('########');
    }

    private function alamatDomisili(Desa $desa): string
    {
        return sprintf('Dusun %s RT %03d/RW %03d, Desa %s', $this->faker->lastName(), $this->faker->numberBetween(1, 12), $this->faker->numberBetween(1, 6), $desa->nama);
    }

    /**
     * Sekitar 30 desa di Jawa Timur dan 12 desa dari 4 provinsi lain, supaya peta terpusat di
     * Jawa Timur tetapi tetap tersebar.
     */
    private function pilihDesa(): void
    {
        $diProvinsi = fn (bool $jatim) => fn ($query) => $query->where('kode', $jatim ? '=' : '!=', '35');

        $this->desaJatim = Desa::query()->with('kecamatan.kabKota')
            ->whereHas('kecamatan.kabKota.provinsi', $diProvinsi(true))
            ->inRandomOrder()->limit(30)->get();

        $this->desaLain = Desa::query()->with('kecamatan.kabKota')
            ->whereHas('kecamatan.kabKota.provinsi', $diProvinsi(false))
            ->inRandomOrder()->limit(200)->get()
            ->groupBy(fn (Desa $desa): int => $desa->kecamatan->kabKota->provinsi_id)
            ->take(4)
            ->flatMap(fn (Collection $desa): Collection => $desa->take(3))
            ->values();
    }

    private function desaAcak(): Desa
    {
        $jatim = $this->desaJatim->isNotEmpty() && ($this->desaLain->isEmpty() || $this->faker->boolean(75));

        return $this->faker->randomElement(($jatim ? $this->desaJatim : $this->desaLain)->all());
    }

    /**
     * @param  array<string, int>  $bobot
     */
    private function acak(array $bobot): string
    {
        $angka = $this->faker->numberBetween(1, array_sum($bobot));

        foreach ($bobot as $nilai => $berat) {
            if (($angka -= $berat) <= 0) {
                return (string) $nilai;
            }
        }

        return (string) array_key_last($bobot);
    }

    private function berkas(string $nama): UploadedFile
    {
        return new UploadedFile(database_path("data/demo/{$nama}"), $nama, null, null, true);
    }

    private function sudahLewat(Carbon $waktu): bool
    {
        return $waktu->lte($this->sekarang);
    }

    /**
     * Menjalankan aksi seolah terjadi pada waktu tertentu (tidak melewati saat ini) oleh akun
     * tertentu, atau oleh pengunjung form publik bila `$oleh` kosong.
     *
     * @template T
     *
     * @param  callable(): T  $aksi
     * @return T
     */
    private function pada(Carbon $waktu, ?User $oleh, callable $aksi): mixed
    {
        Carbon::setTestNow($waktu->min($this->sekarang));
        $oleh ? Auth::setUser($oleh) : Auth::forgetUser();

        return $aksi();
    }
}
