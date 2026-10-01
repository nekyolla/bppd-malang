<?php

use App\Enums\StatusPendaftaran;
use App\Models\KelasPelatihan;
use App\Models\LembarPresensi;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Services\KelasService;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->service = app(KelasService::class);
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
});

function galatKelas(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

/**
 * Jumlah peserta terverifikasi per nama kelas, mis. ['A' => 4, 'B' => 3].
 *
 * @return array<string, int>
 */
function isiKelas(Pelatihan $pelatihan): array
{
    return $pelatihan->kelas()->orderBy('nama_kelas')
        ->withCount(['pendaftaran' => fn ($q) => $q->where('status', StatusPendaftaran::Terverifikasi)])
        ->pluck('pendaftaran_count', 'nama_kelas')
        ->all();
}

describe('kelas', function () {
    it('membuat kelas dalam pelatihan dengan jumlah bebas', function () {
        foreach (['A', ' B ', 'C'] as $nama) {
            $this->service->simpan($this->pelatihan, $nama);
        }

        expect($this->pelatihan->kelas()->orderBy('nama_kelas')->pluck('nama_kelas')->all())->toBe(['A', 'B', 'C']);
    });

    it('menolak nama kelas kosong, terlalu panjang, atau sudah dipakai di pelatihan yang sama', function () {
        $this->service->simpan($this->pelatihan, 'A');

        expect(galatKelas(fn () => $this->service->simpan($this->pelatihan, '  ')))->toHaveKey('nama_kelas')
            ->and(galatKelas(fn () => $this->service->simpan($this->pelatihan, 'Kelas Pagi 1')))->toHaveKey('nama_kelas')
            ->and(galatKelas(fn () => $this->service->simpan($this->pelatihan, 'A'))['nama_kelas'][0])
            ->toBe('Kelas A sudah ada di pelatihan ini. Gunakan nama lain.')
            ->and($this->pelatihan->kelas()->count())->toBe(1);
    });

    it('membolehkan nama kelas yang sama di pelatihan lain', function () {
        $this->service->simpan($this->pelatihan, 'A');
        $this->service->simpan(Pelatihan::factory()->dibuka()->create(), 'A');

        expect(KelasPelatihan::where('nama_kelas', 'A')->count())->toBe(2);
    });

    it('mengganti nama kelas', function () {
        $kelas = $this->service->simpan($this->pelatihan, 'A');
        $this->service->simpan($this->pelatihan, 'B');

        $this->service->simpan($this->pelatihan, 'A', $kelas);
        $this->service->simpan($this->pelatihan, 'Pagi', $kelas);

        expect($kelas->fresh()->nama_kelas)->toBe('Pagi')
            ->and(galatKelas(fn () => $this->service->simpan($this->pelatihan, 'B', $kelas)))->toHaveKey('nama_kelas');
    });

    it('mengusulkan huruf berikutnya yang belum dipakai', function () {
        expect($this->service->namaBerikutnya($this->pelatihan))->toBe('A');

        $this->service->simpan($this->pelatihan, 'a');
        $this->service->simpan($this->pelatihan, 'C');

        expect($this->service->namaBerikutnya($this->pelatihan))->toBe('B');
    });

    it('menghapus kelas kosong dan melepas peserta batal yang masih tercatat di dalamnya', function () {
        $kelas = $this->service->simpan($this->pelatihan, 'A');
        $batal = PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $kelas->id]);

        $this->service->hapus($kelas);

        expect(KelasPelatihan::find($kelas->id))->toBeNull()
            ->and($batal->fresh()->kelas_pelatihan_id)->toBeNull()
            ->and($batal->fresh()->status)->toBe(StatusPendaftaran::Batal);
    });

    it('menolak menghapus kelas yang masih berisi peserta', function () {
        $kelas = $this->service->simpan($this->pelatihan, 'A');
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(2)->create(['kelas_pelatihan_id' => $kelas->id]);

        expect(galatKelas(fn () => $this->service->hapus($kelas))['kelas'][0])
            ->toBe('Kelas A masih berisi 2 peserta. Pindahkan pesertanya ke kelas lain terlebih dahulu.')
            ->and($kelas->fresh())->not->toBeNull();
    });

    it('menolak menghapus kelas yang sudah memiliki presensi', function () {
        $kelas = $this->service->simpan($this->pelatihan, 'A');
        LembarPresensi::factory()->for($kelas, 'kelasPelatihan')->create();

        expect(galatKelas(fn () => $this->service->hapus($kelas)))->toHaveKey('kelas')
            ->and($kelas->fresh())->not->toBeNull();
    });

    it('menolak mengubah kelas setelah pelatihan selesai', function () {
        $selesai = Pelatihan::factory()->selesai()->create();
        $kelas = KelasPelatihan::factory()->for($selesai)->create(['nama_kelas' => 'A']);
        $pesan = 'Kelas tidak dapat diubah karena pelatihan sudah selesai.';

        expect(galatKelas(fn () => $this->service->simpan($selesai, 'B'))['kelas'][0])->toBe($pesan)
            ->and(galatKelas(fn () => $this->service->simpan($selesai, 'Z', $kelas))['kelas'][0])->toBe($pesan)
            ->and(galatKelas(fn () => $this->service->hapus($kelas))['kelas'][0])->toBe($pesan)
            ->and(galatKelas(fn () => $this->service->bagiAcak($selesai))['kelas'][0])->toBe($pesan)
            ->and($kelas->fresh()->nama_kelas)->toBe('A');
    });
});

describe('penetapan kelas', function () {
    beforeEach(function () {
        $this->kelas = $this->service->simpan($this->pelatihan, 'A');
    });

    it('menetapkan, memindahkan, dan melepas kelas peserta terverifikasi', function () {
        $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();
        $kelasB = $this->service->simpan($this->pelatihan, 'B');

        $this->service->tetapkan($pendaftaran, $this->kelas);
        expect($pendaftaran->kelas_pelatihan_id)->toBe($this->kelas->id);

        $this->service->tetapkan($pendaftaran, $kelasB);
        expect($pendaftaran->kelas_pelatihan_id)->toBe($kelasB->id);

        $this->service->tetapkan($pendaftaran, null);
        expect($pendaftaran->kelas_pelatihan_id)->toBeNull();
    });

    it('mencatat perubahan kelas di audit log', function () {
        $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        $this->service->tetapkan($pendaftaran, $this->kelas);

        $log = Activity::query()->where('subject_type', $pendaftaran->getMorphClass())->where('subject_id', $pendaftaran->id)->latest('id')->first();

        expect($log->attribute_changes['attributes']['kelas_pelatihan_id'])->toBe($this->kelas->id)
            ->and($log->attribute_changes['old']['kelas_pelatihan_id'])->toBeNull();
    });

    it('hanya menerima peserta terverifikasi', function (string $state) {
        $pendaftaran = ($state === 'terdaftar' ? PelatihanPeserta::factory() : PelatihanPeserta::factory()->{$state}())
            ->for($this->pelatihan)->create();

        expect(galatKelas(fn () => $this->service->tetapkan($pendaftaran, $this->kelas))['status'][0])
            ->toBe('Hanya peserta terverifikasi yang dapat ditetapkan kelasnya.')
            ->and($pendaftaran->fresh()->kelas_pelatihan_id)->toBeNull();
    })->with(['terdaftar', 'selesai', 'batal']);

    it('menolak kelas dari pelatihan lain', function () {
        $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();
        $kelasLain = KelasPelatihan::factory()->create();

        expect(galatKelas(fn () => $this->service->tetapkan($pendaftaran, $kelasLain))['kelas_pelatihan_id'][0])
            ->toBe('Kelas harus milik pelatihan yang sama dengan pendaftaran peserta.')
            ->and($pendaftaran->fresh()->kelas_pelatihan_id)->toBeNull();
    });

    it('menetapkan kelas beberapa peserta sekaligus dan melewati yang tidak memenuhi syarat', function () {
        $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(3)->create();
        $terdaftar = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $pelatihanLain = PelatihanPeserta::factory()->terverifikasi()->create();

        $hasil = $this->service->tetapkanMassal([...$terverifikasi, $terdaftar, $pelatihanLain], $this->kelas);

        expect($hasil)->toBe(['ditetapkan' => 3, 'dilewati' => 2])
            ->and($this->kelas->pendaftaran()->count())->toBe(3)
            ->and($pelatihanLain->fresh()->kelas_pelatihan_id)->toBeNull();
    });
});

describe('bagi acak', function () {
    it('membagi rata peserta terverifikasi ke semua kelas', function () {
        foreach (['A', 'B', 'C'] as $nama) {
            $this->service->simpan($this->pelatihan, $nama);
        }
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(10)->create();

        expect($this->service->bagiAcak($this->pelatihan))->toBe(10)
            ->and(isiKelas($this->pelatihan))->toBe(['A' => 4, 'B' => 3, 'C' => 3]);
    });

    it('tidak membagi peserta yang belum terverifikasi, batal, atau dari pelatihan lain', function () {
        $this->service->simpan($this->pelatihan, 'A');
        $terdaftar = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $batal = PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create();
        $pelatihanLain = PelatihanPeserta::factory()->terverifikasi()->create();
        $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        expect($this->service->bagiAcak($this->pelatihan))->toBe(1)
            ->and($terverifikasi->fresh()->kelas_pelatihan_id)->not->toBeNull()
            ->and($terdaftar->fresh()->kelas_pelatihan_id)->toBeNull()
            ->and($batal->fresh()->kelas_pelatihan_id)->toBeNull()
            ->and($pelatihanLain->fresh()->kelas_pelatihan_id)->toBeNull();
    });

    it('bawaan hanya membagi yang belum punya kelas dan menyeimbangkan isi kelas', function () {
        $a = $this->service->simpan($this->pelatihan, 'A');
        $this->service->simpan($this->pelatihan, 'B');
        $lama = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(4)->create(['kelas_pelatihan_id' => $a->id]);
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(4)->create();

        expect($this->service->bagiAcak($this->pelatihan))->toBe(4)
            ->and(isiKelas($this->pelatihan))->toBe(['A' => 4, 'B' => 4])
            ->and($lama->every(fn (PelatihanPeserta $p) => $p->fresh()->kelas_pelatihan_id === $a->id))->toBeTrue();
    });

    it('membagi ulang semua peserta bila diminta', function () {
        $a = $this->service->simpan($this->pelatihan, 'A');
        $this->service->simpan($this->pelatihan, 'B');
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(6)->create(['kelas_pelatihan_id' => $a->id]);

        expect($this->service->bagiAcak($this->pelatihan))->toBe(0)
            ->and(isiKelas($this->pelatihan))->toBe(['A' => 6, 'B' => 0])
            ->and($this->service->bagiAcak($this->pelatihan, ulangSemua: true))->toBe(6)
            ->and(isiKelas($this->pelatihan))->toBe(['A' => 3, 'B' => 3]);
    });

    it('menolak membagi sebelum ada kelas', function () {
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        expect(galatKelas(fn () => $this->service->bagiAcak($this->pelatihan))['kelas'][0])
            ->toBe('Buat kelas terlebih dahulu sebelum membagi peserta.');
    });
});
