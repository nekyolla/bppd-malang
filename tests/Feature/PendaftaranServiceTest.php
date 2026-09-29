<?php

use App\Enums\StatusPendaftaran;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\User;
use App\Services\PendaftaranService;
use App\Support\IsianPeserta;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->service = app(PendaftaranService::class);
    $this->admin = User::factory()->create();
});

function galatVerifikasi(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

it('memverifikasi pendaftaran dan mengisi snapshot dari data peserta', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $peserta = $pendaftaran->peserta;

    $this->service->verifikasi($pendaftaran, $this->admin, catatan: '  KTP sesuai  ');

    expect($pendaftaran->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($pendaftaran->jabatan_id_saat_pelatihan)->toBe($peserta->jabatan_id)
        ->and($pendaftaran->desa_id_saat_pelatihan)->toBe($peserta->desa_id)
        ->and($pendaftaran->status_ptkp_id_saat_pelatihan)->toBe($peserta->status_ptkp_id)
        ->and($pendaftaran->waktu_pelantikan_saat_pelatihan->toDateString())->toBe($peserta->waktu_pelantikan->toDateString())
        ->and($pendaftaran->diverifikasiOleh->is($this->admin))->toBeTrue()
        ->and($pendaftaran->diverifikasi_pada)->not->toBeNull()
        ->and($pendaftaran->catatan_panitia)->toBe('KTP sesuai');
});

it('menerapkan koreksi admin ke data peserta sebelum mengambil snapshot', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $jabatanBaru = Jabatan::factory()->nonaktif()->create();
    $desaBaru = Desa::factory()->create();

    $this->service->verifikasi($pendaftaran, $this->admin, [
        ...$this->service->isianVerifikasi($pendaftaran),
        'nama_lengkap' => ' Siti Aminah ',
        'jabatan_id' => $jabatanBaru->id,
        'desa_id' => $desaBaru->id,
        'no_hp' => '0812 3456 7890',
    ]);

    $peserta = $pendaftaran->peserta->fresh();

    expect($peserta->nama_lengkap)->toBe('Siti Aminah')
        ->and($peserta->no_hp)->toBe('081234567890')
        ->and($pendaftaran->jabatan_id_saat_pelatihan)->toBe($jabatanBaru->id)
        ->and($pendaftaran->desa_id_saat_pelatihan)->toBe($desaBaru->id);
});

it('menunjukkan dan menerapkan isian berbeda dari NIK yang pernah terdaftar', function () {
    $lama = PelatihanPeserta::factory()->selesai()->create();
    $peserta = $lama->peserta;
    $jabatanBaru = Jabatan::factory()->create();

    $baru = PelatihanPeserta::factory()->for($peserta)->create([
        'data_isian' => [
            ...IsianPeserta::dariPeserta($peserta),
            'jabatan_id' => $jabatanBaru->id,
            'waktu_pelantikan' => '2025-01-15',
            'file_ktp' => 'pendaftaran/99/ktp.pdf',
            'foto' => 'pendaftaran/99/foto.jpg',
        ],
    ]);

    expect(array_keys($this->service->perbedaanIsian($baru)))->toBe(['jabatan_id', 'waktu_pelantikan'])
        ->and($this->service->perbedaanIsian($lama))->toBe([]);

    $this->service->verifikasi($baru, $this->admin, $this->service->isianVerifikasi($baru));

    $peserta->refresh();

    expect($peserta->jabatan_id)->toBe($jabatanBaru->id)
        ->and($peserta->file_ktp)->toBe('pendaftaran/99/ktp.pdf')
        ->and($baru->jabatan_id_saat_pelatihan)->toBe($jabatanBaru->id)
        ->and($baru->tahun_menjabat)->toBe($baru->pelatihan->tanggal_mulai->year - 2025 + 1)
        ->and($lama->fresh()->jabatan_id_saat_pelatihan)->not->toBe($jabatanBaru->id);
});

it('hanya memverifikasi pendaftaran yang menunggu verifikasi', function (string $state) {
    $pendaftaran = PelatihanPeserta::factory()->{$state}()->create();
    $status = $pendaftaran->status;

    expect(galatVerifikasi(fn () => $this->service->verifikasi($pendaftaran, $this->admin)))->toHaveKey('status')
        ->and($pendaftaran->fresh()->status)->toBe($status);
})->with(['terverifikasi', 'selesai', 'batal']);

it('menolak koreksi yang tidak valid tanpa mengubah apa pun', function (string $kolom, mixed $nilai) {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $namaAwal = $pendaftaran->peserta->nama_lengkap;
    Peserta::factory()->create(['nik' => '3507019999990001']);

    expect(galatVerifikasi(fn () => $this->service->verifikasi($pendaftaran, $this->admin, [
        ...$this->service->isianVerifikasi($pendaftaran),
        'nama_lengkap' => 'Nama Baru',
        $kolom => $nilai,
    ])))->toHaveKey($kolom)
        ->and($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terdaftar)
        ->and($pendaftaran->peserta->fresh()->nama_lengkap)->toBe($namaAwal);
})->with([
    'NIK milik peserta lain' => ['nik', '3507019999990001'],
    'nomor HP' => ['no_hp', '12345'],
    'desa tidak ada' => ['desa_id', 999999],
]);

it('memverifikasi massal hanya pendaftaran tanpa perbedaan isian', function () {
    $siap = PelatihanPeserta::factory()->count(2)->create();
    $sudah = PelatihanPeserta::factory()->terverifikasi()->create();
    $berbeda = PelatihanPeserta::factory()->create();
    $berbeda->update(['data_isian' => [...$berbeda->data_isian, 'nama_lengkap' => 'Nama Lain']]);

    $hasil = $this->service->verifikasiMassal([...$siap, $sudah, $berbeda], $this->admin);

    expect($hasil)->toBe(['terverifikasi' => 2, 'dilewati' => 2])
        ->and($siap->map(fn ($p) => $p->fresh()->status)->unique()->all())->toBe([StatusPendaftaran::Terverifikasi])
        ->and($berbeda->fresh()->status)->toBe(StatusPendaftaran::Terdaftar);
});

it('mencatat verifikasi di audit log atas nama admin', function () {
    $this->actingAs($this->admin);
    $pendaftaran = PelatihanPeserta::factory()->create();

    $this->service->verifikasi($pendaftaran, $this->admin);

    $log = Activity::query()->where('subject_type', $pendaftaran->getMorphClass())->where('subject_id', $pendaftaran->id)->latest('id')->first();

    expect($log->causer->is($this->admin))->toBeTrue()
        ->and($log->attribute_changes['attributes']['status'])->toBe('terverifikasi');
});
