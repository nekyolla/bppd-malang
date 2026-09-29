<?php

use App\Http\Controllers\BerkasController;
use App\Livewire\Registrasi;
use Illuminate\Support\Facades\Route;

// Halaman publik ditunda (PF-06); sementara beranda langsung ke form registrasi.
Route::redirect('/', '/daftar');

Route::get('/daftar', Registrasi::class)->name('registrasi');

Route::middleware('auth')->group(function () {
    Route::get('/berkas/pendaftaran/{pelatihanPeserta}/{jenis}', [BerkasController::class, 'pendaftaran'])
        ->whereIn('jenis', array_keys(BerkasController::JENIS_PENDAFTARAN))
        ->name('berkas.pendaftaran');
});
