<?php

use App\Enums\StatusPendaftaran;
use App\Models\AsramaPeserta;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\SertifikatPeserta;
use App\Models\User;
use App\Services\PendaftaranService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 09:00');
    $this->service = app(PendaftaranService::class);
    $this->admin = User::factory()->create();
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
});

function galatBatal(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

describe('pembatalan', function () {
    it('membatalkan peserta dengan alasan dan mencatat siapa yang membatalkan', function (string $state) {
        $pendaftaran = ($state === 'terdaftar' ? PelatihanPeserta::factory() : PelatihanPeserta::factory()->{$state}())
            ->for($this->pelatihan)->create();

        $this->service->batalkan($pendaftaran, $this->admin, '  Sakit  ');

        expect($pendaftaran->status)->toBe(StatusPendaftaran::Batal)
            ->and($pendaftaran->alasan_batal)->toBe('Sakit')
            ->and($pendaftaran->dibatalkanOleh->is($this->admin))->toBeTrue()
            ->and($pendaftaran->dibatalkan_pada)->not->toBeNull();
    })->with(['terdaftar', 'terverifikasi']);

    it('mewajibkan alasan pembatalan', function () {
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();

        expect(galatBatal(fn () => $this->service->batalkan($pendaftaran, $this->admin, '   ')))->toHaveKey('alasan_batal')
            ->and($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terdaftar);
    });

    it('menolak membatalkan peserta yang sudah selesai atau batal', function (string $state) {
        $pendaftaran = PelatihanPeserta::factory()->{$state}()->for($this->pelatihan)->create();

        expect(galatBatal(fn () => $this->service->batalkan($pendaftaran, $this->admin, 'Sakit')))->toHaveKey('status');
    })->with(['selesai', 'batal']);

    it('menolak membatalkan peserta yang sertifikatnya sudah diunggah', function () {
        $sertifikat = SertifikatPeserta::factory()->create([
            'pelatihan_peserta_id' => PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan),
        ]);
        $pendaftaran = $sertifikat->pelatihanPeserta;

        expect(galatBatal(fn () => $this->service->batalkan($pendaftaran, $this->admin, 'Sakit'))['status'][0])
            ->toBe('Hapus sertifikat peserta ini terlebih dahulu.')
            ->and($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);
    });

    it('melepas jatah kamar dan mengembalikan kamar ke netral jika penghuni terakhir', function () {
        $penggunaan = PenggunaanKamar::factory()->for($this->pelatihan)->create();
        $pertama = AsramaPeserta::factory()->for($penggunaan)->create();
        $kedua = AsramaPeserta::factory()->for($penggunaan)->create();

        $this->service->batalkan($pertama->pelatihanPeserta, $this->admin, 'Sakit');

        expect(AsramaPeserta::find($pertama->id))->toBeNull()
            ->and($penggunaan->fresh())->not->toBeNull();

        $this->service->batalkan($kedua->pelatihanPeserta, $this->admin, 'Sakit');

        expect($penggunaan->fresh())->toBeNull();
    });
});

it('memperlakukan pendaftar baru dari desa yang sama seperti peserta biasa setelah ada yang batal', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create();
    $batal = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $kelas->id]);
    $this->service->batalkan($batal, $this->admin, 'Mutasi jabatan');
    $baru = PelatihanPeserta::factory()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['desa_id' => $batal->desa_id_saat_pelatihan]))
        ->create();

    $this->service->verifikasi($baru, $this->admin);

    expect($baru->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($baru->kelas_pelatihan_id)->toBeNull()
        ->and($this->service->kembalikan($batal))->toBe(StatusPendaftaran::Terverifikasi);
});
