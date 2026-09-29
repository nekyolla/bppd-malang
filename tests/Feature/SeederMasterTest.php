<?php

use App\Models\Asrama;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KamarAsrama;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StatusPtkpSeeder;
use Database\Seeders\SumberDanaSeeder;

it('mengisi delapan status PTKP', function () {
    $this->seed(StatusPtkpSeeder::class);

    expect(StatusPtkp::orderBy('id')->pluck('kode')->all())
        ->toBe(['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3']);
});

it('mengisi sumber dana dengan keterangan wajib hanya untuk Lainnya', function () {
    $this->seed(SumberDanaSeeder::class);

    expect(SumberDana::count())->toBe(5)
        ->and(SumberDana::where('butuh_keterangan', true)->pluck('nama')->all())->toBe(['Lainnya']);
});

it('aman dijalankan ulang tanpa menduplikasi atau mengaktifkan kembali data', function () {
    $this->seed([StatusPtkpSeeder::class, SumberDanaSeeder::class]);
    StatusPtkp::where('kode', 'K/3')->update(['is_aktif' => false]);
    SumberDana::where('nama', 'Mandiri')->update(['is_aktif' => false]);

    $this->seed([StatusPtkpSeeder::class, SumberDanaSeeder::class]);

    expect(StatusPtkp::count())->toBe(8)
        ->and(SumberDana::count())->toBe(5)
        ->and(StatusPtkp::where('kode', 'K/3')->value('is_aktif'))->toBeFalse()
        ->and(SumberDana::aktif()->pluck('nama')->all())->not->toContain('Mandiri');
});

it('mengisi data contoh lokal secara idempoten', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Jabatan::count())->toBe(count(DemoSeeder::JABATAN))
        ->and(JudulPelatihan::count())->toBe(4)
        ->and(Asrama::count())->toBe(2)
        ->and(KamarAsrama::count())->toBe(16)
        ->and(KamarAsrama::whereHas('asrama', fn ($q) => $q->where('nama_asrama', 'Melati'))->value('kapasitas'))->toBe(2);
});
