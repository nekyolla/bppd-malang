<?php

use App\Livewire\Registrasi;
use Illuminate\Support\Facades\Route;

// Halaman publik ditunda (PF-06); sementara beranda langsung ke form registrasi.
Route::redirect('/', '/daftar');

Route::get('/daftar', Registrasi::class)->name('registrasi');
