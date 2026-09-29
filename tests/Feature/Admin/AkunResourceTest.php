<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Akun\Pages\ManageAkun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->superadmin = User::factory()->peran(Peran::Superadmin)->create();
    $this->actingAs($this->superadmin);
});

it('hanya superadmin yang dapat mengelola akun', function () {
    $this->get('/admin/akun')->assertOk();
    $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get('/admin/akun')->assertForbidden();
});

it('membuat akun admin baru', function () {
    Livewire::test(ManageAkun::class)
        ->callAction('create', data: [
            'username' => 'panitia',
            'email' => '',
            'peran' => Peran::Admin->value,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])
        ->assertHasNoActionErrors();

    expect(User::where('username', 'panitia')->sole()->hasRole(Peran::Admin))->toBeTrue();
});

it('memvalidasi isian akun baru di form', function () {
    Livewire::test(ManageAkun::class)
        ->callAction('create', data: [
            'username' => '3507010101900001',
            'peran' => Peran::Admin->value,
            'password' => 'rahasia123',
            'password_confirmation' => 'berbeda123',
        ])
        ->assertHasActionErrors(['username' => 'regex', 'password' => 'confirmed']);

    expect(User::count())->toBe(1);
});

it('mengubah peran dan mengatur ulang kata sandi akun lain', function () {
    $admin = User::factory()->peran(Peran::Admin)->create();

    Livewire::test(ManageAkun::class)
        ->callAction(TestAction::make('edit')->table($admin), data: ['peran' => Peran::Superadmin->value])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('aturUlangKataSandi')->table($admin), data: ['password' => 'kataSandiBaru1', 'password_confirmation' => 'kataSandiBaru1'])
        ->assertNotified('Kata sandi diperbarui');

    expect($admin->fresh()->hasRole(Peran::Superadmin))->toBeTrue()
        ->and(Hash::check('kataSandiBaru1', $admin->fresh()->password))->toBeTrue();
});

it('memberi tahu jika superadmin mencoba menurunkan perannya sendiri', function () {
    User::factory()->peran(Peran::Superadmin)->create();

    Livewire::test(ManageAkun::class)
        ->callAction(TestAction::make('edit')->table($this->superadmin), data: ['peran' => Peran::Admin->value])
        ->assertNotified('Perubahan akun ditolak');

    expect($this->superadmin->fresh()->hasRole(Peran::Superadmin))->toBeTrue();
});

it('menonaktifkan dan mengaktifkan akun lain tanpa aksi hapus', function () {
    $admin = User::factory()->peran(Peran::Admin)->create();

    Livewire::test(ManageAkun::class)
        ->assertActionHidden(TestAction::make('nonaktifkan')->table($this->superadmin))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($admin))
        ->callAction(TestAction::make('nonaktifkan')->table($admin))
        ->assertNotified('Akun dinonaktifkan');

    expect($admin->fresh()->is_aktif)->toBeFalse();

    Livewire::test(ManageAkun::class)
        ->callAction(TestAction::make('aktifkan')->table($admin));

    expect($admin->fresh()->is_aktif)->toBeTrue();
});
