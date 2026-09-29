<?php

use App\Enums\Peran;
use App\Models\PelatihanPeserta;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->pendaftaran = PelatihanPeserta::factory()->create();
    $folder = "pendaftaran/{$this->pendaftaran->id}";

    foreach (['ktp.pdf' => '%PDF-ktp', 'foto.jpg' => 'jpeg-foto', 'surat-tugas.pdf' => '%PDF-surat'] as $nama => $isi) {
        Storage::disk('local')->put("{$folder}/{$nama}", $isi);
    }

    $this->pendaftaran->update([
        'file_surat_tugas' => "{$folder}/surat-tugas.pdf",
        'data_isian' => [...$this->pendaftaran->data_isian, 'file_ktp' => "{$folder}/ktp.pdf", 'foto' => "{$folder}/foto.jpg"],
    ]);

    $this->url = fn (string $jenis): string => route('berkas.pendaftaran', [$this->pendaftaran, $jenis]);
});

it('mengarahkan pengunjung tanpa login ke halaman login admin', function () {
    $this->get(($this->url)('ktp'))->assertRedirect('/admin/login');
});

it('membuka berkas pendaftaran untuk superadmin dan admin', function (Peran $peran, string $jenis, string $isi, string $nama) {
    $response = $this->actingAs(User::factory()->peran($peran)->create())
        ->get(($this->url)($jenis))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe($isi)
        ->and($response->headers->get('Content-Disposition'))->toContain("inline; filename={$nama}-{$this->pendaftaran->id}")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([Peran::Superadmin, Peran::Admin])->with([
    ['ktp', '%PDF-ktp', 'ktp'],
    ['foto', 'jpeg-foto', 'pas-foto'],
    ['surat-tugas', '%PDF-surat', 'surat-tugas'],
]);

it('menolak akun tanpa role internal atau yang dinonaktifkan', function (User $user) {
    $this->actingAs($user)->get(($this->url)('ktp'))->assertForbidden();
})->with([
    'tanpa role' => fn () => User::factory()->create(),
    'nonaktif' => fn () => User::factory()->nonaktif()->peran(Peran::Admin)->create(),
]);

it('mengembalikan 404 untuk jenis berkas yang tidak dikenal atau berkas yang hilang', function () {
    $admin = User::factory()->peran(Peran::Admin)->create();

    $this->actingAs($admin)->get("/berkas/pendaftaran/{$this->pendaftaran->id}/npwp")->assertNotFound();
    $this->actingAs($admin)->get("/berkas/pendaftaran/{$this->pendaftaran->id}/..%2F..%2F.env")->assertNotFound();

    Storage::disk('local')->delete("pendaftaran/{$this->pendaftaran->id}/ktp.pdf");
    $this->actingAs($admin)->get(($this->url)('ktp'))->assertNotFound();

    $tanpaBerkas = PelatihanPeserta::factory()->create();
    $this->actingAs($admin)->get(route('berkas.pendaftaran', [$tanpaBerkas, 'surat-tugas']))->assertNotFound();
});
