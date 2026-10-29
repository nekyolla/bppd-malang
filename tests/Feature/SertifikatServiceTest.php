<?php

use App\Models\PelatihanPeserta;
use App\Models\SertifikatPeserta;
use App\Models\User;
use App\Services\SertifikatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->service = app(SertifikatService::class);
    $this->admin = User::factory()->create();
    $this->pdf = fn (string $isi = '%PDF-1.4 sertifikat', int $kb = 0): UploadedFile => $kb > 0
        ? UploadedFile::fake()->create('sertifikat.pdf', $kb, 'application/pdf')
        : UploadedFile::fake()->createWithContent('sertifikat.pdf', $isi);
});

/**
 * @return array<string, list<string>>
 */
function galatSertifikat(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

it('mengunggah sertifikat PDF ke penyimpanan privat untuk peserta terverifikasi atau selesai', function (string $state) {
    $pendaftaran = pendaftaranBerstatus($state);

    $sertifikat = $this->service->unggah($pendaftaran, ($this->pdf)(), $this->admin);
    $path = $sertifikat->file_sertifikat;

    expect($path)->toMatch("#^sertifikat/{$pendaftaran->pelatihan_id}/{$pendaftaran->id}-[a-z0-9]{10}\\.pdf$#")
        ->and($sertifikat->diuploadOleh->is($this->admin))->toBeTrue()
        ->and($pendaftaran->sertifikat()->exists())->toBeTrue();

    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
})->with(['terverifikasi', 'selesai']);

it('mengganti sertifikat ke berkas baru lalu menghapus berkas lama', function () {
    $pendaftaran = PelatihanPeserta::factory()->selesai()->create();
    $adminLain = User::factory()->create();

    $lama = $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-lama'), $this->admin)->file_sertifikat;
    $sertifikat = $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-baru'), $adminLain);

    expect(SertifikatPeserta::count())->toBe(1)
        ->and($sertifikat->diupload_oleh)->toBe($adminLain->id)
        ->and($sertifikat->file_sertifikat)->not->toBe($lama)
        ->and(Storage::disk('local')->get($sertifikat->file_sertifikat))->toBe('%PDF-baru')
        ->and(Storage::disk('local')->allFiles())->toBe([$sertifikat->file_sertifikat]);
});

it('mempertahankan sertifikat lama dan tidak menyisakan berkas baru bila penyimpanan baris gagal', function () {
    $pendaftaran = PelatihanPeserta::factory()->selesai()->create();
    $lama = $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-lama'), $this->admin);
    SertifikatPeserta::saving(fn () => throw new RuntimeException('DB gagal'));

    expect(fn () => $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-baru'), $this->admin))
        ->toThrow(RuntimeException::class, 'DB gagal');

    expect($lama->fresh()->file_sertifikat)->toBe($lama->file_sertifikat)
        ->and(Storage::disk('local')->allFiles())->toBe([$lama->file_sertifikat])
        ->and(Storage::disk('local')->get($lama->file_sertifikat))->toBe('%PDF-lama');
});

function pendaftaranBerstatus(string $status): PelatihanPeserta
{
    return match ($status) {
        'terdaftar' => PelatihanPeserta::factory()->create(),
        'terverifikasi' => PelatihanPeserta::factory()->terverifikasi()->create(),
        'selesai' => PelatihanPeserta::factory()->selesai()->create(),
        'batal' => PelatihanPeserta::factory()->batal()->create(),
    };
}

it('menolak peserta menunggu verifikasi atau batal', function (string $status) {
    $pendaftaran = pendaftaranBerstatus($status);

    expect(galatSertifikat(fn () => $this->service->unggah($pendaftaran, ($this->pdf)(), $this->admin))['status'][0])
        ->toBe('Sertifikat hanya dapat diunggah untuk peserta terverifikasi atau selesai.')
        ->and(SertifikatPeserta::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['terdaftar', 'batal']);

it('menolak berkas bukan PDF atau lebih dari 2 MB tanpa menyentuh sertifikat lama', function () {
    $pendaftaran = PelatihanPeserta::factory()->selesai()->create();
    $lama = $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-lama'), $this->admin);

    expect(galatSertifikat(fn () => $this->service->unggah($pendaftaran, UploadedFile::fake()->image('sertifikat.jpg'), $this->admin))['berkas'][0])
        ->toBe('Sertifikat harus berupa PDF.')
        ->and(galatSertifikat(fn () => $this->service->unggah($pendaftaran, ($this->pdf)(kb: 2500), $this->admin))['berkas'][0])
        ->toBe('Ukuran sertifikat maksimal 2 MB.')
        ->and(Storage::disk('local')->get($lama->file_sertifikat))->toBe('%PDF-lama')
        ->and($lama->fresh()->updated_at->equalTo($lama->updated_at))->toBeTrue();
});

it('menghapus baris dan berkasnya, termasuk bila berkasnya sudah hilang', function () {
    $pertama = $this->service->unggah(PelatihanPeserta::factory()->selesai()->create(), ($this->pdf)(), $this->admin);
    $kedua = $this->service->unggah(PelatihanPeserta::factory()->selesai()->create(), ($this->pdf)(), $this->admin);
    Storage::disk('local')->delete($kedua->file_sertifikat);

    $this->service->hapus($pertama);
    $this->service->hapus($kedua);

    expect(SertifikatPeserta::count())->toBe(0);
    Storage::disk('local')->assertMissing($pertama->file_sertifikat);
});

it('mencatat penggantian sertifikat tepat satu kali di audit log, juga oleh admin yang sama', function (bool $adminSama) {
    $pendaftaran = PelatihanPeserta::factory()->selesai()->create();
    $sertifikat = $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-lama'), $this->admin);
    $pengganti = $adminSama ? $this->admin : User::factory()->create();
    $idTerakhir = (int) Activity::query()->max('id');

    $this->actingAs($pengganti);
    $this->service->unggah($pendaftaran, ($this->pdf)('%PDF-baru'), $pengganti);

    $baru = Activity::query()->whereMorphedTo('subject', $sertifikat)->where('id', '>', $idTerakhir)->get();

    expect($baru)->toHaveCount(1)
        ->and($baru->first()->event)->toBe('ganti')
        ->and($baru->first()->causer_id)->toBe($pengganti->id)
        ->and($baru->first()->properties['old']['diupload_oleh'])->toBe($this->admin->id)
        ->and($baru->first()->properties['old']['file_sertifikat'])->toBe($sertifikat->file_sertifikat)
        ->and($baru->first()->properties['attributes']['diupload_oleh'])->toBe($pengganti->id);
})->with(['admin sama' => true, 'admin lain' => false]);
