<?php

use App\Enums\Peran;
use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function masukDi(string $panel): Testable
{
    Filament::setCurrentPanel(Filament::getPanel($panel));

    return Livewire::test(Login::class);
}

it('menampilkan halaman login dengan isian NIK', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('NIK');
})->with(['/peserta/login', '/admin/login']);

it('peserta dapat masuk dengan NIK dan kata sandi', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    masukDi('peserta')
        ->fillForm(['nik' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/peserta');

    $this->assertAuthenticatedAs($user);
});

it('menolak kata sandi yang salah', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    masukDi('peserta')
        ->fillForm(['nik' => $user->nik, 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['nik']);

    $this->assertGuest();
});

it('menolak NIK yang tidak terdaftar', function () {
    masukDi('peserta')
        ->fillForm(['nik' => '3507010101909999', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['nik']);

    $this->assertGuest();
});

it('menolak akun yang dinonaktifkan', function () {
    $user = User::factory()->nonaktif()->peran(Peran::Peserta)->create();

    masukDi('peserta')
        ->fillForm(['nik' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['nik']);

    $this->assertGuest();
});

it('role internal dapat masuk panel admin', function (Peran $peran) {
    $user = User::factory()->peran($peran)->create();

    masukDi('admin')
        ->fillForm(['nik' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
})->with([Peran::Superadmin, Peran::Admin, Peran::Keuangan]);

it('peserta tidak dapat masuk panel admin', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    masukDi('admin')
        ->fillForm(['nik' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['nik']);

    $this->assertGuest();
});

it('role internal tidak dapat masuk panel peserta', function () {
    $user = User::factory()->peran(Peran::Admin)->create();

    masukDi('peserta')
        ->fillForm(['nik' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['nik']);

    $this->assertGuest();
});

it('akun yang dinonaktifkan saat sedang login tidak dapat membuka panel', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    $this->actingAs($user)->get('/peserta')->assertOk();

    $user->update(['is_aktif' => false]);

    $this->actingAs($user)->get('/peserta')->assertForbidden();
});

it('mengunci login setelah 5 percobaan gagal untuk NIK yang sama', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    $halaman = masukDi('peserta');

    foreach (range(1, Login::MAKS_PERCOBAAN_PER_NIK) as $i) {
        $halaman->fillForm(['nik' => $user->nik, 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['nik']);
    }

    $halaman->fillForm(['nik' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('penguncian satu NIK tidak memblokir NIK lain dari IP yang sama', function () {
    $dikunci = User::factory()->peran(Peran::Peserta)->create();
    $lain = User::factory()->peran(Peran::Peserta)->create();

    $halaman = masukDi('peserta');

    foreach (range(1, Login::MAKS_PERCOBAAN_PER_NIK) as $i) {
        $halaman->fillForm(['nik' => $dikunci->nik, 'password' => 'salah'])->call('authenticate');
    }

    masukDi('peserta')
        ->fillForm(['nik' => $lain->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($lain);
});
