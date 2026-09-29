<?php

use App\Enums\Peran;
use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ListPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ViewPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Models\Desa;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->peran(Peran::Admin)->create();
    $this->actingAs($this->admin);
});

it('dapat dibuka superadmin dan admin, tetapi tidak oleh pengunjung', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();

    $this->get('/admin/pendaftaran')->assertOk();
    $this->get("/admin/pendaftaran/{$pendaftaran->id}")->assertOk();
    $this->actingAs(User::factory()->peran(Peran::Superadmin)->create())->get('/admin/pendaftaran')->assertOk();

    auth()->logout();
    $this->get('/admin/pendaftaran')->assertRedirect('/admin/login');
});

it('menampilkan pendaftar dengan NIK tersamar dan tanpa tombol tambah atau hapus', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $nik = $pendaftaran->peserta->nik;

    Livewire::test(ListPendaftaran::class)
        ->assertCanSeeTableRecords([$pendaftaran])
        ->assertSee(substr($nik, 0, 4).'********'.substr($nik, -4))
        ->assertDontSee($nik)
        ->assertActionDoesNotExist('create')
        ->assertActionDoesNotExist(TestAction::make('delete')->table($pendaftaran));
});

it('menampilkan jumlah pendaftar yang menunggu verifikasi di menu', function () {
    PelatihanPeserta::factory()->count(3)->create();
    PelatihanPeserta::factory()->terverifikasi()->create();

    expect(PendaftaranResource::getNavigationBadge())->toBe('3');
});

it('menyaring pendaftar per pelatihan, status, dan wilayah', function () {
    $pelatihan = Pelatihan::factory()->dibuka()->create();
    $diPelatihan = PelatihanPeserta::factory()->for($pelatihan)->create();
    $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->create();
    $desa = Desa::factory()->create();
    $diDesa = PelatihanPeserta::factory()->for(Peserta::factory()->for($desa))->create();

    Livewire::test(ListPendaftaran::class)
        ->filterTable('pelatihan_id', $pelatihan->id)
        ->assertCanSeeTableRecords([$diPelatihan])
        ->assertCanNotSeeTableRecords([$terverifikasi, $diDesa]);

    Livewire::test(ListPendaftaran::class)
        ->filterTable('status', StatusPendaftaran::Terverifikasi->value)
        ->assertCanSeeTableRecords([$terverifikasi])
        ->assertCanNotSeeTableRecords([$diPelatihan, $diDesa]);

    Livewire::test(ListPendaftaran::class)
        ->filterTable('wilayah', ['provinsi_id' => $desa->kecamatan->kabKota->provinsi_id])
        ->assertCanSeeTableRecords([$diDesa])
        ->assertCanNotSeeTableRecords([$diPelatihan, $terverifikasi]);

    Livewire::test(ListPendaftaran::class)
        ->filterTable('wilayah', ['kab_kota_id' => $desa->kecamatan->kab_kota_id])
        ->assertCanSeeTableRecords([$diDesa])
        ->assertCanNotSeeTableRecords([$diPelatihan]);
});

it('mencari pendaftar berdasarkan nama atau NIK', function () {
    $siti = PelatihanPeserta::factory()->for(Peserta::factory()->state(['nama_lengkap' => 'Siti Aminah', 'nik' => '3507010101900001']))->create();
    $budi = PelatihanPeserta::factory()->for(Peserta::factory()->state(['nama_lengkap' => 'Budi Santoso']))->create();

    Livewire::test(ListPendaftaran::class)
        ->searchTable('siti')
        ->assertCanSeeTableRecords([$siti])
        ->assertCanNotSeeTableRecords([$budi]);

    Livewire::test(ListPendaftaran::class)
        ->searchTable('3507010101900001')
        ->assertCanSeeTableRecords([$siti])
        ->assertCanNotSeeTableRecords([$budi]);
});

it('memverifikasi dari tabel sekaligus merapikan salah ketik', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();

    Livewire::test(ListPendaftaran::class)
        ->callAction(TestAction::make('verifikasi')->table($pendaftaran), data: [
            'nama_lengkap' => 'Nama Sesuai KTP',
            'catatan_panitia' => 'Nama dirapikan sesuai KTP',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Pendaftaran terverifikasi');

    $pendaftaran->refresh();

    expect($pendaftaran->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($pendaftaran->peserta->nama_lengkap)->toBe('Nama Sesuai KTP')
        ->and($pendaftaran->catatan_panitia)->toBe('Nama dirapikan sesuai KTP')
        ->and($pendaftaran->diverifikasiOleh->is($this->admin))->toBeTrue()
        ->and($pendaftaran->desa_id_saat_pelatihan)->toBe($pendaftaran->peserta->desa_id);
});

it('membiarkan modal terbuka dan memberi tahu jika koreksi ditolak service', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $lain = Peserta::factory()->create();

    Livewire::test(ListPendaftaran::class)
        ->callAction(TestAction::make('verifikasi')->table($pendaftaran), data: ['nik' => $lain->nik])
        ->assertNotified('Pendaftaran belum dapat diverifikasi')
        ->assertActionMounted(TestAction::make('verifikasi')->table($pendaftaran));

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terdaftar);
});

it('menyembunyikan tombol verifikasi untuk pendaftaran yang sudah diproses', function () {
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->create();

    Livewire::test(ListPendaftaran::class)
        ->assertActionHidden(TestAction::make('verifikasi')->table($pendaftaran));
});

it('memverifikasi massal dan melewati isian yang berbeda', function () {
    $siap = PelatihanPeserta::factory()->count(2)->create();
    $berbeda = PelatihanPeserta::factory()->create();
    $berbeda->update(['data_isian' => [...$berbeda->data_isian, 'nama_lengkap' => 'Nama Lain']]);

    Livewire::test(ListPendaftaran::class)
        ->selectTableRecords([...$siap, $berbeda])
        ->callAction(TestAction::make('verifikasiMassal')->table()->bulk())
        ->assertNotified('2 pendaftaran terverifikasi');

    expect($berbeda->fresh()->status)->toBe(StatusPendaftaran::Terdaftar)
        ->and($siap->first()->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);
});

it('menampilkan detail, tautan berkas, dan isian yang berbeda', function () {
    $lama = PelatihanPeserta::factory()->selesai()->create();
    $baru = PelatihanPeserta::factory()->for($lama->peserta)->create();
    $baru->update(['data_isian' => [...$baru->data_isian, 'alamat_domisili' => 'Alamat Pindahan Baru']]);

    Livewire::test(ViewPendaftaran::class, ['record' => $baru->getRouteKey()])
        ->assertSee('Isian berbeda dari data lama')
        ->assertSee('Alamat Pindahan Baru')
        ->assertSee($lama->peserta->alamat_domisili)
        ->assertSee(route('berkas.pendaftaran', [$baru, 'ktp']))
        ->assertActionVisible('verifikasi');

    Livewire::test(ViewPendaftaran::class, ['record' => $lama->getRouteKey()])
        ->assertDontSee('Isian berbeda dari data lama')
        ->assertSee('Snapshot saat pelatihan')
        ->assertActionHidden('verifikasi');
});

it('membatalkan peserta dengan alasan wajib', function () {
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->create();

    Livewire::test(ListPendaftaran::class)
        ->callAction(TestAction::make('batalkan')->table($pendaftaran), data: ['alasan_batal' => ''])
        ->assertHasFormErrors(['alasan_batal' => 'required']);

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);

    Livewire::test(ListPendaftaran::class)
        ->callAction(TestAction::make('batalkan')->table($pendaftaran), data: ['alasan_batal' => 'Sakit'])
        ->assertNotified('Peserta dibatalkan');

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Batal)
        ->and($pendaftaran->fresh()->alasan_batal)->toBe('Sakit');

    Livewire::test(ListPendaftaran::class)
        ->filterTable('status', StatusPendaftaran::Batal->value)
        ->assertActionHidden(TestAction::make('batalkan')->table($pendaftaran));
});

it('menetapkan pengganti dari panel verifikasi', function () {
    Carbon::setTestNow('2026-10-01 09:00');
    $pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
    $batal = PelatihanPeserta::factory()->batal()->for($pelatihan)->create();
    $calon = PelatihanPeserta::factory()->for($pelatihan)
        ->for(Peserta::factory()->state(['desa_id' => $batal->peserta->desa_id]))
        ->create();

    Livewire::test(ListPendaftaran::class)
        ->mountAction(TestAction::make('verifikasi')->table($calon))
        ->assertFormFieldExists('menggantikan_id')
        ->fillForm(['menggantikan_id' => $batal->id])
        ->callMountedAction()
        ->assertNotified('Pendaftaran terverifikasi');

    expect($calon->fresh()->menggantikan_id)->toBe($batal->id);

    Livewire::test(ViewPendaftaran::class, ['record' => $batal->getRouteKey()])
        ->assertSee('Digantikan oleh')
        ->assertSee($calon->peserta->nama_lengkap);
});

it('tidak menawarkan pengganti jika tidak ada peserta batal dari desa yang sama', function () {
    Carbon::setTestNow('2026-10-01 09:00');
    $pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
    PelatihanPeserta::factory()->batal()->for($pelatihan)->create();
    $calon = PelatihanPeserta::factory()->for($pelatihan)->create();

    Livewire::test(ListPendaftaran::class)
        ->mountAction(TestAction::make('verifikasi')->table($calon))
        ->assertFormFieldHidden('menggantikan_id');
});
