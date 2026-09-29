<?php

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\TipeLokasi;
use App\Models\AsramaPeserta;
use App\Models\JudulPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Services\PelatihanService;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->service = app(PelatihanService::class);
    $this->judul = JudulPelatihan::factory()->create(['judul' => 'Pelatihan Keuangan Desa']);
    $this->isian = [
        'judul_pelatihan_id' => $this->judul->id,
        'batch_ke' => 1,
        'tanggal_mulai' => '2026-10-05',
        'tanggal_selesai' => '2026-10-08',
        'tipe_lokasi' => 'bbpd',
        'keterangan_lokasi' => 'diabaikan untuk BBPD',
    ];
});

function galatValidasi(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

describe('simpan', function () {
    it('membuat pelatihan draft dengan tahun anggaran dari tanggal mulai', function () {
        $pelatihan = $this->service->simpan([...$this->isian, 'tanggal_mulai' => '2027-01-04', 'tanggal_selesai' => '2027-01-06']);

        expect($pelatihan->exists)->toBeTrue()
            ->and($pelatihan->tahun_anggaran)->toBe(2027)
            ->and($pelatihan->status)->toBe(StatusPelatihan::Draft)
            ->and($pelatihan->keterangan_lokasi)->toBeNull()
            ->and($pelatihan->nama_tampilan)->toBe('Pelatihan Keuangan Desa 2027 Batch 1');
    });

    it('mewajibkan keterangan lokasi untuk pelatihan di luar BBPD', function () {
        expect(galatValidasi(fn () => $this->service->simpan([...$this->isian, 'tipe_lokasi' => TipeLokasi::Luar, 'keterangan_lokasi' => '  '])))
            ->toHaveKey('keterangan_lokasi');

        $pelatihan = $this->service->simpan([...$this->isian, 'tipe_lokasi' => TipeLokasi::Luar, 'keterangan_lokasi' => ' Hotel Tugu, Malang ']);

        expect($pelatihan->keterangan_lokasi)->toBe('Hotel Tugu, Malang');
    });

    it('menolak tanggal selesai sebelum tanggal mulai', function () {
        expect(galatValidasi(fn () => $this->service->simpan([...$this->isian, 'tanggal_selesai' => '2026-10-01'])))
            ->toHaveKey('tanggal_selesai');
    });

    it('menolak judul, tahun, dan batch yang sama dengan pesan yang jelas', function () {
        $pelatihan = $this->service->simpan($this->isian);

        expect(galatValidasi(fn () => $this->service->simpan($this->isian))['batch_ke'][0])
            ->toBe('Pelatihan Keuangan Desa 2026 Batch 1 sudah ada. Gunakan nomor batch lain.');

        $this->service->simpan([...$this->isian, 'tanggal_selesai' => '2026-10-09'], $pelatihan);

        expect($pelatihan->fresh()->jumlah_hari)->toBe(5)
            ->and($this->service->simpan([...$this->isian, 'batch_ke' => 2])->batch_ke)->toBe(2);
    });

    it('mengunci tipe lokasi jika sudah ada peserta di kamar', function () {
        $pelatihan = $this->service->simpan($this->isian);
        $penggunaan = PenggunaanKamar::factory()->for($pelatihan)->create();

        $this->service->simpan([...$this->isian, 'tanggal_selesai' => '2026-10-07'], $pelatihan);

        AsramaPeserta::factory()->for($penggunaan)->create();

        expect(galatValidasi(fn () => $this->service->simpan([...$this->isian, 'tipe_lokasi' => 'luar', 'keterangan_lokasi' => 'Batu'], $pelatihan)))
            ->toHaveKey('tipe_lokasi')
            ->and($pelatihan->fresh()->tipe_lokasi)->toBe(TipeLokasi::Bbpd);
    });
});

describe('ubahStatus', function () {
    it('berpindah berurutan dari draft sampai selesai', function () {
        $pelatihan = Pelatihan::factory()->create();

        foreach ([StatusPelatihan::Dibuka, StatusPelatihan::Berjalan, StatusPelatihan::Selesai] as $tujuan) {
            $this->service->ubahStatus($pelatihan, $tujuan);

            expect($pelatihan->status)->toBe($tujuan);
        }
    });

    it('menolak melompati atau mundur status', function (string $state, StatusPelatihan $tujuan) {
        $pelatihan = ($state === 'make' ? Pelatihan::factory() : Pelatihan::factory()->{$state}())->create();
        $awal = $pelatihan->status;

        expect(galatValidasi(fn () => $this->service->ubahStatus($pelatihan, $tujuan)))->toHaveKey('status')
            ->and($pelatihan->fresh()->status)->toBe($awal);
    })->with([
        'draft ke berjalan' => ['make', StatusPelatihan::Berjalan],
        'dibuka ke draft' => ['dibuka', StatusPelatihan::Draft],
        'selesai ke berjalan' => ['selesai', StatusPelatihan::Berjalan],
    ]);

    it('tidak dapat diselesaikan selama ada peserta menunggu verifikasi', function () {
        $pelatihan = Pelatihan::factory()->berjalan()->create();
        PelatihanPeserta::factory()->count(2)->for($pelatihan)->create();
        $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($pelatihan)->create();

        expect(galatValidasi(fn () => $this->service->ubahStatus($pelatihan, StatusPelatihan::Selesai))['status'][0])
            ->toBe('Masih ada 2 peserta yang menunggu verifikasi. Verifikasi atau batalkan terlebih dahulu.')
            ->and($pelatihan->fresh()->status)->toBe(StatusPelatihan::Berjalan)
            ->and($terverifikasi->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);
    });

    it('menyelesaikan semua peserta terverifikasi dan mencatatnya di audit log', function () {
        $pelatihan = Pelatihan::factory()->berjalan()->create();
        $terverifikasi = PelatihanPeserta::factory()->count(2)->terverifikasi()->for($pelatihan)->create();
        $batal = PelatihanPeserta::factory()->batal()->for($pelatihan)->create();
        $lain = PelatihanPeserta::factory()->terverifikasi()->create();

        $this->service->ubahStatus($pelatihan, StatusPelatihan::Selesai);

        expect($terverifikasi->map(fn ($p) => $p->fresh()->status)->unique()->all())->toBe([StatusPendaftaran::Selesai])
            ->and($batal->fresh()->status)->toBe(StatusPendaftaran::Batal)
            ->and($lain->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi)
            ->and(Activity::query()->where('subject_type', (new PelatihanPeserta)->getMorphClass())
                ->whereIn('subject_id', $terverifikasi->modelKeys())
                ->where('event', 'updated')
                ->count())->toBe(2);
    });
});
