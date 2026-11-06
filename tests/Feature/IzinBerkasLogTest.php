<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// Di server, artisan dijalankan user deploy sedangkan web oleh www-data (satu grup).
// Berkas log harus bisa ditulis grup; bila tidak, siapa pun yang membuatnya lebih dulu
// mengunci yang lain dan galat web tidak tercatat (ditemukan saat uji panduan deployment).

dataset('kanal log berkas', ['single', 'daily']);

it('membuat berkas log yang bisa ditulis grup', function (string $kanal) {
    $folder = storage_path('framework/testing/log-'.$kanal);
    File::deleteDirectory($folder);
    File::ensureDirectoryExists($folder);

    config(["logging.channels.{$kanal}.path" => $folder.'/laravel.log']);

    $umaskLama = umask(0022);

    try {
        Log::channel($kanal)->error('uji izin berkas log');
    } finally {
        umask($umaskLama);
    }

    $berkas = File::files($folder);

    expect($berkas)->toHaveCount(1)
        ->and(fileperms($berkas[0]->getPathname()) & 0777)->toBe(0664);

    File::deleteDirectory($folder);
})->with('kanal log berkas');
