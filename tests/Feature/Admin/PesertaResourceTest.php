<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Peserta\Pages\EditPeserta;
use App\Filament\Admin\Resources\Peserta\Pages\ListPeserta;
use App\Filament\Admin\Resources\Peserta\Pages\ViewPeserta;
use App\Filament\Admin\Resources\Peserta\RelationManagers\RiwayatPelatihanRelationManager;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());
    $this->peserta = Peserta::factory()->create(['nama_lengkap' => 'Siti Aminah']);
});

it('dapat dibuka akun internal, tetapi tidak oleh pengunjung', function () {
    $this->get('/admin/peserta')->assertOk();
    $this->get("/admin/peserta/{$this->peserta->id}")->assertOk();
    $this->get("/admin/peserta/{$this->peserta->id}/edit")->assertOk();

    auth()->logout();
    $this->get('/admin/peserta')->assertRedirect('/admin/login');
});

it('menampilkan satu baris per peserta dengan NIK tersamar, jumlah pelatihan, dan tanpa tambah/hapus', function () {
    PelatihanPeserta::factory()->for($this->peserta)->count(2)->create();
    $nik = $this->peserta->nik;

    Livewire::test(ListPeserta::class)
        ->assertCanSeeTableRecords([$this->peserta])
        ->assertSee(substr($nik, 0, 4).'********'.substr($nik, -4))
        ->assertDontSee($nik)
        ->assertTableColumnStateSet('pendaftaran_count', 2, $this->peserta)
        ->assertActionDoesNotExist('create')
        ->assertActionDoesNotExist(TestAction::make('delete')->table($this->peserta));
});

it('mencari peserta berdasarkan nama atau NIK dan menyaring per wilayah', function () {
    $lain = Peserta::factory()->create(['nama_lengkap' => 'Budi Santoso']);
    $desa = Desa::factory()->create();
    $diDesa = Peserta::factory()->for($desa)->create();

    Livewire::test(ListPeserta::class)
        ->searchTable('siti')
        ->assertCanSeeTableRecords([$this->peserta])
        ->assertCanNotSeeTableRecords([$lain]);

    Livewire::test(ListPeserta::class)
        ->searchTable($this->peserta->nik)
        ->assertCanSeeTableRecords([$this->peserta])
        ->assertCanNotSeeTableRecords([$lain]);

    Livewire::test(ListPeserta::class)
        ->filterTable('wilayah', ['kab_kota_id' => $desa->kecamatan->kab_kota_id])
        ->assertCanSeeTableRecords([$diDesa])
        ->assertCanNotSeeTableRecords([$this->peserta, $lain]);
});

it('menampilkan detail lengkap dengan NIK penuh, pratinjau berkas, dan tombol kembali', function () {
    $this->peserta->update(['file_ktp' => 'pendaftaran/1/ktp.jpg', 'foto' => 'pendaftaran/1/foto.jpg']);

    Livewire::test(ViewPeserta::class, ['record' => $this->peserta->getRouteKey()])
        ->assertSee($this->peserta->nik)
        ->assertSee(route('berkas.peserta', [$this->peserta, 'ktp']), escape: false)
        ->assertActionExists('kembali');
});

it('berjudul nama peserta dengan jabatan dan desa sebagai subjudul', function () {
    $this->peserta->load('jabatan', 'desa');

    $halaman = Livewire::test(ViewPeserta::class, ['record' => $this->peserta->getRouteKey()])->instance();

    expect($halaman->getTitle())->toBe($this->peserta->nama_lengkap)
        ->and($halaman->getSubheading())->toBe("{$this->peserta->jabatan->nama_jabatan} · Desa {$this->peserta->desa->nama}");
});

it('menampilkan riwayat seluruh pelatihan peserta', function () {
    $pertama = PelatihanPeserta::factory()->selesai()->for($this->peserta)->create();
    $kedua = PelatihanPeserta::factory()->for($this->peserta)->create();
    $orangLain = PelatihanPeserta::factory()->create();

    Livewire::test(RiwayatPelatihanRelationManager::class, ['ownerRecord' => $this->peserta, 'pageClass' => ViewPeserta::class])
        ->assertCanSeeTableRecords([$pertama, $kedua])
        ->assertCanNotSeeTableRecords([$orangLain]);
});

it('merapikan data peserta lewat form ubah', function () {
    Livewire::test(EditPeserta::class, ['record' => $this->peserta->getRouteKey()])
        ->fillForm(['nama_lengkap' => 'Siti Aminah Rahayu'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect("/admin/peserta/{$this->peserta->id}");

    expect($this->peserta->fresh()->nama_lengkap)->toBe('Siti Aminah Rahayu');
});

it('menampilkan galat service di bawah isian NIK', function () {
    $lain = Peserta::factory()->create();

    Livewire::test(EditPeserta::class, ['record' => $this->peserta->getRouteKey()])
        ->fillForm(['nik' => $lain->nik])
        ->call('save')
        ->assertHasFormErrors(['nik']);
});

it('menampilkan periode jabatan hanya untuk jabatan berperiode (PF-25)', function () {
    $kades = Peserta::factory()->for(Jabatan::factory()->berperiode()->state(['nama_jabatan' => 'Kepala Desa']))->create(['periode_jabatan' => 2]);
    PelatihanPeserta::factory()->terverifikasi()->for($kades)->create();

    Livewire::test(ViewPeserta::class, ['record' => $kades->getRouteKey()])
        ->assertSee('Periode ke-2');
    Livewire::test(RiwayatPelatihanRelationManager::class, ['ownerRecord' => $kades, 'pageClass' => ViewPeserta::class])
        ->assertSee('Kepala Desa (periode ke-2)');
    Livewire::test(ViewPeserta::class, ['record' => $this->peserta->getRouteKey()])
        ->assertDontSee('Periode jabatan');
});
