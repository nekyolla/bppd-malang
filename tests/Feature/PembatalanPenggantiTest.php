<?php

use App\Enums\JenisKelamin;
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
use App\Services\PenggantiService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 09:00');
    $this->service = app(PendaftaranService::class);
    $this->pengganti = app(PenggantiService::class);
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

describe('pengganti', function () {
    beforeEach(function () {
        $this->kelas = KelasPelatihan::factory()->for($this->pelatihan)->create();
        $this->batal = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelas->id]);
        $this->service->batalkan($this->batal, $this->admin, 'Mutasi jabatan');
        $this->desaId = $this->batal->desa_id_saat_pelatihan;
        $this->calon = PelatihanPeserta::factory()->for($this->pelatihan)
            ->for(Peserta::factory()->state(['desa_id' => $this->desaId]))
            ->create();
    });

    it('menetapkan pengganti saat verifikasi dan memasukkannya ke kelas yang digantikan', function () {
        expect($this->pengganti->kandidat($this->calon, $this->desaId)->modelKeys())->toBe([$this->batal->id]);

        $this->service->verifikasi($this->calon, $this->admin, menggantikanId: $this->batal->id);

        expect($this->calon->status)->toBe(StatusPendaftaran::Terverifikasi)
            ->and($this->calon->menggantikan->is($this->batal))->toBeTrue()
            ->and($this->batal->fresh()->pengganti->is($this->calon))->toBeTrue()
            ->and($this->calon->kelas_pelatihan_id)->toBe($this->kelas->id)
            ->and($this->pengganti->kandidat($this->calon, $this->desaId))->toBeEmpty();
    });

    it('mengingatkan bila pengganti berbeda jenis kelamin dengan yang digantikan', function () {
        $sama = $this->batal->peserta->jenis_kelamin;
        $beda = $sama === JenisKelamin::LakiLaki ? JenisKelamin::Perempuan : JenisKelamin::LakiLaki;

        expect($this->pengganti->bedaJenisKelamin($this->batal->id, $sama))->toBeFalse()
            ->and($this->pengganti->bedaJenisKelamin($this->batal->id, $beda))->toBeTrue()
            ->and($this->pengganti->bedaJenisKelamin($this->batal->id, $beda->value))->toBeTrue()
            ->and($this->pengganti->bedaJenisKelamin($this->batal->id, null))->toBeFalse();
    });

    it('menolak pengganti dari desa lain', function () {
        $calonLain = PelatihanPeserta::factory()->for($this->pelatihan)->create();

        expect($this->pengganti->kandidat($calonLain, $calonLain->peserta->desa_id))->toBeEmpty()
            ->and(galatBatal(fn () => $this->service->verifikasi($calonLain, $this->admin, menggantikanId: $this->batal->id))['menggantikan_id'][0])
            ->toBe('Pengganti harus dari desa yang sama.')
            ->and($calonLain->fresh()->status)->toBe(StatusPendaftaran::Terdaftar);
    });

    it('menolak pengganti dari pelatihan lain', function () {
        $calonLain = PelatihanPeserta::factory()
            ->for(Pelatihan::factory()->dibuka()->tanggal('2026-10-12', '2026-10-15'))
            ->for(Peserta::factory()->state(['desa_id' => $this->desaId]))
            ->create();

        expect($this->pengganti->kandidat($calonLain, $this->desaId))->toBeEmpty()
            ->and(galatBatal(fn () => $this->service->verifikasi($calonLain, $this->admin, menggantikanId: $this->batal->id)))
            ->toHaveKey('menggantikan_id');
    });

    it('menolak menggantikan peserta yang tidak batal', function () {
        $aktif = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
            ->for(Peserta::factory()->state(['desa_id' => $this->desaId]))->create();

        expect(galatBatal(fn () => $this->service->verifikasi($this->calon, $this->admin, menggantikanId: $aktif->id))['menggantikan_id'][0])
            ->toBe('Hanya peserta yang batal yang dapat digantikan.');
    });

    it('hanya satu pengganti untuk satu peserta batal', function () {
        $this->service->verifikasi($this->calon, $this->admin, menggantikanId: $this->batal->id);
        $calonKedua = PelatihanPeserta::factory()->for($this->pelatihan)
            ->for(Peserta::factory()->state(['desa_id' => $this->desaId]))->create();

        expect(galatBatal(fn () => $this->service->verifikasi($calonKedua, $this->admin, menggantikanId: $this->batal->id))['menggantikan_id'][0])
            ->toBe('Peserta ini sudah memiliki pengganti.')
            ->and($calonKedua->fresh()->status)->toBe(StatusPendaftaran::Terdaftar);
    });

    it('menolak pengganti mulai tanggal mulai pelatihan', function (string $sekarang) {
        Carbon::setTestNow($sekarang);

        expect($this->pengganti->kandidat($this->calon, $this->desaId))->toBeEmpty()
            ->and(galatBatal(fn () => $this->service->verifikasi($this->calon, $this->admin, menggantikanId: $this->batal->id))['menggantikan_id'][0])
            ->toBe('Pengganti tidak dapat ditetapkan karena pelatihan sudah dimulai.');
    })->with(['hari mulai' => '2026-10-05 07:00', 'saat berjalan' => '2026-10-06 10:00']);
});
