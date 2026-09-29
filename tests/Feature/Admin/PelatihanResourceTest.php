<?php

use App\Enums\Peran;
use App\Enums\StatusPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\CreatePelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\EditPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ListPelatihan;
use App\Models\JudulPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->peran(Peran::Admin)->create();
    $this->actingAs($this->admin);
});

it('dapat dibuka superadmin dan admin, tetapi tidak oleh pengunjung', function () {
    $this->get('/admin/pelatihan')->assertOk();
    $this->actingAs(User::factory()->peran(Peran::Superadmin)->create())->get('/admin/pelatihan')->assertOk();

    auth()->logout();
    $this->get('/admin/pelatihan')->assertRedirect('/admin/login');
});

it('membuat pelatihan lewat form tanpa isian status', function () {
    $judul = JudulPelatihan::factory()->create();

    Livewire::test(CreatePelatihan::class)
        ->assertFormFieldDoesNotExist('status')
        ->fillForm([
            'judul_pelatihan_id' => $judul->id,
            'batch_ke' => 2,
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-05',
            'tipe_lokasi' => 'bbpd',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/pelatihan');

    $pelatihan = Pelatihan::sole();

    expect($pelatihan->tahun_anggaran)->toBe(2026)
        ->and($pelatihan->batch_ke)->toBe(2)
        ->and($pelatihan->status)->toBe(StatusPelatihan::Draft);
});

it('menampilkan galat service di bawah isian yang sesuai', function () {
    $pelatihan = Pelatihan::factory()->tanggal('2026-11-02', '2026-11-05')->create();

    Livewire::test(CreatePelatihan::class)
        ->fillForm([
            'judul_pelatihan_id' => $pelatihan->judul_pelatihan_id,
            'batch_ke' => 1,
            'tanggal_mulai' => '2026-11-09',
            'tanggal_selesai' => '2026-11-10',
            'tipe_lokasi' => 'bbpd',
        ])
        ->call('create')
        ->assertHasFormErrors(['batch_ke']);

    expect(Pelatihan::count())->toBe(1);
});

it('mewajibkan keterangan lokasi untuk pelatihan di luar BBPD', function () {
    Livewire::test(CreatePelatihan::class)
        ->fillForm([
            'judul_pelatihan_id' => JudulPelatihan::factory()->create()->id,
            'batch_ke' => 1,
            'tanggal_mulai' => '2026-11-02',
            'tanggal_selesai' => '2026-11-05',
            'tipe_lokasi' => 'luar',
        ])
        ->assertFormFieldVisible('keterangan_lokasi')
        ->call('create')
        ->assertHasFormErrors(['keterangan_lokasi' => 'required']);
});

it('menampilkan jumlah peserta aktif dan batal', function () {
    $pelatihan = Pelatihan::factory()->dibuka()->create();
    PelatihanPeserta::factory()->count(2)->for($pelatihan)->create();
    PelatihanPeserta::factory()->batal()->for($pelatihan)->create();

    Livewire::test(ListPelatihan::class)
        ->assertCanSeeTableRecords([$pelatihan])
        ->assertSee('2 aktif · 1 batal')
        ->assertSee('2 menunggu verifikasi');
});

it('membuka pendaftaran lewat tombol status', function () {
    $pelatihan = Pelatihan::factory()->create();

    Livewire::test(ListPelatihan::class)
        ->assertActionHasLabel(TestAction::make('ubahStatus')->table($pelatihan), 'Buka pendaftaran')
        ->callAction(TestAction::make('ubahStatus')->table($pelatihan))
        ->assertNotified('Status pelatihan: Pendaftaran dibuka');

    expect($pelatihan->fresh()->status)->toBe(StatusPelatihan::Dibuka);
});

it('memberi tahu admin jika pelatihan belum bisa diselesaikan', function () {
    $pelatihan = Pelatihan::factory()->berjalan()->create();
    PelatihanPeserta::factory()->for($pelatihan)->create();

    Livewire::test(ListPelatihan::class)
        ->callAction(TestAction::make('ubahStatus')->table($pelatihan))
        ->assertNotified('Status tidak dapat diubah');

    expect($pelatihan->fresh()->status)->toBe(StatusPelatihan::Berjalan);
});

it('menyembunyikan tombol status untuk pelatihan yang sudah selesai', function () {
    $pelatihan = Pelatihan::factory()->selesai()->create();

    Livewire::test(ListPelatihan::class)
        ->assertActionHidden(TestAction::make('ubahStatus')->table($pelatihan));
});

it('hanya mengizinkan hapus pelatihan yang belum punya pendaftar', function () {
    $kosong = Pelatihan::factory()->create();
    $terisi = PelatihanPeserta::factory()->create()->pelatihan;

    Livewire::test(EditPelatihan::class, ['record' => $terisi->getRouteKey()])
        ->assertActionHidden('delete');

    Livewire::test(EditPelatihan::class, ['record' => $kosong->getRouteKey()])
        ->callAction('delete');

    expect(Pelatihan::find($kosong->id))->toBeNull()
        ->and($terisi->fresh())->not->toBeNull();
});

it('mengubah pelatihan lewat halaman edit', function () {
    $pelatihan = Pelatihan::factory()->tanggal('2026-11-02', '2026-11-05')->create();

    Livewire::test(EditPelatihan::class, ['record' => $pelatihan->getRouteKey()])
        ->fillForm(['tanggal_selesai' => '2026-11-06'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pelatihan->fresh()->jumlah_jp)->toBe(50);
});
