<?php

use App\Enums\Peran;
use App\Models\User;
use App\Services\AkunService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->service = app(AkunService::class);
    $this->superadmin = User::factory()->peran(Peran::Superadmin)->create();
});

function galatAkun(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException tidak dilempar.');
}

it('membuat akun admin dengan username dirapikan dan kata sandi di-hash', function () {
    $user = $this->service->buat(['username' => '  Panitia.Satu ', 'email' => ' Panitia@BBPD.go.id ', 'peran' => 'admin', 'password' => 'rahasia123']);

    expect($user->username)->toBe('panitia.satu')
        ->and($user->email)->toBe('panitia@bbpd.go.id')
        ->and($user->is_aktif)->toBeTrue()
        ->and($user->hasRole(Peran::Admin))->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('menolak username, email, atau kata sandi yang tidak sah', function (array $ubah, string $kolom) {
    User::factory()->create(['username' => 'dipakai', 'email' => 'dipakai@bbpd.go.id']);

    expect(galatAkun(fn () => $this->service->buat([
        'username' => 'panitia', 'email' => null, 'peran' => Peran::Admin, 'password' => 'rahasia123', ...$ubah,
    ])))->toHaveKey($kolom);
})->with([
    'username berupa NIK' => [['username' => '3507010101900001'], 'username'],
    'username terlalu pendek' => [['username' => 'ab'], 'username'],
    'username berspasi' => [['username' => 'pan itia'], 'username'],
    'username dipakai' => [['username' => 'Dipakai'], 'username'],
    'email dipakai' => [['email' => 'DIPAKAI@bbpd.go.id'], 'email'],
    'kata sandi pendek' => [['password' => 'pendek'], 'password'],
    'peran tidak dikenal' => [['peran' => 'keuangan'], 'peran'],
]);

it('mengubah username, email, dan peran', function () {
    $admin = User::factory()->peran(Peran::Admin)->create();

    $this->service->ubah($admin, $this->superadmin, ['username' => 'kepala.seksi', 'email' => null, 'peran' => 'superadmin']);

    expect($admin->fresh()->username)->toBe('kepala.seksi')
        ->and($admin->fresh()->hasRole(Peran::Superadmin))->toBeTrue()
        ->and($admin->fresh()->hasRole(Peran::Admin))->toBeFalse();
});

it('mencegah superadmin menurunkan peran dirinya sendiri', function () {
    User::factory()->peran(Peran::Superadmin)->create();

    expect(galatAkun(fn () => $this->service->ubah($this->superadmin, $this->superadmin, ['username' => $this->superadmin->username, 'peran' => 'admin'])))
        ->toHaveKey('akun')
        ->and($this->superadmin->fresh()->hasRole(Peran::Superadmin))->toBeTrue();
});

it('menjaga minimal satu superadmin aktif', function () {
    $kedua = User::factory()->peran(Peran::Superadmin)->create();

    $this->service->nonaktifkan($kedua, $this->superadmin);

    $ketiga = User::factory()->peran(Peran::Superadmin)->create();
    $this->service->nonaktifkan($ketiga, $this->superadmin);

    $lain = User::factory()->peran(Peran::Admin)->create();

    expect(galatAkun(fn () => $this->service->ubah($this->superadmin, $lain, ['username' => $this->superadmin->username, 'peran' => 'admin'])))
        ->toHaveKey('akun')
        ->and(galatAkun(fn () => $this->service->nonaktifkan($this->superadmin, $lain)))->toHaveKey('akun');
});

it('tidak dapat menonaktifkan akun sendiri', function () {
    expect(galatAkun(fn () => $this->service->nonaktifkan($this->superadmin, $this->superadmin)))->toHaveKey('akun')
        ->and($this->superadmin->fresh()->is_aktif)->toBeTrue();
});

it('menonaktifkan, mengaktifkan, dan mengatur ulang kata sandi', function () {
    $admin = User::factory()->peran(Peran::Admin)->create();

    $this->service->nonaktifkan($admin, $this->superadmin);
    expect($admin->fresh()->is_aktif)->toBeFalse();

    $this->service->aktifkan($admin);
    $this->service->aturUlangKataSandi($admin, 'kataSandiBaru1');

    expect($admin->fresh()->is_aktif)->toBeTrue()
        ->and(Hash::check('kataSandiBaru1', $admin->fresh()->password))->toBeTrue()
        ->and(galatAkun(fn () => $this->service->aturUlangKataSandi($admin, '123')))->toHaveKey('password');
});
