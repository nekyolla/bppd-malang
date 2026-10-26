<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePembagianKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pelatihan\Pages\PesertaKelas;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ViewPendaftaran;
use App\Models\KelasPelatihan;
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
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
    $this->kelas = fn (?Pelatihan $pelatihan = null) => Livewire::test(ManageKelas::class, ['record' => ($pelatihan ?? $this->pelatihan)->getRouteKey()]);
    $this->pembagian = fn (?Pelatihan $pelatihan = null) => Livewire::test(ManagePembagianKelas::class, ['record' => ($pelatihan ?? $this->pelatihan)->getRouteKey()]);
});

it('menampilkan sub-menu pelatihan: data, pendaftar, kelas, pembagian kelas', function () {
    $this->get("/admin/pelatihan/{$this->pelatihan->id}/edit")
        ->assertOk()
        ->assertSee('Data pelatihan')
        ->assertSee(ManageKelas::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertSee(ManagePembagianKelas::getUrl(['record' => $this->pelatihan]), escape: false)
        ->assertSee("/admin/pelatihan/{$this->pelatihan->id}/pendaftar", escape: false);

    $this->get(ManageKelas::getUrl(['record' => $this->pelatihan]))->assertOk();
    $this->get(ManagePembagianKelas::getUrl(['record' => $this->pelatihan]))->assertOk();
});

it('membuat kelas dengan usulan nama berikutnya dan menolak nama yang sama', function () {
    ($this->kelas)()
        ->assertSee('Buat kelas terlebih dahulu sebelum membagi peserta.')
        ->mountAction(TestAction::make('create')->table())
        ->assertSchemaStateSet(['nama_kelas' => 'A']);

    ($this->kelas)()
        ->callAction(TestAction::make('create')->table())
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('create')->table(), data: ['nama_kelas' => 'A'])
        ->assertHasActionErrors(['nama_kelas' => 'unique']);

    KelasPelatihan::factory()->create(['nama_kelas' => 'C']);

    ($this->kelas)()
        ->callAction(TestAction::make('create')->table())
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('create')->table(), data: ['nama_kelas' => 'C'])
        ->assertHasNoActionErrors();

    expect($this->pelatihan->kelas()->orderBy('nama_kelas')->pluck('nama_kelas')->all())->toBe(['A', 'B', 'C']);
});

it('menampilkan jumlah peserta aktif per kelas', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(2)->create(['kelas_pelatihan_id' => $kelas->id]);
    PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $kelas->id]);

    ($this->kelas)()->assertTableColumnStateSet('jumlah_peserta', 2, $kelas);
});

it('mengganti nama dan menghapus kelas kosong', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);

    ($this->kelas)()
        ->callAction(TestAction::make('edit')->table($kelas), data: ['nama_kelas' => 'Pagi'])
        ->assertHasNoActionErrors();

    expect($kelas->fresh()->nama_kelas)->toBe('Pagi');

    ($this->kelas)()->callAction(TestAction::make('delete')->table($kelas));

    expect(KelasPelatihan::find($kelas->id))->toBeNull();
});

it('menolak menghapus kelas yang masih berisi peserta', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $kelas->id]);

    ($this->kelas)()
        ->callAction(TestAction::make('delete')->table($kelas))
        ->assertNotified('Kelas tidak dapat disimpan');

    expect($kelas->fresh())->not->toBeNull();
});

it('membagi acak peserta dari tab kelas', function () {
    KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
    KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'B']);
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(4)->create();

    ($this->kelas)()
        ->callAction(TestAction::make('bagiAcak')->table(), data: ['cakupan' => 'belum'])
        ->assertNotified('4 peserta dibagi ke kelas');

    expect($this->pelatihan->pendaftaran()->whereNull('kelas_pelatihan_id')->count())->toBe(0);

    ($this->pembagian)()
        ->callAction(TestAction::make('bagiAcak')->table(), data: ['cakupan' => 'belum'])
        ->assertNotified('Tidak ada peserta yang perlu dibagi');
});

it('meminta kelas dibuat dulu sebelum bagi acak', function () {
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

    ($this->pembagian)()
        ->callAction(TestAction::make('bagiAcak')->table(), data: ['cakupan' => 'belum'])
        ->assertNotified('Peserta belum dapat dibagi');
});

it('hanya menampilkan peserta terverifikasi pelatihan ini di pembagian kelas, dengan NIK tersamar', function () {
    $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();
    $terdaftar = PelatihanPeserta::factory()->for($this->pelatihan)->create();
    $batal = PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create();
    $pelatihanLain = PelatihanPeserta::factory()->terverifikasi()->create();
    $nik = $terverifikasi->peserta->nik;

    ($this->pembagian)()
        ->assertCanSeeTableRecords([$terverifikasi])
        ->assertCanNotSeeTableRecords([$terdaftar, $batal, $pelatihanLain])
        ->assertSee(substr($nik, 0, 4).'********'.substr($nik, -4))
        ->assertDontSee($nik)
        ->assertSee('Belum dibagi');
});

it('mengatur kelas satu peserta dengan pilihan kelas dari pelatihan yang sama saja', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
    $kelasLain = KelasPelatihan::factory()->create(['nama_kelas' => 'Z']);
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

    ($this->pembagian)()
        ->callAction(TestAction::make('aturKelas')->table($pendaftaran), data: ['kelas_pelatihan_id' => $kelasLain->id])
        ->assertHasActionErrors(['kelas_pelatihan_id']);

    expect($pendaftaran->fresh()->kelas_pelatihan_id)->toBeNull();

    ($this->pembagian)()
        ->callAction(TestAction::make('aturKelas')->table($pendaftaran), data: ['kelas_pelatihan_id' => $kelas->id])
        ->assertHasNoActionErrors();

    expect($pendaftaran->fresh()->kelas_pelatihan_id)->toBe($kelas->id);

    ($this->pembagian)()
        ->callAction(TestAction::make('aturKelas')->table($pendaftaran), data: ['kelas_pelatihan_id' => null]);

    expect($pendaftaran->fresh()->kelas_pelatihan_id)->toBeNull();
});

it('mengatur kelas beberapa peserta sekaligus dan menyaring per kelas', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
    $terpilih = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->count(2)->create();
    $lain = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

    ($this->pembagian)()
        ->selectTableRecords($terpilih)
        ->callAction(TestAction::make('aturKelasMassal')->table()->bulk(), data: ['kelas_pelatihan_id' => $kelas->id])
        ->assertNotified('2 peserta masuk kelas A');

    expect($kelas->pendaftaran()->count())->toBe(2);

    ($this->pembagian)()
        ->filterTable('kelas_pelatihan_id', $kelas->id)
        ->assertCanSeeTableRecords($terpilih)
        ->assertCanNotSeeTableRecords([$lain])
        ->resetTableFilters()
        ->filterTable('belum_dibagi')
        ->assertCanSeeTableRecords([$lain])
        ->assertCanNotSeeTableRecords($terpilih);
});

it('mengunci kelas setelah pelatihan selesai', function () {
    $selesai = Pelatihan::factory()->selesai()->create();
    $kelas = KelasPelatihan::factory()->for($selesai)->create(['nama_kelas' => 'A']);
    $pendaftaran = PelatihanPeserta::factory()->selesai()->for($selesai)->create(['kelas_pelatihan_id' => $kelas->id]);

    ($this->kelas)($selesai)
        ->assertActionHidden(TestAction::make('create')->table())
        ->assertActionHidden(TestAction::make('bagiAcak')->table())
        ->assertActionHidden(TestAction::make('edit')->table($kelas))
        ->assertActionHidden(TestAction::make('delete')->table($kelas));

    ($this->pembagian)($selesai)
        ->assertCanSeeTableRecords([$pendaftaran])
        ->assertActionHidden(TestAction::make('bagiAcak')->table())
        ->assertActionHidden(TestAction::make('aturKelas')->table($pendaftaran));
});

it('menampilkan kelas di daftar dan detail pendaftaran, dan dapat mengaturnya dari detail', function () {
    $kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'Pagi']);
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

    Livewire::test(ViewPendaftaran::class, ['record' => $pendaftaran->getRouteKey()])
        ->assertSee('Belum dibagi')
        ->callAction('aturKelas', data: ['kelas_pelatihan_id' => $kelas->id])
        ->assertHasNoActionErrors()
        ->assertSee('Pagi');

    Livewire::test(ManagePendaftar::class, ['record' => $this->pelatihan->getRouteKey()])
        ->assertTableColumnStateSet('kelasPelatihan.nama_kelas', 'Pagi', $pendaftaran);
});

describe('peserta per kelas', function () {
    beforeEach(function () {
        $this->kelasA = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
        $this->halamanKelas = fn () => Livewire::test(PesertaKelas::class, ['record' => $this->pelatihan->getRouteKey(), 'kelas' => $this->kelasA->getKey()]);
    });

    it('menampilkan peserta aktif kelas ini saja, tanpa NIK, dan tombol kembali ke kelas', function () {
        $anggota = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelasA->id]);
        $batal = PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelasA->id]);
        $kelasLain = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
            ->create(['kelas_pelatihan_id' => KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'B'])->id]);

        ($this->halamanKelas)()
            ->assertCanSeeTableRecords([$anggota])
            ->assertCanNotSeeTableRecords([$batal, $kelasLain])
            ->assertDontSee($anggota->peserta->nik)
            ->assertActionHasUrl('kembali', ManageKelas::getUrl(['record' => $this->pelatihan]));
    });

    it('membuka peserta kelas dari daftar kelas', function () {
        ($this->kelas)()->assertSee(PesertaKelas::getUrl(['record' => $this->pelatihan, 'kelas' => $this->kelasA]), escape: false);
    });

    it('menolak kelas dari pelatihan lain', function () {
        $kelasLain = KelasPelatihan::factory()->create();

        $this->get("/admin/pelatihan/{$this->pelatihan->id}/kelas/{$kelasLain->id}")->assertNotFound();
    });

    it('mengunduh daftar hadir PDF dan Excel', function (string $aksi) {
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelasA->id]);

        ($this->halamanKelas)()
            ->callAction($aksi)
            ->assertFileDownloaded();
    })->with(['daftarHadirPdf', 'daftarHadirExcel']);
});

it('tidak menampilkan kamar di sub-menu pelatihan karena sudah ada di menu Asrama', function () {
    $this->get(ManageKelas::getUrl(['record' => $this->pelatihan]))
        ->assertOk()
        ->assertDontSee('penempatan-kamar?pelatihan=', escape: false);
});
