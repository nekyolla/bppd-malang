<?php

use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SertifikatPeserta;
use App\Services\SertifikatService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/*
 * PF-40: peserta mengunduh sendiri sertifikatnya di halaman publik dengan NIK.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->service = app(SertifikatService::class);
});

function sertifikatUntuk(Peserta $peserta, string $mulai, ?string $nomor = null): SertifikatPeserta
{
    $pelatihan = Pelatihan::factory()->selesai()->tanggal($mulai, date('Y-m-d', strtotime($mulai.' +3 days')))->create();
    $pendaftaran = PelatihanPeserta::factory()->selesai()->for($pelatihan)->for($peserta)->create();
    $path = "sertifikat/{$pelatihan->id}/{$pendaftaran->id}-uji.pdf";
    Storage::disk('local')->put($path, '%PDF-uji');

    return SertifikatPeserta::factory()->for($pendaftaran, 'pelatihanPeserta')->create(['file_sertifikat' => $path, 'nomor_sertifikat' => $nomor]);
}

describe('pencarian', function () {
    it('mengembalikan null untuk NIK tak dikenal dan NIK tanpa sertifikat', function () {
        $tanpaSertifikat = Peserta::factory()->create(['nik' => '3507000000000001']);
        PelatihanPeserta::factory()->batal()->for($tanpaSertifikat)->create();
        PelatihanPeserta::factory()->for($tanpaSertifikat)->create();

        expect($this->service->untukPublik('3507999999999999'))->toBeNull()
            ->and($this->service->untukPublik('3507000000000001'))->toBeNull();
    });

    it('hanya mengembalikan sertifikat milik NIK itu, terbaru dulu', function () {
        $ani = Peserta::factory()->create(['nik' => '3507000000000001', 'nama_lengkap' => 'Ani Lestari']);
        $lama = sertifikatUntuk($ani, '2025-05-05');
        $baru = sertifikatUntuk($ani, '2026-10-05', '001/BBPKPD/2026');
        sertifikatUntuk(Peserta::factory()->create(['nik' => '3507000000000002']), '2026-09-01');

        $hasil = $this->service->untukPublik('3507000000000001');

        expect($hasil['nama'])->toBe('An* Le*****')
            ->and($hasil['daftar'])->toHaveCount(2)
            ->and(array_column($hasil['daftar'], 'pelatihan'))->toBe([
                $baru->pelatihanPeserta->pelatihan->nama_tampilan,
                $lama->pelatihanPeserta->pelatihan->nama_tampilan,
            ])
            ->and($hasil['daftar'][0])->toMatchArray(['tanggal' => '5–8 Oktober 2026', 'nomor' => '001/BBPKPD/2026'])
            ->and($hasil['daftar'][1]['nomor'])->toBeNull();
    });

    it('memberi tautan unduh relatif bertanda tangan dan kedaluwarsa', function () {
        $sertifikat = sertifikatUntuk(Peserta::factory()->create(['nik' => '3507000000000001']), '2026-10-05');

        $url = $this->service->untukPublik('3507000000000001')['daftar'][0]['url'];

        expect($url)->toStartWith("/sertifikat/unduh/{$sertifikat->id}?")
            ->toContain('expires=')
            ->toContain('signature=');
    });
});

describe('unduhan', function () {
    beforeEach(function () {
        $this->sertifikat = sertifikatUntuk(Peserta::factory()->create(['nik' => '3507000000000001']), '2026-10-05');
        $this->url = $this->service->untukPublik('3507000000000001')['daftar'][0]['url'];
    });

    it('mengunduh sertifikat lewat tautan bertanda tangan dan mencatatnya', function () {
        $respons = $this->get($this->url);

        $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($respons->headers->get('Content-Disposition'))
            ->toContain('attachment')
            ->toContain(Str::slug('sertifikat '.$this->sertifikat->pelatihanPeserta->pelatihan->nama_tampilan).'.pdf')
            ->and($respons->streamedContent())->toBe('%PDF-uji')
            ->and(Activity::query()->where('event', 'unduh_publik')->sole())
            ->causer_id->toBeNull()
            ->subject->is($this->sertifikat)->toBeTrue();
    });

    it('menolak tautan tanpa tanda tangan atau yang kedaluwarsa', function () {
        $this->get("/sertifikat/unduh/{$this->sertifikat->id}")->assertForbidden();

        $this->travel(16)->minutes();
        $this->get($this->url)->assertForbidden();

        expect(Activity::query()->where('event', 'unduh_publik')->exists())->toBeFalse();
    });

    it('tetap sah bila diakses lewat host atau skema lain (di balik proxy)', function () {
        $this->get('https://lain.test'.$this->url)->assertOk();
    });

    it('memberi 404 bila berkas sudah hilang', function () {
        Storage::disk('local')->delete($this->sertifikat->file_sertifikat);

        $this->get($this->url)->assertNotFound();
    });
});
