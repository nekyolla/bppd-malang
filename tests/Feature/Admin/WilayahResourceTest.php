<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Wilayah\Pages\ListWilayah;
use App\Models\Desa;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->superadmin = User::factory()->peran(Peran::Superadmin)->create();
});

it('hanya superadmin yang dapat membuka data wilayah', function () {
    $this->actingAs($this->superadmin)->get('/admin/wilayah')->assertOk();
    $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get('/admin/wilayah')->assertForbidden();
});

it('menampilkan desa beserta kecamatan, kabupaten/kota, dan provinsinya', function () {
    $desa = Desa::factory()->create(['nama' => 'Sumbersuko']);
    $kecamatan = $desa->kecamatan;

    $this->actingAs($this->superadmin);

    Livewire::test(ListWilayah::class)
        ->assertCanSeeTableRecords([$desa])
        ->assertTableColumnStateSet('kecamatan.nama', $kecamatan->nama, $desa)
        ->assertTableColumnStateSet('kecamatan.kabKota.nama', $kecamatan->kabKota->nama, $desa)
        ->assertTableColumnStateSet('kecamatan.kabKota.provinsi.nama', $kecamatan->kabKota->provinsi->nama, $desa);
});

it('mencari desa menurut nama atau kode', function () {
    $dicari = Desa::factory()->create(['nama' => 'Sumbersuko', 'kode' => '35.07.01.2001']);
    $lain = Desa::factory()->create(['nama' => 'Wonorejo', 'kode' => '35.07.02.2002']);

    $this->actingAs($this->superadmin);

    Livewire::test(ListWilayah::class)
        ->searchTable('sumbersuko')
        ->assertCanSeeTableRecords([$dicari])
        ->assertCanNotSeeTableRecords([$lain])
        ->searchTable('35.07.02')
        ->assertCanSeeTableRecords([$lain])
        ->assertCanNotSeeTableRecords([$dicari]);
});

it('menyaring desa menurut provinsi dan kabupaten/kota', function () {
    $dicari = Desa::factory()->create();
    $lain = Desa::factory()->create();
    $kabKota = $dicari->kecamatan->kabKota;

    $this->actingAs($this->superadmin);

    Livewire::test(ListWilayah::class)
        ->filterTable('wilayah', ['provinsi_id' => $kabKota->provinsi_id])
        ->assertCanSeeTableRecords([$dicari])
        ->assertCanNotSeeTableRecords([$lain])
        ->filterTable('wilayah', ['provinsi_id' => $kabKota->provinsi_id, 'kab_kota_id' => $kabKota->id])
        ->assertCanSeeTableRecords([$dicari])
        ->assertCanNotSeeTableRecords([$lain]);
});

it('hanya-baca: tanpa tambah, ubah, maupun hapus', function () {
    $desa = Desa::factory()->create();

    $this->actingAs($this->superadmin);

    Livewire::test(ListWilayah::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');

    expect($this->superadmin->can('update', $desa))->toBeFalse()
        ->and($this->superadmin->can('delete', $desa))->toBeFalse();
});
