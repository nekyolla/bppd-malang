<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Pelatihan\Pages\EditPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ListPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePembagianKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageSertifikat;
use App\Filament\Admin\Resources\Pelatihan\Pages\PesertaKelas;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * PF-38: menu Pelatihan hanya untuk mengubah data pelatihan; pekerjaan per pelatihan
 * (pendaftar, kelas, pembagian kelas, sertifikat) dibuka dari menu Pendaftaran.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());
    $this->pelatihan = Pelatihan::factory()->dibuka()->create();
});

it('membuka form ubah dari baris tabel Pelatihan', function () {
    $tabel = Livewire::test(ListPelatihan::class)->instance()->getTable();

    expect($tabel->getRecordUrl($this->pelatihan))->toBe(EditPelatihan::getUrl(['record' => $this->pelatihan]));
});

it('menampilkan form ubah pelatihan tanpa sub-menu kerja', function () {
    $this->get(EditPelatihan::getUrl(['record' => $this->pelatihan]))
        ->assertOk()
        ->assertDontSee(ManagePendaftar::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertDontSee(ManageKelas::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertDontSee(ManageSertifikat::getUrl(['record' => $this->pelatihan]), escape: false);
});

it('menampilkan sub-menu pendaftar, kelas, pembagian kelas, dan sertifikat tanpa data pelatihan', function () {
    $this->get(ManagePendaftar::getUrl(['record' => $this->pelatihan]))
        ->assertOk()
        ->assertSee(ManageKelas::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertSee(ManagePembagianKelas::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertSee(ManageSertifikat::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertDontSee('Data pelatihan')
        ->assertDontSee(EditPelatihan::getUrl(['record' => $this->pelatihan]), escape: false);
});

it('memulai breadcrumb sub-halaman dari menu Pendaftaran', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);

    expect(Livewire::test(ManagePendaftar::class, ['record' => $this->pelatihan->getRouteKey()])->instance()->getBreadcrumbs())
        ->toBe([PendaftaranResource::getUrl('index') => 'Pendaftaran', 'Pendaftar'])
        ->and(Livewire::test(PesertaKelas::class, ['record' => $this->pelatihan->getRouteKey(), 'kelas' => $kelas->getKey()])->instance()->getBreadcrumbs())
        ->toBe([PendaftaranResource::getUrl('index') => 'Pendaftaran', 'Kelas A']);
});

it('menandai menu Pendaftaran, bukan Pelatihan, saat membuka sub-halaman kerja', function () {
    $polaPendaftaran = (array) PendaftaranResource::getNavigationItemActiveRoutePattern();
    $polaPelatihan = (array) PelatihanResource::getNavigationItemActiveRoutePattern();

    foreach ([ManagePendaftar::class, ManageKelas::class, PesertaKelas::class, ManagePembagianKelas::class, ManageSertifikat::class] as $halaman) {
        expect($polaPendaftaran)->toContain($halaman::getRouteName())
            ->and($polaPelatihan)->not->toContain($halaman::getRouteName());
    }

    expect($polaPelatihan)->toContain(EditPelatihan::getRouteName(), ListPelatihan::getRouteName());
});
