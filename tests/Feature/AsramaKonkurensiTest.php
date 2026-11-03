<?php

use App\Enums\JenisKelamin;
use App\Models\AsramaPeserta;
use App\Models\KamarAsrama;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\User;
use App\Services\AsramaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Penempatan bersamaan (ARCHITECTURE §11 butir 1, CLAUDE.md aturan 4).
 *
 * RefreshDatabase membungkus test dalam satu transaksi yang tidak terlihat koneksi lain. Di sini
 * koneksi bawaan dialihkan ke `kunci_a` (autocommit) supaya data ter-commit dan bisa dikunci oleh
 * koneksi kedua `kunci_b` yang berperan sebagai admin lain. Tabel yang terisi dikosongkan lagi di
 * afterEach.
 */
beforeEach(function () {
    $this->koneksiAsal = config('database.default');
    $mysql = config("database.connections.{$this->koneksiAsal}");
    config([
        'database.connections.kunci_a' => $mysql,
        'database.connections.kunci_b' => $mysql,
        'database.default' => 'kunci_a',
    ]);

    $this->tabelKosong = collect(DB::select('SHOW TABLES'))
        ->map(fn (object $baris): string => array_values((array) $baris)[0])
        ->filter(fn (string $tabel): bool => DB::table($tabel)->doesntExist())
        ->values();

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
});

afterEach(function () {
    DB::connection('kunci_b')->rollBack(0);

    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    $this->tabelKosong->each(fn (string $tabel) => DB::table($tabel)->delete());
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');

    DB::purge('kunci_a');
    DB::purge('kunci_b');
    config(['database.default' => $this->koneksiAsal]);
});

it('menunggu kunci kamar yang dipegang admin lain sehingga tempat tidur terakhir tidak terisi dua kali', function () {
    $service = app(AsramaService::class);
    $admin = User::factory()->create();
    $pelatihan = Pelatihan::factory()->dibuka()->create();
    $kamar = KamarAsrama::factory()->create(['kapasitas' => 2]);
    $peserta = fn (): PelatihanPeserta => PelatihanPeserta::factory()->terverifikasi()->for($pelatihan)
        ->for(Peserta::factory()->state(['jenis_kelamin' => JenisKelamin::LakiLaki]))->create();
    [$pertama, $keduaB, $ketigaA] = [$peserta(), $peserta(), $peserta()];
    $service->tempatkan($pertama, $kamar, $admin);
    $penggunaan = PenggunaanKamar::query()->where('kamar_asrama_id', $kamar->id)->sole();

    // Admin lain sedang menempatkan peserta di tempat tidur terakhir dan belum selesai.
    $b = DB::connection('kunci_b');
    $b->beginTransaction();
    $b->table('kamar_asrama')->where('id', $kamar->id)->lockForUpdate()->first();
    $b->table('asrama_peserta')->insert([
        'pelatihan_peserta_id' => $keduaB->id,
        'penggunaan_kamar_id' => $penggunaan->id,
        'ditempatkan_oleh' => $admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Tanpa kunci kamar, penempatan ini lolos karena belum melihat penghuni kedua.
    expect(fn () => $service->tempatkan($ketigaA, $kamar, $admin))
        ->toThrow(QueryException::class, 'Lock wait timeout')
        ->and(AsramaPeserta::query()->where('pelatihan_peserta_id', $ketigaA->id)->exists())->toBeFalse();

    $b->commit();

    try {
        $service->tempatkan($ketigaA, $kamar, $admin);
        $galat = null;
    } catch (ValidationException $e) {
        $galat = collect($e->errors())->flatten()->first();
    }

    expect($galat)->toBe("Kamar {$kamar->nama_tampilan} sudah penuh (2/2).")
        ->and($penggunaan->penghuni()->count())->toBe(2);
});
