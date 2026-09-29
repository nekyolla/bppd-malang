<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\StatusPresensi;
use App\Enums\TipeKamar;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

it('menampilkan status pendaftaran sesuai design system', function (StatusPendaftaran $status, string $label, string $warna, Heroicon $ikon) {
    expect($status->getLabel())->toBe($label)
        ->and($status->getColor())->toBe($warna)
        ->and($status->getIcon())->toBe($ikon);
})->with([
    [StatusPendaftaran::Terdaftar, 'Menunggu verifikasi', 'warning', Heroicon::OutlinedClock],
    [StatusPendaftaran::Terverifikasi, 'Terverifikasi', 'info', Heroicon::OutlinedCheckBadge],
    [StatusPendaftaran::Selesai, 'Selesai', 'success', Heroicon::OutlinedAcademicCap],
    [StatusPendaftaran::Batal, 'Batal', 'danger', Heroicon::OutlinedXCircle],
]);

it('hanya mengizinkan perpindahan status pendaftaran yang sah', function (StatusPendaftaran $dari, StatusPendaftaran $ke, bool $sah) {
    expect($dari->bisaMenjadi($ke))->toBe($sah);
})->with([
    [StatusPendaftaran::Terdaftar, StatusPendaftaran::Terverifikasi, true],
    [StatusPendaftaran::Terdaftar, StatusPendaftaran::Batal, true],
    [StatusPendaftaran::Terdaftar, StatusPendaftaran::Selesai, false],
    [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai, true],
    [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Batal, true],
    [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Terdaftar, false],
    [StatusPendaftaran::Selesai, StatusPendaftaran::Batal, false],
    [StatusPendaftaran::Batal, StatusPendaftaran::Terdaftar, false],
]);

it('menandai selesai dan batal sebagai status akhir', function () {
    expect(StatusPendaftaran::Selesai->isAkhir())->toBeTrue()
        ->and(StatusPendaftaran::Batal->isAkhir())->toBeTrue()
        ->and(StatusPendaftaran::Terdaftar->isAkhir())->toBeFalse()
        ->and(StatusPendaftaran::Terverifikasi->isAkhir())->toBeFalse();
});

it('menjalankan status pelatihan berurutan sampai selesai', function () {
    expect(StatusPelatihan::Draft->berikutnya())->toBe(StatusPelatihan::Dibuka)
        ->and(StatusPelatihan::Dibuka->berikutnya())->toBe(StatusPelatihan::Berjalan)
        ->and(StatusPelatihan::Berjalan->berikutnya())->toBe(StatusPelatihan::Selesai)
        ->and(StatusPelatihan::Selesai->berikutnya())->toBeNull();
});

it('menerima pendaftaran saat pelatihan dibuka atau berjalan', function () {
    expect(StatusPelatihan::Draft->menerimaPendaftaran())->toBeFalse()
        ->and(StatusPelatihan::Dibuka->menerimaPendaftaran())->toBeTrue()
        ->and(StatusPelatihan::Berjalan->menerimaPendaftaran())->toBeTrue()
        ->and(StatusPelatihan::Selesai->menerimaPendaftaran())->toBeFalse();
});

it('memakai singkatan H/I/S/A untuk presensi', function () {
    expect(array_map(fn (StatusPresensi $status) => $status->singkatan(), StatusPresensi::cases()))
        ->toBe(['H', 'I', 'S', 'A']);
});

it('menentukan tipe kamar dari jenis kelamin penghuni pertama', function () {
    expect(TipeKamar::dariJenisKelamin(JenisKelamin::LakiLaki))->toBe(TipeKamar::LakiLaki)
        ->and(TipeKamar::dariJenisKelamin(JenisKelamin::Perempuan))->toBe(TipeKamar::Perempuan)
        ->and(TipeKamar::Pasutri->getColor())->toBe(Color::Violet);
});
