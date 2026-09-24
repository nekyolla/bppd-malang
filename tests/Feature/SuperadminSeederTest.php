<?php

use App\Enums\Peran;
use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperadminSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('membuat superadmin yang dapat masuk dengan username', function () {
    config(['bbpd.superadmin' => ['username' => 'superadmin', 'email' => null, 'password' => 'rahasia-123']]);

    $this->seed(SuperadminSeeder::class);

    $user = User::where('username', 'superadmin')->firstOrFail();

    expect($user->nik)->toBeNull()
        ->and($user->hasRole(Peran::Superadmin))->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm(['username' => 'superadmin', 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('tidak membuat superadmin jika username tidak valid', function (string $username) {
    config(['bbpd.superadmin' => ['username' => $username, 'email' => null, 'password' => 'rahasia-123']]);

    $this->seed(SuperadminSeeder::class);

    expect(User::count())->toBe(0);
})->with([
    'hanya angka' => '3507000000000001',
    'huruf besar' => 'Superadmin',
    'terlalu pendek' => 'ab',
    'berisi spasi' => 'super admin',
]);
