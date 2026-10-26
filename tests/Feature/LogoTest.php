<?php

use App\Support\Logo;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete([public_path('images/logo.png'), public_path('images/logo-gelap.png')]);
});

it('memakai teks merek selama berkas logo belum ada', function () {
    File::delete(public_path('images/logo.png'));

    if (Logo::url() !== null) {
        $this->markTestSkipped('Logo asli sudah dipasang di public/images.');
    }

    $this->get('/daftar')->assertOk()->assertSee('Balai Besar Pemerintahan Desa');
});

it('memakai logo dari public/images bila tersedia, termasuk varian gelap', function () {
    if (Logo::url() !== null) {
        $this->markTestSkipped('Logo asli sudah dipasang di public/images.');
    }

    File::ensureDirectoryExists(public_path('images'));
    File::put(public_path('images/logo.png'), 'png');

    expect(Logo::url())->toEndWith('/images/logo.png')
        ->and(Logo::urlGelap())->toBe(Logo::url());

    File::put(public_path('images/logo-gelap.png'), 'png');

    expect(Logo::urlGelap())->toEndWith('/images/logo-gelap.png');

    $this->get('/daftar')->assertOk()->assertSee('images/logo.png', escape: false);
});
