<?php

use App\Enums\Peran;
use App\Filament\Auth\Register;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('peserta'));
});

function isiRegistrasi(array $data = []): array
{
    return array_merge([
        'nik' => '3507010101900001',
        'email' => null,
        'password' => 'rahasia-123',
        'passwordConfirmation' => 'rahasia-123',
    ], $data);
}

it('menampilkan halaman registrasi peserta', function () {
    $this->get('/peserta/register')
        ->assertOk()
        ->assertSee('NIK');
});

it('tidak menyediakan registrasi di panel admin', function () {
    $this->get('/admin/register')->assertNotFound();
});

it('mendaftarkan peserta dengan NIK tanpa email', function () {
    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi())
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('nik', '3507010101900001')->firstOrFail();

    expect($user->email)->toBeNull()
        ->and($user->username)->toBeNull()
        ->and($user->is_aktif)->toBeTrue()
        ->and($user->hasRole(Peran::Peserta))->toBeTrue()
        ->and($user->hasRole(Peran::Admin))->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

it('mengizinkan banyak peserta tanpa email', function () {
    User::factory()->create(['nik' => '3507010101900002', 'email' => null]);

    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi(['email' => '']))
        ->call('register')
        ->assertHasNoFormErrors();

    expect(User::whereNull('email')->count())->toBe(2);
});

it('menyimpan email jika diisi', function () {
    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi(['email' => 'budi@example.com']))
        ->call('register')
        ->assertHasNoFormErrors();

    expect(User::where('nik', '3507010101900001')->value('email'))->toBe('budi@example.com');
});

it('menolak NIK yang bukan 16 digit angka', function (string $nik) {
    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi(['nik' => $nik]))
        ->call('register')
        ->assertHasFormErrors(['nik']);

    expect(User::count())->toBe(0);
})->with([
    '15 digit' => '350701010190000',
    '17 digit' => '35070101019000011',
    'berisi huruf' => '350701010190000A',
    'berisi spasi' => '3507 01010190001',
]);

it('menolak NIK yang sudah terdaftar', function () {
    User::factory()->create(['nik' => '3507010101900001']);

    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi())
        ->call('register')
        ->assertHasFormErrors(['nik' => 'unique']);
});

it('menolak email yang sudah dipakai', function () {
    User::factory()->create(['email' => 'budi@example.com']);

    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi(['email' => 'budi@example.com']))
        ->call('register')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('menolak konfirmasi kata sandi yang berbeda', function () {
    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi(['passwordConfirmation' => 'lain-lagi']))
        ->call('register')
        ->assertHasFormErrors(['password']);

    expect(User::count())->toBe(0);
});

it('menampilkan pesan galat dalam bahasa Indonesia', function () {
    Livewire::test(Register::class)
        ->fillForm(isiRegistrasi(['nik' => '']))
        ->call('register')
        ->assertHasFormErrors(['nik' => ['NIK wajib diisi.']]);
});
