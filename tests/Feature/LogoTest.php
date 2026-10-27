<?php

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

it('memakai teks merek selama berkas logo belum ada', function () {
    expect(Logo::url())->toBeNull();

    $this->get('/daftar')->assertOk()->assertSee('Balai Besar Pemerintahan Desa');
});

it('memakai logo dari folder logo bila tersedia, termasuk varian gelap', function () {
    File::put(public_path('images-uji/logo.png'), 'png');

    expect(Logo::url())->toEndWith('/images-uji/logo.png')
        ->and(Logo::urlGelap())->toBe(Logo::url());

    File::put(public_path('images-uji/logo-gelap.png'), 'png');

    expect(Logo::urlGelap())->toEndWith('/images-uji/logo-gelap.png');

    $this->get('/daftar')->assertOk()->assertSee('images-uji/logo.png', escape: false);
});
