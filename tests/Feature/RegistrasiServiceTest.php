<?php

use App\Enums\StatusPendaftaran;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Services\RegistrasiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->service = app(RegistrasiService::class);
    $this->pelatihan = Pelatihan::factory()->dibuka()->create();
    $this->isian = fn (array $ubah = []): array => [
        'pelatihan_id' => $this->pelatihan->id,
        'nik' => '3507010101900001',
        'nama_lengkap' => '  Siti Aminah ',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Malang',
        'tanggal_lahir' => '1990-01-01',
        'agama' => 'Islam',
        'alamat_domisili' => 'Jl. Raya Donomulyo 1',
        'no_hp' => '0812-3456-7890',
        'email' => ' Siti@Contoh.ID ',
        'jenjang_pendidikan' => 'S1',
        'jurusan_pendidikan' => '',
        'jabatan_id' => Jabatan::factory()->create()->id,
        'waktu_pelantikan' => '2022-03-01',
        'desa_id' => Desa::factory()->create()->id,
        'alamat_kantor_desa' => 'Jl. Balai Desa 2',
        'npwp' => '12.345.678.9-012.000',
        'status_ptkp_id' => StatusPtkp::factory()->create()->id,
        'sumber_dana_id' => SumberDana::factory()->create()->id,
        'sumber_dana_keterangan' => null,
        'ktp' => UploadedFile::fake()->create('ktp saya.pdf', 500, 'application/pdf'),
        'foto' => UploadedFile::fake()->image('foto.jpg', 300, 400),
        'surat_tugas' => UploadedFile::fake()->create('surat.pdf', 800, 'application/pdf'),
        ...$ubah,
    ];
});

function galatRegistrasi(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

it('membuat peserta dan pendaftaran baru untuk NIK baru', function () {
    $pendaftaran = $this->service->daftar(($this->isian)());
    $peserta = $pendaftaran->peserta;
    $folder = "pendaftaran/{$pendaftaran->id}";

    expect($pendaftaran->status)->toBe(StatusPendaftaran::Terdaftar)
        ->and($peserta->nama_lengkap)->toBe('Siti Aminah')
        ->and($peserta->no_hp)->toBe('081234567890')
        ->and($peserta->email)->toBe('siti@contoh.id')
        ->and($peserta->npwp)->toBe('123456789012000')
        ->and($peserta->jurusan_pendidikan)->toBeNull()
        ->and($peserta->file_ktp)->toBe("{$folder}/ktp.pdf")
        ->and($peserta->foto)->toBe("{$folder}/foto.jpg")
        ->and($pendaftaran->file_surat_tugas)->toBe("{$folder}/surat-tugas.pdf")
        ->and($pendaftaran->data_isian['nik'])->toBe('3507010101900001')
        ->and($pendaftaran->data_isian['file_ktp'])->toBe("{$folder}/ktp.pdf");

    Storage::disk('local')->assertExists(["{$folder}/ktp.pdf", "{$folder}/foto.jpg", "{$folder}/surat-tugas.pdf"]);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('tidak menimpa data peserta lama saat NIK yang sama mendaftar pelatihan lain', function () {
    $pertama = $this->service->daftar(($this->isian)());
    $pelatihanLain = Pelatihan::factory()->berjalan()->create();

    $kedua = $this->service->daftar(($this->isian)([
        'pelatihan_id' => $pelatihanLain->id,
        'nama_lengkap' => 'Nama Berbeda',
        'alamat_domisili' => 'Alamat baru',
    ]));

    $peserta = Peserta::sole();

    expect($kedua->peserta_id)->toBe($pertama->peserta_id)
        ->and($peserta->nama_lengkap)->toBe('Siti Aminah')
        ->and($peserta->alamat_domisili)->toBe('Jl. Raya Donomulyo 1')
        ->and($peserta->file_ktp)->toBe("pendaftaran/{$pertama->id}/ktp.pdf")
        ->and($kedua->data_isian['nama_lengkap'])->toBe('Nama Berbeda')
        ->and($kedua->data_isian['file_ktp'])->toBe("pendaftaran/{$kedua->id}/ktp.pdf");

    Storage::disk('local')->assertExists("pendaftaran/{$kedua->id}/ktp.pdf");
});

it('menolak NIK yang sudah terdaftar di pelatihan yang sama tanpa menyisakan berkas', function () {
    $this->service->daftar(($this->isian)());

    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)()))['nik'][0])
        ->toBe(RegistrasiService::PESAN_NIK_GANDA)
        ->and(PelatihanPeserta::count())->toBe(1)
        ->and(Storage::disk('local')->directories('pendaftaran'))->toHaveCount(1);
});

it('hanya menerima pelatihan yang sedang dibuka atau berjalan', function (string $state) {
    $pelatihan = ($state === 'draft' ? Pelatihan::factory() : Pelatihan::factory()->{$state}())->create();

    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)(['pelatihan_id' => $pelatihan->id])))['pelatihan_id'][0])
        ->toBe('Pelatihan ini tidak sedang menerima pendaftaran.');
})->with(['draft', 'selesai']);

it('menolak data master yang dinonaktifkan', function (string $kolom, string $model) {
    $nonaktif = $model::factory()->nonaktif()->create();

    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)([$kolom => $nonaktif->id]))))
        ->toHaveKey($kolom);
})->with([
    ['jabatan_id', Jabatan::class],
    ['status_ptkp_id', StatusPtkp::class],
    ['sumber_dana_id', SumberDana::class],
]);

it('mewajibkan keterangan untuk sumber dana Lainnya', function () {
    $lainnya = SumberDana::factory()->butuhKeterangan()->create();

    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)(['sumber_dana_id' => $lainnya->id, 'sumber_dana_keterangan' => ' ']))))
        ->toHaveKey('sumber_dana_keterangan');

    $pendaftaran = $this->service->daftar(($this->isian)(['sumber_dana_id' => $lainnya->id, 'sumber_dana_keterangan' => 'Dana CSR']));

    expect($pendaftaran->sumber_dana_keterangan)->toBe('Dana CSR');
});

it('menolak berkas lebih dari 2 MB atau bertipe salah', function (string $isian, Closure $berkas) {
    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)([$isian => $berkas()]))))
        ->toHaveKey($isian)
        ->and(Peserta::count())->toBe(0);
})->with([
    'KTP 3 MB' => ['ktp', fn () => UploadedFile::fake()->create('ktp.pdf', 3000, 'application/pdf')],
    'KTP berupa gambar' => ['ktp', fn () => UploadedFile::fake()->image('ktp.jpg')],
    'foto PNG' => ['foto', fn () => UploadedFile::fake()->image('foto.png')],
    'surat tugas Word' => ['surat_tugas', fn () => UploadedFile::fake()->create('surat.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
]);

it('menolak path berkas berupa teks dari klien', function () {
    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)(['ktp' => 'pendaftaran/1/ktp.pdf']))))
        ->toHaveKey('ktp');
});

it('memvalidasi NIK, nomor HP, dan NPWP', function (string $kolom, string $nilai) {
    expect(galatRegistrasi(fn () => $this->service->daftar(($this->isian)([$kolom => $nilai]))))
        ->toHaveKey($kolom);
})->with([
    ['nik', '350701010190'],
    ['no_hp', '0212345'],
    ['npwp', '1234'],
]);
