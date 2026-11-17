<?php

use App\Http\Controllers\BerkasController;
use App\Http\Middleware\HeaderKeamanan;
use App\Livewire\Registrasi;
use Illuminate\Support\Facades\Route;

// Halaman publik ditunda (PF-06); sementara beranda langsung ke form registrasi.
Route::redirect('/', '/daftar');

Route::get('/daftar', Registrasi::class)->name('registrasi');

// Unduh sertifikat oleh peserta tanpa login (PF-40): tautan relatif bertanda tangan dari halaman /sertifikat.
Route::get('/sertifikat/unduh/{sertifikatPeserta}', [BerkasController::class, 'sertifikatPublik'])
    ->middleware(['signed:relative', 'throttle:120,60', HeaderKeamanan::class])
    ->name('sertifikat.publik.unduh');

Route::middleware(['auth', HeaderKeamanan::class])->group(function () {
    Route::get('/berkas/pendaftaran/{pelatihanPeserta}/{jenis}', [BerkasController::class, 'pendaftaran'])
        ->whereIn('jenis', array_keys(BerkasController::JENIS_PENDAFTARAN))
        ->name('berkas.pendaftaran');

    Route::get('/berkas/peserta/{peserta}/{jenis}', [BerkasController::class, 'peserta'])
        ->whereIn('jenis', array_keys(BerkasController::JENIS_PESERTA))
        ->name('berkas.peserta');

    Route::get('/berkas/sertifikat/{pelatihanPeserta}', [BerkasController::class, 'sertifikat'])
        ->name('berkas.sertifikat');
});
