<?php

use App\Support\Instansi;
use App\Support\Logo;
use Illuminate\Support\Facades\File;

// Folder uji terpisah: test tidak boleh menyentuh logo asli di public/images.
beforeEach(function () {
    config(['bbpd.logo_folder' => 'images-uji']);
    File::ensureDirectoryExists(public_path('images-uji'));
});

afterEach(function () {
    File::deleteDirectory(public_path('images-uji'));
});

it('menampilkan nama instansi sebagai teks, juga tanpa berkas lambang (PF-21)', function () {
    expect(Logo::lambang())->toBeNull();

    $this->get('/daftar')->assertOk()
        ->assertSee(Instansi::NAMA)
        ->assertSee(Instansi::KEMENTERIAN)
        ->assertDontSee('BBPD');
});

it('memakai lambang dari folder logo bila tersedia', function () {
    File::put(public_path('images-uji/lambang.png'), 'png');

    expect(Logo::lambang())->toEndWith('/images-uji/lambang.png');

    $this->get('/daftar')->assertOk()->assertSee('images-uji/lambang.png', escape: false);
});

it('menampilkan halaman login dua kolom dengan nama instansi dan sistem (PF-20, PF-32, PF-33)', function () {
    $this->get('/admin/login')->assertOk()
        ->assertSee(Instansi::NAMA)
        ->assertSee('Sistem Informasi Pengembangan Kapasitas Pemerintahan Desa')
        ->assertDontSee('Khusus panitia')
        ->assertSee('fi-masuk', escape: false)
        ->assertDontSee('BBPD');
});
