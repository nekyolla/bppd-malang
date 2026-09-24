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

it('menampilkan halaman login dengan isian username', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('Username');
})->with(['/peserta/login', '/admin/login']);

it('menampilkan petunjuk NIK hanya di login peserta', function () {
    $this->get('/peserta/login')->assertSee('Masukkan NIK 16 digit');
    $this->get('/admin/login')->assertDontSee('Masukkan NIK 16 digit');
});

it('peserta dapat masuk dengan NIK sebagai username', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    masukDi('peserta')
        ->fillForm(['username' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/peserta');

    $this->assertAuthenticatedAs($user);
});

it('menolak kata sandi yang salah', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    masukDi('peserta')
        ->fillForm(['username' => $user->nik, 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['username' => ['Username atau kata sandi salah.']]);

    $this->assertGuest();
});

it('menolak username yang tidak terdaftar', function (string $username) {
    masukDi('peserta')
        ->fillForm(['username' => $username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
})->with(['NIK' => '3507010101909999', 'username' => 'tidakada']);

it('menolak akun yang dinonaktifkan', function () {
    $user = User::factory()->nonaktif()->peran(Peran::Peserta)->create();

    masukDi('peserta')
        ->fillForm(['username' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
});

it('role internal dapat masuk panel admin dengan username', function (Peran $peran) {
    $user = User::factory()->internal()->peran($peran)->create();

    masukDi('admin')
        ->fillForm(['username' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
})->with([Peran::Superadmin, Peran::Admin, Peran::Keuangan]);

it('username internal tidak peka huruf besar dan spasi di tepi', function () {
    $user = User::factory()->internal()->peran(Peran::Admin)->create(['username' => 'keuangan']);

    masukDi('admin')
        ->fillForm(['username' => '  Keuangan ', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('peserta tidak dapat masuk panel admin', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    masukDi('admin')
        ->fillForm(['username' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
});

it('role internal tidak dapat masuk panel peserta', function () {
    $user = User::factory()->internal()->peran(Peran::Admin)->create();

    masukDi('peserta')
        ->fillForm(['username' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
});

it('akun yang dinonaktifkan saat sedang login tidak dapat membuka panel', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    $this->actingAs($user)->get('/peserta')->assertOk();

    $user->update(['is_aktif' => false]);

    $this->actingAs($user)->get('/peserta')->assertForbidden();
});

it('mengunci login setelah 5 percobaan gagal untuk username yang sama', function () {
    $user = User::factory()->peran(Peran::Peserta)->create();

    $halaman = masukDi('peserta');

    foreach (range(1, Login::MAKS_PERCOBAAN_PER_USERNAME) as $i) {
        $halaman->fillForm(['username' => $user->nik, 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
    }

    $halaman->fillForm(['username' => $user->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('penguncian satu username tidak memblokir username lain dari IP yang sama', function () {
    $dikunci = User::factory()->peran(Peran::Peserta)->create();
    $lain = User::factory()->peran(Peran::Peserta)->create();

    $halaman = masukDi('peserta');

    foreach (range(1, Login::MAKS_PERCOBAAN_PER_USERNAME) as $i) {
        $halaman->fillForm(['username' => $dikunci->nik, 'password' => 'salah'])->call('authenticate');
    }

    masukDi('peserta')
        ->fillForm(['username' => $lain->nik, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($lain);
});
