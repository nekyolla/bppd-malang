<?php

use App\Enums\JenisKelamin;
use App\Enums\TipeKamar;
use App\Enums\TipeLokasi;
use App\Models\Asrama;
use App\Models\AsramaPeserta;
use App\Models\KamarAsrama;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\User;
use App\Services\AsramaService;
use App\Services\PelatihanService;
use App\Support\FormatTanggal;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 09:00');
    $this->service = app(AsramaService::class);
    $this->admin = User::factory()->create();
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
    $this->asrama = Asrama::factory()->create(['nama_asrama' => 'Anggrek']);
    $this->kamar = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '01', 'kapasitas' => 4]);
});

function galatAsrama(Closure $callback): string
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return collect($exception->errors())->flatten()->first();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

/**
 * Pendaftaran terverifikasi dengan jenis kelamin tertentu.
 */
function pesertaAsrama(Pelatihan $pelatihan, JenisKelamin $jenisKelamin = JenisKelamin::LakiLaki, string $state = 'terverifikasi'): PelatihanPeserta
{
    $factory = PelatihanPeserta::factory()->for($pelatihan)->for(Peserta::factory()->state(['jenis_kelamin' => $jenisKelamin]));

    return ($state === 'terdaftar' ? $factory : $factory->{$state}())->create();
}

describe('penempatan', function () {
    it('menempatkan peserta di kamar kosong dan mengunci gender kamar dari penghuni pertama', function () {
        $penempatan = $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $this->kamar, $this->admin);

        $penggunaan = $penempatan->penggunaanKamar;

        expect($penggunaan->tipe)->toBe(TipeKamar::Perempuan)
            ->and($penggunaan->pelatihan_id)->toBe($this->pelatihan->id)
            ->and($penggunaan->disetujui_oleh)->toBeNull()
            ->and($penempatan->ditempatkanOleh->is($this->admin))->toBeTrue();
    });

    it('menolak peserta dengan gender berbeda dari penghuni kamar', function () {
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);
        $perempuan = pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan);

        expect(galatAsrama(fn () => $this->service->tempatkan($perempuan, $this->kamar, $this->admin)))
            ->toBe('Kamar Anggrek-01 sudah ditempati peserta laki-laki. Pilih kamar lain.')
            ->and($perempuan->penempatanKamar)->toBeNull();
    });

    it('menolak penempatan melebihi kapasitas kamar', function () {
        $this->kamar->update(['kapasitas' => 2]);
        $this->service->tempatkanBanyak([pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)], $this->kamar, $this->admin);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin)))
            ->toBe('Kamar Anggrek-01 sudah penuh (2/2).');
    });

    it('menolak pelatihan di luar BBPD', function () {
        $luar = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create(['tipe_lokasi' => TipeLokasi::Luar, 'keterangan_lokasi' => 'Surabaya']);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($luar), $this->kamar, $this->admin)))
            ->toBe('Asrama hanya untuk pelatihan di Balai Besar.');
    });

    it('hanya menempatkan peserta terverifikasi', function (string $state) {
        $pendaftaran = pesertaAsrama($this->pelatihan, state: $state);

        expect(galatAsrama(fn () => $this->service->tempatkan($pendaftaran, $this->kamar, $this->admin)))
            ->toBe('Hanya peserta terverifikasi yang dapat ditempatkan di kamar.');
    })->with(['terdaftar', 'selesai', 'batal']);

    it('tidak memindahkan peserta yang sudah punya kamar', function () {
        $pendaftaran = pesertaAsrama($this->pelatihan);
        $this->service->tempatkan($pendaftaran, $this->kamar, $this->admin);
        $kamarLain = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02']);

        expect(galatAsrama(fn () => $this->service->tempatkan($pendaftaran, $kamarLain, $this->admin)))
            ->toBe("{$pendaftaran->peserta->nama_lengkap} sudah ditempatkan di kamar Anggrek-01. Keluarkan dulu sebelum memindahkan.");
    });

    it('menolak kamar atau asrama yang dinonaktifkan', function () {
        $this->kamar->update(['is_aktif' => false]);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin)))
            ->toBe('Kamar Anggrek-01 sudah dinonaktifkan.');

        $this->kamar->update(['is_aktif' => true]);
        $this->asrama->update(['is_aktif' => false]);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin)))
            ->toBe('Kamar Anggrek-01 sudah dinonaktifkan.');
    });

    it('mencatat penempatan di audit log', function () {
        $penempatan = $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);

        expect(Activity::query()->where('subject_type', $penempatan->getMorphClass())->where('subject_id', $penempatan->id)->where('event', 'created')->exists())->toBeTrue();
    });

    it('membatalkan semua penempatan jika salah satu ditolak', function () {
        $laki = pesertaAsrama($this->pelatihan);
        $perempuan = pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan);

        expect(galatAsrama(fn () => $this->service->tempatkanBanyak([$laki, $perempuan], $this->kamar, $this->admin)))
            ->toContain('sudah ditempati peserta laki-laki')
            ->and(AsramaPeserta::count())->toBe(0)
            ->and(PenggunaanKamar::count())->toBe(0);
    });
});

describe('kamar pasutri', function () {
    it('menerima 1 laki-laki dan 1 perempuan, maksimal 2 orang walau kapasitas lebih besar', function () {
        $suami = pesertaAsrama($this->pelatihan);
        $istri = pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan);

        $this->service->tempatkan($suami, $this->kamar, $this->admin, pasutri: true);
        $this->service->tempatkan($istri, $this->kamar, $this->admin);

        $penggunaan = PenggunaanKamar::sole();

        expect($penggunaan->tipe)->toBe(TipeKamar::Pasutri)
            ->and($penggunaan->disetujuiOleh->is($this->admin))->toBeTrue()
            ->and($penggunaan->jumlah_penghuni)->toBe(2)
            ->and(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $this->kamar, $this->admin)))
            ->toBe('Kamar pasutri Anggrek-01 sudah berisi peserta perempuan. Penghuni kedua harus laki-laki.');
    });

    it('menolak dua penghuni bergender sama di kamar pasutri', function () {
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin, pasutri: true);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin)))
            ->toBe('Kamar pasutri Anggrek-01 sudah berisi peserta laki-laki. Penghuni kedua harus perempuan.');
    });

    it('hanya menjadikan kamar kosong berkapasitas minimal 2 sebagai kamar pasutri', function () {
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);
        $tunggal = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 1]);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $this->kamar, $this->admin, pasutri: true)))
            ->toBe('Kamar Anggrek-01 sudah ditempati peserta laki-laki, sehingga tidak dapat dijadikan kamar pasutri.')
            ->and(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan), $tunggal, $this->admin, pasutri: true)))
            ->toBe('Kamar Anggrek-02 hanya untuk 1 orang, tidak bisa jadi kamar pasutri.');
    });
});

describe('bentrok tanggal', function () {
    it('menolak kamar yang dipakai pelatihan lain pada tanggal yang tumpang tindih', function (string $mulai, string $selesai) {
        $lain = Pelatihan::factory()->dibuka()->tanggal($mulai, $selesai)->create();
        $this->service->tempatkan(pesertaAsrama($lain), $this->kamar, $this->admin);

        expect(galatAsrama(fn () => $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin)))
            ->toBe("Kamar Anggrek-01 sedang dipakai {$lain->nama_tampilan} (".FormatTanggal::rentang($lain->tanggal_mulai, $lain->tanggal_selesai).').');
    })->with([
        'sama persis' => ['2026-10-05', '2026-10-08'],
        'beririsan di awal' => ['2026-10-02', '2026-10-05'],
        'beririsan di akhir' => ['2026-10-08', '2026-10-10'],
        'di dalam' => ['2026-10-06', '2026-10-06'],
    ]);

    it('membolehkan kamar yang sama untuk pelatihan pada tanggal berbeda', function () {
        $lain = Pelatihan::factory()->dibuka()->tanggal('2026-10-09', '2026-10-12')->create();
        $this->service->tempatkan(pesertaAsrama($lain), $this->kamar, $this->admin);

        $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $this->kamar, $this->admin);

        expect(PenggunaanKamar::count())->toBe(2);
    });

    it('menolak perubahan tanggal pelatihan yang membuat kamarnya bentrok', function () {
        $lain = Pelatihan::factory()->dibuka()->tanggal('2026-10-12', '2026-10-15')->create();
        $this->service->tempatkan(pesertaAsrama($lain), $this->kamar, $this->admin);
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);
        $data = fn (string $mulai, string $selesai): array => [
            'judul_pelatihan_id' => $this->pelatihan->judul_pelatihan_id,
            'batch_ke' => $this->pelatihan->batch_ke,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'tipe_lokasi' => 'bbpd',
        ];

        try {
            app(PelatihanService::class)->simpan($data('2026-10-11', '2026-10-14'), $this->pelatihan);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('tanggal_mulai')
                ->and($exception->errors()['tanggal_mulai'][0])->toContain('Kamar Anggrek-01 sedang dipakai');
        }

        app(PelatihanService::class)->simpan($data('2026-10-04', '2026-10-09'), $this->pelatihan);

        expect($this->pelatihan->fresh()->tanggal_selesai->toDateString())->toBe('2026-10-09');
    });
});

describe('mengeluarkan', function () {
    it('mengembalikan kamar menjadi netral setelah penghuni terakhir keluar', function () {
        $penempatan = $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);

        $this->service->keluarkan($penempatan);

        expect(PenggunaanKamar::count())->toBe(0);

        $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $this->kamar, $this->admin);

        expect(PenggunaanKamar::sole()->tipe)->toBe(TipeKamar::Perempuan);
    });

    it('mempertahankan tipe kamar selama masih ada penghuni', function () {
        [$a, $b] = [pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)];
        $this->service->tempatkanBanyak([$a, $b], $this->kamar, $this->admin);

        $this->service->keluarkan($a->fresh()->penempatanKamar);

        expect(PenggunaanKamar::sole()->jumlah_penghuni)->toBe(1);
    });

    it('menolak mengubah penempatan peserta yang sudah selesai', function () {
        $penempatan = AsramaPeserta::factory()->for(PenggunaanKamar::factory()->for($this->pelatihan)->for($this->kamar))->create();
        $penempatan->pelatihanPeserta->forceFill(['status' => 'selesai'])->save();

        expect(galatAsrama(fn () => $this->service->keluarkan($penempatan)))
            ->toBe('Pelatihan sudah selesai. Penempatan tidak dapat diubah.')
            ->and($penempatan->fresh())->not->toBeNull();
    });
});

describe('denah dan ringkasan', function () {
    it('menampilkan keadaan setiap kamar per asrama', function () {
        $kosong = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 2]);
        $dipakaiLain = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '03']);
        KamarAsrama::factory()->for($this->asrama)->nonaktif()->create(['no_kamar' => '04']);
        KamarAsrama::factory()->for(Asrama::factory()->nonaktif())->create();
        $lain = Pelatihan::factory()->dibuka()->tanggal('2026-10-07', '2026-10-09')->create();
        $this->service->tempatkan(pesertaAsrama($lain), $dipakaiLain, $this->admin);
        $penghuni = pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan);
        $this->service->tempatkan($penghuni, $this->kamar, $this->admin);

        $denah = $this->service->denah($this->pelatihan);
        $kamar = collect($denah[0]['kamar'])->keyBy('no_kamar');

        expect($denah)->toHaveCount(1)
            ->and($denah[0]['nama'])->toBe('Anggrek')
            ->and($kamar->keys()->all())->toBe(['01', '02', '03'])
            ->and($kamar['01'])->toMatchArray(['tipe' => TipeKamar::Perempuan, 'jumlah' => 1, 'kapasitas' => 4, 'penuh' => false, 'tersedia' => true])
            ->and($kamar['01']['penghuni'][0]['nama'])->toBe($penghuni->peserta->nama_lengkap)
            ->and($kamar['02'])->toMatchArray(['tipe' => null, 'jumlah' => 0, 'tersedia' => true, 'keterangan' => null])
            ->and($kamar['03']['tersedia'])->toBeFalse()
            ->and($kamar['03']['keterangan'])->toBe("Dipakai {$lain->nama_tampilan} (7–9 Oktober 2026)");
    });

    it('tetap menampilkan kamar nonaktif yang sudah berpenghuni di pelatihan ini', function () {
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);
        $this->kamar->update(['is_aktif' => false]);

        $kamar = $this->service->denah($this->pelatihan)[0]['kamar'][0];

        expect($kamar)->toMatchArray(['jumlah' => 1, 'tersedia' => false, 'keterangan' => 'Kamar dinonaktifkan']);
    });

    it('menghitung kecukupan tempat tidur laki-laki dan perempuan', function () {
        // Anggrek-01 (4) berisi 1 L; Anggrek-02 (2) kosong; Anggrek-03 (4) pasutri berisi 1 P.
        KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 2]);
        $pasutri = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '03', 'kapasitas' => 4]);
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $this->kamar, $this->admin);
        $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $pasutri, $this->admin, pasutri: true);
        foreach (range(1, 4) as $i) {
            pesertaAsrama($this->pelatihan);
        }
        foreach (range(1, 3) as $i) {
            pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan);
        }
        pesertaAsrama($this->pelatihan, state: 'batal');

        expect($this->service->ringkasan($this->pelatihan))->toBe([
            'peserta' => ['L' => 5, 'P' => 4],
            'belum' => ['L' => 4, 'P' => 3],
            'sisa' => ['L' => 4, 'P' => 0],
            'kosong' => 2,
            'kurang' => 1,
        ]);
    });

    it('menyaring calon penghuni sesuai gender kamar dan yang belum punya kamar', function () {
        $sudah = pesertaAsrama($this->pelatihan);
        $this->service->tempatkan($sudah, $this->kamar, $this->admin);
        $laki = pesertaAsrama($this->pelatihan);
        $perempuan = pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan);
        pesertaAsrama($this->pelatihan, state: 'terdaftar');
        $kosong = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02']);

        expect($this->service->calonPenghuni($this->pelatihan, $this->kamar)->modelKeys())->toBe([$laki->id])
            ->and($this->service->calonPenghuni($this->pelatihan, $kosong)->modelKeys())->toEqualCanonicalizing([$laki->id, $perempuan->id]);
    });

    it('menghitung penghuni terbanyak dari pelatihan yang belum selesai', function () {
        $this->service->tempatkanBanyak([pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)], $this->kamar, $this->admin);
        $selesai = Pelatihan::factory()->selesai()->tanggal('2026-06-01', '2026-06-03')->create();
        $penggunaanLama = PenggunaanKamar::factory()->for($selesai)->for($this->kamar)->create();
        AsramaPeserta::factory()->for($penggunaanLama)->count(3)->create();

        expect($this->service->penghuniTerbanyak($this->kamar))->toBe(2);
    });
});

describe('keluarkan sekaligus', function () {
    it('mengosongkan kamar dan mengembalikannya netral', function () {
        $daftar = [pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)];
        $this->service->tempatkanBanyak($daftar, $this->kamar, $this->admin);

        $jumlah = $this->service->keluarkanBanyak(AsramaPeserta::all());

        expect($jumlah)->toBe(3)
            ->and(AsramaPeserta::count())->toBe(0)
            ->and(PenggunaanKamar::count())->toBe(0);
    });

    it('tidak mengeluarkan siapa pun bila satu penempatan ditolak', function () {
        [$a, $b] = [pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)];
        $this->service->tempatkanBanyak([$a, $b], $this->kamar, $this->admin);
        $b->forceFill(['status' => 'selesai'])->save();

        galatAsrama(fn () => $this->service->keluarkanBanyak(AsramaPeserta::query()->orderBy('id')->get()));

        expect(AsramaPeserta::count())->toBe(2);
    });
});

describe('isi otomatis', function () {
    it('mengisi kamar segender yang bersisa dulu, lalu kamar kosong, dan memisahkan gender', function () {
        $kamar2 = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 2]);
        $kamar3 = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '03', 'kapasitas' => 2]);
        $this->service->tempatkan(pesertaAsrama($this->pelatihan), $kamar2, $this->admin);

        $laki = [pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)];
        $perempuan = [pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan)];

        $hasil = $this->service->isiOtomatis($this->pelatihan, $this->admin);

        $kamarDari = fn (PelatihanPeserta $p): int => $p->fresh()->penempatanKamar->penggunaanKamar->kamar_asrama_id;

        $kamarLaki = collect($laki)->map($kamarDari);
        $kamarPerempuan = collect($perempuan)->map($kamarDari);

        expect($hasil)->toBe(['ditempatkan' => 4, 'sisa' => 0])
            // Sisa satu tempat di kamar 02 (laki-laki) terpakai sebelum membuka kamar kosong.
            ->and($kamarLaki)->toContain($kamar2->id)
            ->and($kamarLaki->intersect($kamarPerempuan))->toBeEmpty()
            ->and($kamarPerempuan->unique())->toHaveCount(1)
            ->and(PenggunaanKamar::query()->where('kamar_asrama_id', $kamarPerempuan->first())->sole()->tipe)->toBe(TipeKamar::Perempuan);
    });

    it('melewati kamar pasutri, kamar bentrok, dan menyisakan peserta yang tidak kebagian', function () {
        $this->kamar->update(['kapasitas' => 2]);
        $pasutri = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 2]);
        $this->service->tempatkan(pesertaAsrama($this->pelatihan, JenisKelamin::Perempuan), $pasutri, $this->admin, pasutri: true);

        $calon = [pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan), pesertaAsrama($this->pelatihan)];

        $hasil = $this->service->isiOtomatis($this->pelatihan, $this->admin);

        expect($hasil)->toBe(['ditempatkan' => 2, 'sisa' => 1])
            ->and(PenggunaanKamar::query()->where('kamar_asrama_id', $pasutri->id)->sole()->jumlah_penghuni)->toBe(1)
            ->and(collect($calon)->filter(fn (PelatihanPeserta $p): bool => $p->fresh()->penempatanKamar === null))->toHaveCount(1);
    });

    it('mendekatkan teman sekelas dalam satu kamar', function () {
        $this->kamar->update(['kapasitas' => 2]);
        KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 2]);
        $kelasA = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
        $kelasB = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'B']);
        $daftar = collect([$kelasB, $kelasA, $kelasB, $kelasA])->map(function ($kelas) {
            $p = pesertaAsrama($this->pelatihan);
            $p->forceFill(['kelas_pelatihan_id' => $kelas->id])->save();

            return $p;
        });

        $this->service->isiOtomatis($this->pelatihan, $this->admin);

        $kamarPerKelas = $daftar->groupBy('kelas_pelatihan_id')
            ->map(fn ($grup) => $grup->map(fn (PelatihanPeserta $p): int => $p->fresh()->penempatanKamar->penggunaanKamar->kamar_asrama_id)->unique()->count());

        expect($kamarPerKelas->all())->each->toBe(1);
    });
});
