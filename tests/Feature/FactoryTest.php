<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\TipeKamar;
use App\Enums\TipeLokasi;
use App\Models\AsramaPeserta;
use App\Models\Jabatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Presensi;
use App\Models\SertifikatPeserta;
use App\Models\SumberDana;

it('membuat pelatihan dengan tahun anggaran sesuai tanggal mulai dan empat hari', function () {
    $pelatihan = Pelatihan::factory()->tanggal('2027-01-04', '2027-01-07')->create();
    $bawaan = Pelatihan::factory()->create();

    expect($pelatihan->tahun_anggaran)->toBe(2027)
        ->and($pelatihan->jumlah_jp)->toBe(40)
        ->and($bawaan->status)->toBe(StatusPelatihan::Draft)
        ->and($bawaan->tahun_anggaran)->toBe($bawaan->tanggal_mulai->year)
        ->and($bawaan->jumlah_hari)->toBe(4)
        ->and(Pelatihan::factory()->luar()->create()->keterangan_lokasi)->not->toBeNull()
        ->and(Pelatihan::factory()->berjalan()->create()->status)->toBe(StatusPelatihan::Berjalan)
        ->and(Pelatihan::factory()->luar()->create()->tipe_lokasi)->toBe(TipeLokasi::Luar);
});

it('membuat pendaftaran terverifikasi dengan snapshot dari data peserta', function () {
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->create();
    $peserta = $pendaftaran->peserta;

    expect($pendaftaran->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($pendaftaran->jabatan_id_saat_pelatihan)->toBe($peserta->jabatan_id)
        ->and($pendaftaran->desa_id_saat_pelatihan)->toBe($peserta->desa_id)
        ->and($pendaftaran->status_ptkp_id_saat_pelatihan)->toBe($peserta->status_ptkp_id)
        ->and($pendaftaran->waktu_pelantikan_saat_pelatihan->equalTo($peserta->waktu_pelantikan))->toBeTrue()
        ->and($pendaftaran->diverifikasiOleh)->not->toBeNull()
        ->and($pendaftaran->data_isian['nik'])->toBe($peserta->nik)
        ->and($pendaftaran->pelatihan->status)->toBe(StatusPelatihan::Dibuka);
});

it('membuat pendaftaran baru, selesai, dan batal', function () {
    expect(PelatihanPeserta::factory()->create()->status)->toBe(StatusPendaftaran::Terdaftar)
        ->and(PelatihanPeserta::factory()->create()->desa_id_saat_pelatihan)->toBeNull()
        ->and(PelatihanPeserta::factory()->selesai()->create()->status)->toBe(StatusPendaftaran::Selesai)
        ->and(PelatihanPeserta::factory()->batal()->create()->alasan_batal)->not->toBeNull();
});

it('membuat presensi untuk peserta di kelas dan pelatihan yang sama', function () {
    $presensi = Presensi::factory()->create();
    $kelas = $presensi->lembarPresensi->kelasPelatihan;

    expect($presensi->pelatihanPeserta->kelas_pelatihan_id)->toBe($kelas->id)
        ->and($presensi->pelatihanPeserta->pelatihan_id)->toBe($kelas->pelatihan_id)
        ->and($presensi->lembarPresensi->tanggal->equalTo($kelas->pelatihan->tanggal_mulai))->toBeTrue();
});

it('membuat penghuni kamar di pelatihan yang sama dengan gender sesuai kamar', function () {
    $penghuni = AsramaPeserta::factory()
        ->for(PenggunaanKamar::factory()->perempuan())
        ->create();

    expect($penghuni->pelatihanPeserta->pelatihan_id)->toBe($penghuni->penggunaanKamar->pelatihan_id)
        ->and($penghuni->pelatihanPeserta->peserta->jenis_kelamin)->toBe(JenisKelamin::Perempuan)
        ->and(PenggunaanKamar::factory()->pasutri()->create()->tipe)->toBe(TipeKamar::Pasutri);
});

it('membuat sertifikat untuk pendaftaran yang sudah selesai', function () {
    $sertifikat = SertifikatPeserta::factory()->create();

    expect($sertifikat->pelatihanPeserta->status)->toBe(StatusPendaftaran::Selesai)
        ->and($sertifikat->file_sertifikat)->toStartWith('sertifikat/');
});

it('menyediakan state nonaktif dan sumber dana Lainnya', function () {
    expect(Jabatan::factory()->nonaktif()->create()->is_aktif)->toBeFalse()
        ->and(SumberDana::factory()->butuhKeterangan()->create()->butuh_keterangan)->toBeTrue();
});
