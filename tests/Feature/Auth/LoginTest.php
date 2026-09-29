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

function masukAdmin(): Testable
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return Livewire::test(Login::class);
}

it('menampilkan halaman login admin dengan isian username', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Username');
});

it('tidak menyediakan panel peserta maupun registrasi akun', function (string $url) {
    $this->get($url)->assertNotFound();
})->with(['/peserta', '/peserta/login', '/peserta/register', '/admin/register']);

it('role internal dapat masuk panel admin dengan username', function (Peran $peran) {
    $user = User::factory()->peran($peran)->create();

    masukAdmin()
        ->fillForm(['username' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
})->with(Peran::cases());

it('username tidak peka huruf besar dan spasi di tepi', function () {
    $user = User::factory()->peran(Peran::Admin)->create(['username' => 'panitia']);

    masukAdmin()
        ->fillForm(['username' => '  Panitia ', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('menolak kata sandi yang salah', function () {
    $user = User::factory()->peran(Peran::Admin)->create();

    masukAdmin()
        ->fillForm(['username' => $user->username, 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['username' => ['Username atau kata sandi salah.']]);

    $this->assertGuest();
});

it('menolak username yang tidak terdaftar, termasuk NIK', function (string $username) {
    masukAdmin()
        ->fillForm(['username' => $username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
})->with(['NIK' => '3507010101909999', 'username' => 'tidakada']);

it('menolak akun yang dinonaktifkan', function () {
    $user = User::factory()->nonaktif()->peran(Peran::Admin)->create();

    masukAdmin()
        ->fillForm(['username' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
});

it('menolak akun tanpa role internal', function () {
    $user = User::factory()->create();

    masukAdmin()
        ->fillForm(['username' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);

    $this->assertGuest();
});

it('akun yang dinonaktifkan saat sedang login tidak dapat membuka panel', function () {
    $user = User::factory()->peran(Peran::Admin)->create();

    $this->actingAs($user)->get('/admin')->assertOk();

    $user->update(['is_aktif' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('mengunci login setelah 5 percobaan gagal untuk username yang sama', function () {
    $user = User::factory()->peran(Peran::Admin)->create();

    $halaman = masukAdmin();

    foreach (range(1, Login::MAKS_PERCOBAAN_PER_USERNAME) as $i) {
        $halaman->fillForm(['username' => $user->username, 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
    }

    $halaman->fillForm(['username' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('penguncian satu username tidak memblokir username lain dari IP yang sama', function () {
    $dikunci = User::factory()->peran(Peran::Admin)->create();
    $lain = User::factory()->peran(Peran::Admin)->create();

    $halaman = masukAdmin();

    foreach (range(1, Login::MAKS_PERCOBAAN_PER_USERNAME) as $i) {
        $halaman->fillForm(['username' => $dikunci->username, 'password' => 'salah'])->call('authenticate');
    }

    masukAdmin()
        ->fillForm(['username' => $lain->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($lain);
});
