<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageSertifikat;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ViewPendaftaran;
use App\Filament\Admin\Resources\Peserta\Pages\ViewPeserta;
use App\Filament\Admin\Resources\Peserta\RelationManagers\RiwayatPelatihanRelationManager;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SertifikatPeserta;
use App\Models\User;
use App\Services\SertifikatService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->peran(Peran::Admin)->create();
    $this->actingAs($this->admin);
    $this->pelatihan = Pelatihan::factory()->berjalan()->tanggal('2026-10-05', '2026-10-08')->create();
    $this->sudah = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Ani']))->create();
    $this->belum = PelatihanPeserta::factory()->selesai()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Budi']))->create();
    app(SertifikatService::class)->unggah($this->sudah, UploadedFile::fake()->createWithContent('s.pdf', '%PDF-ani'), $this->admin);
    $this->halaman = fn () => Livewire::test(ManageSertifikat::class, ['record' => $this->pelatihan->getRouteKey()]);
});

it('menampilkan peserta terverifikasi/selesai dengan status sertifikat', function () {
    $menunggu = PelatihanPeserta::factory()->for($this->pelatihan)->create();
    $batal = PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create();

    ($this->halaman)()
        ->assertCanSeeTableRecords([$this->sudah, $this->belum])
        ->assertCanNotSeeTableRecords([$menunggu, $batal])
        ->assertTableColumnStateSet('sertifikat', 'Sudah diunggah', $this->sudah)
        ->assertTableColumnStateSet('sertifikat', 'Belum', $this->belum)
        ->assertSee('1 dari 2 peserta sudah bersertifikat');
});

it('mengunggah dan mengganti sertifikat dari aksi baris', function () {
    ($this->halaman)()
        ->assertActionVisible(TestAction::make('unggah')->table($this->belum))
        ->assertActionHidden(TestAction::make('ganti')->table($this->belum))
        ->callAction(TestAction::make('unggah')->table($this->belum), ['berkas' => UploadedFile::fake()->createWithContent('b.pdf', '%PDF-budi')])
        ->assertHasNoActionErrors();

    expect(Storage::disk('local')->get($this->belum->sertifikat->file_sertifikat))->toBe('%PDF-budi');

    ($this->halaman)()
        ->assertActionHidden(TestAction::make('unggah')->table($this->sudah))
        ->callAction(TestAction::make('ganti')->table($this->sudah), ['berkas' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-ani-baru')])
        ->assertHasNoActionErrors();

    expect(Storage::disk('local')->get($this->sudah->sertifikat->file_sertifikat))->toBe('%PDF-ani-baru')
        ->and(SertifikatPeserta::count())->toBe(2);
});

it('memeriksa izin ubah sertifikat untuk Ganti dan izin buat untuk Unggah', function () {
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'update' ? false : null);

    ($this->halaman)()
        ->assertActionHidden(TestAction::make('ganti')->table($this->sudah))
        ->assertActionVisible(TestAction::make('unggah')->table($this->belum));
});

it('menolak berkas bukan PDF', function () {
    ($this->halaman)()
        ->callAction(TestAction::make('unggah')->table($this->belum), ['berkas' => UploadedFile::fake()->image('b.jpg')])
        ->assertHasActionErrors(['berkas']);

    expect($this->belum->sertifikat()->exists())->toBeFalse();
});

it('membuka dan menghapus sertifikat', function () {
    ($this->halaman)()
        ->assertActionHasUrl(TestAction::make('lihat')->table($this->sudah), route('berkas.sertifikat', $this->sudah))
        ->assertActionHidden(TestAction::make('lihat')->table($this->belum))
        ->callAction(TestAction::make('hapus')->table($this->sudah));

    expect($this->sudah->sertifikat()->exists())->toBeFalse();
});

it('memfilter peserta yang sudah atau belum bersertifikat', function () {
    ($this->halaman)()
        ->filterTable('sudah', true)
        ->assertCanSeeTableRecords([$this->sudah])
        ->assertCanNotSeeTableRecords([$this->belum])
        ->filterTable('sudah', false)
        ->assertCanSeeTableRecords([$this->belum])
        ->assertCanNotSeeTableRecords([$this->sudah]);
});

it('menampilkan sub-menu Sertifikat di halaman pelatihan', function () {
    $this->get(ManageSertifikat::getUrl(['record' => $this->pelatihan]))->assertOk();
    $this->get(ManageKelas::getUrl(['record' => $this->pelatihan]))
        ->assertSee(ManageSertifikat::getUrl(['record' => $this->pelatihan]), escape: false);
});

it('menampilkan tautan sertifikat di detail pendaftaran dan riwayat peserta', function () {
    $url = route('berkas.sertifikat', $this->sudah);

    Livewire::test(ViewPendaftaran::class, ['record' => $this->sudah->getRouteKey()])->assertSee($url, escape: false);
    Livewire::test(RiwayatPelatihanRelationManager::class, ['ownerRecord' => $this->sudah->peserta, 'pageClass' => ViewPeserta::class])
        ->assertSee($url, escape: false);
    Livewire::test(ViewPendaftaran::class, ['record' => $this->belum->getRouteKey()])->assertSee('Belum diunggah');
});
