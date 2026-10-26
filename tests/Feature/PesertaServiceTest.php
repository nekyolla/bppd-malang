<?php

use App\Models\Desa;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Services\PesertaService;
use App\Support\IsianPeserta;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(PesertaService::class);
});

it('merapikan data peserta tanpa mengubah snapshot pelatihan yang sudah diverifikasi', function () {
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->create();
    $peserta = $pendaftaran->peserta;
    $desaLama = $pendaftaran->desa_id_saat_pelatihan;
    $desaBaru = Desa::factory()->create();

    $this->service->ubah($peserta, [...IsianPeserta::dariPeserta($peserta), 'nama_lengkap' => '  Nama Sesuai KTP ', 'desa_id' => $desaBaru->id]);

    expect($peserta->fresh()->nama_lengkap)->toBe('Nama Sesuai KTP')
        ->and($peserta->fresh()->desa_id)->toBe($desaBaru->id)
        ->and($pendaftaran->fresh()->desa_id_saat_pelatihan)->toBe($desaLama);
});

it('menolak NIK yang sudah dipakai peserta lain', function () {
    $peserta = Peserta::factory()->create();
    $lain = Peserta::factory()->create();

    expect(fn () => $this->service->ubah($peserta, [...IsianPeserta::dariPeserta($peserta), 'nik' => $lain->nik]))
        ->toThrow(ValidationException::class);

    expect($peserta->fresh()->nik)->not->toBe($lain->nik);
});
