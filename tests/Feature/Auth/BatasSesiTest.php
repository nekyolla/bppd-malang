<?php

use App\Enums\Peran;
use App\Filament\Admin\Livewire\LoncengVerifikasi;
use App\Filament\Auth\Login;
use App\Http\Middleware\BatasSesiAdmin;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());
});

/**
 * Update Livewire sungguhan: snapshot komponen diambil dari halaman, dikirim ke endpoint update.
 */
function updateLivewire(TestResponse $halaman, string $nama = LoncengVerifikasi::NAMA): TestResponse
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $halaman->getContent(), $cocok);

    $snapshot = collect($cocok[1])
        ->map(fn (string $s): string => html_entity_decode($s, ENT_QUOTES))
        ->first(fn (string $s): bool => json_decode($s, true)['memo']['name'] === $nama);

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson(app('livewire')->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
    ]);
}

it('tidak menawarkan "Ingat saya" di halaman login', function () {
    auth()->logout();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)->assertFormFieldDoesNotExist('remember');
});

it('mengeluarkan akun setelah 30 menit tanpa aktivitas', function () {
    $this->get('/admin')->assertOk();

    $this->travel(31)->minutes();

    $this->get('/admin')->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
    $this->assertGuest();
    expect(session(BatasSesiAdmin::KUNCI_BERAKHIR))->toBe('diam');
});

it('memperpanjang sesi selama pengguna aktif', function () {
    $this->get('/admin')->assertOk();
    $this->travel(20)->minutes();
    $this->get('/admin')->assertOk();
    $this->travel(20)->minutes();

    $this->get('/admin')->assertOk();
    $this->assertAuthenticated();
});

it('tidak menghitung polling lonceng sebagai aktivitas', function () {
    $halaman = $this->get('/admin')->assertOk();

    $this->travel(20)->minutes();
    updateLivewire($halaman)->assertOk();
    $this->travel(15)->minutes();

    updateLivewire($halaman)->assertUnauthorized();
    $this->assertGuest();
});

it('menghitung update Livewire lain sebagai aktivitas', function () {
    $halaman = $this->get('/admin')->assertOk();

    $this->travel(20)->minutes();
    updateLivewire($halaman, 'app.filament.admin.pages.dashboard')->assertOk();
    $this->travel(15)->minutes();

    updateLivewire($halaman)->assertOk();
    $this->assertAuthenticated();
});

it('mengeluarkan akun setelah 8 jam sejak login walau terus aktif', function () {
    $this->withSession([BatasSesiAdmin::KUNCI_MULAI => now()->subMinutes(481)->getTimestamp(), BatasSesiAdmin::KUNCI_AKTIF => now()->getTimestamp()])
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    $this->assertGuest();
    expect(session(BatasSesiAdmin::KUNCI_BERAKHIR))->toBe('mutlak');
});

it('menjelaskan alasan keluar di halaman login', function (string $alasan, string $pesan) {
    auth()->logout();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    session([BatasSesiAdmin::KUNCI_BERAKHIR => $alasan]);

    Livewire::test(Login::class)->assertNotified(Notification::make()->title('Sesi berakhir')->body($pesan)->warning()->persistent());

    expect(session()->has(BatasSesiAdmin::KUNCI_BERAKHIR))->toBeFalse();
})->with([
    'tanpa aktivitas' => ['diam', 'Anda keluar otomatis setelah 30 menit tanpa aktivitas.'],
    'batas login' => ['mutlak', 'Anda sudah login lebih dari 8 jam. Silakan masuk kembali.'],
]);
