<?php

use App\Enums\Peran;
use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ListPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ViewPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->peran(Peran::Admin)->create();
    $this->actingAs($this->admin);
    $this->pelatihan = Pelatihan::factory()->dibuka()->create();
});

/**
 * Sub-halaman Pendaftar milik satu pelatihan (PF-17).
 */
function halamanPendaftar(Pelatihan $pelatihan)
{
    return Livewire::test(ManagePendaftar::class, ['record' => $pelatihan->getRouteKey()]);
}

describe('menu pendaftaran', function () {
    it('dapat dibuka superadmin dan admin, tetapi tidak oleh pengunjung', function () {
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();

        $this->get('/admin/pendaftaran')->assertOk();
        $this->get("/admin/pelatihan/{$this->pelatihan->id}/pendaftar")->assertOk();
        $this->get("/admin/pendaftaran/{$pendaftaran->id}")->assertOk();
        $this->actingAs(User::factory()->peran(Peran::Superadmin)->create())->get('/admin/pendaftaran')->assertOk();

        auth()->logout();
        $this->get('/admin/pendaftaran')->assertRedirect('/admin/login');
    });

    it('menampilkan satu baris per pelatihan dengan jumlah per status dan membuka pendaftarnya', function () {
        PelatihanPeserta::factory()->for($this->pelatihan)->count(2)->create();
        PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();
        PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create();
        $lain = Pelatihan::factory()->selesai()->create();

        Livewire::test(ListPendaftaran::class)
            ->assertCanSeeTableRecords([$this->pelatihan, $lain])
            ->assertTableColumnStateSet('jumlah_menunggu', 2, $this->pelatihan)
            ->assertTableColumnStateSet('jumlah_terverifikasi', 1, $this->pelatihan)
            ->assertTableColumnStateSet('jumlah_batal', 1, $this->pelatihan)
            ->assertTableColumnStateSet('jumlah_pendaftar', 4, $this->pelatihan)
            ->assertSee(ManagePendaftar::getUrl(['record' => $this->pelatihan]), escape: false);
    });

    it('menampilkan jumlah pendaftar yang menunggu verifikasi di menu', function () {
        PelatihanPeserta::factory()->count(3)->create();
        PelatihanPeserta::factory()->terverifikasi()->create();

        expect(PendaftaranResource::getNavigationBadge())->toBe('3');
    });
});

describe('pendaftar per pelatihan', function () {
    it('hanya menampilkan pendaftar pelatihan ini dengan NIK tersamar', function () {
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $lain = PelatihanPeserta::factory()->create();
        $nik = $pendaftaran->peserta->nik;

        halamanPendaftar($this->pelatihan)
            ->assertCanSeeTableRecords([$pendaftaran])
            ->assertCanNotSeeTableRecords([$lain])
            ->assertSee(substr($nik, 0, 4).'********'.substr($nik, -4))
            ->assertDontSee($nik)
            ->assertTableColumnDoesNotExist('pelatihan.nama_tampilan')
            ->assertActionExists('kembali');
    });

    it('menyaring pendaftar per status dan wilayah', function () {
        $terdaftar = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();
        $desa = Desa::factory()->create();
        $diDesa = PelatihanPeserta::factory()->for($this->pelatihan)->for(Peserta::factory()->for($desa))->create();

        halamanPendaftar($this->pelatihan)
            ->filterTable('status', StatusPendaftaran::Terverifikasi->value)
            ->assertCanSeeTableRecords([$terverifikasi])
            ->assertCanNotSeeTableRecords([$terdaftar, $diDesa]);

        halamanPendaftar($this->pelatihan)
            ->filterTable('wilayah', ['provinsi_id' => $desa->kecamatan->kabKota->provinsi_id])
            ->assertCanSeeTableRecords([$diDesa])
            ->assertCanNotSeeTableRecords([$terdaftar, $terverifikasi]);

        halamanPendaftar($this->pelatihan)
            ->filterTable('wilayah', ['kab_kota_id' => $desa->kecamatan->kab_kota_id])
            ->assertCanSeeTableRecords([$diDesa])
            ->assertCanNotSeeTableRecords([$terdaftar]);
    });

    it('membuka daftar yang menunggu verifikasi lewat tautan peringatan', function () {
        $menunggu = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        $this->get(ManagePendaftar::urlMenunggu($this->pelatihan))
            ->assertOk()
            ->assertSee($menunggu->peserta->nama_lengkap)
            ->assertDontSee($terverifikasi->peserta->nama_lengkap);
    });

    it('mencari pendaftar berdasarkan nama atau NIK', function () {
        $siti = PelatihanPeserta::factory()->for($this->pelatihan)->for(Peserta::factory()->state(['nama_lengkap' => 'Siti Aminah', 'nik' => '3507010101900001']))->create();
        $budi = PelatihanPeserta::factory()->for($this->pelatihan)->for(Peserta::factory()->state(['nama_lengkap' => 'Budi Santoso']))->create();

        halamanPendaftar($this->pelatihan)
            ->searchTable('siti')
            ->assertCanSeeTableRecords([$siti])
            ->assertCanNotSeeTableRecords([$budi]);

        halamanPendaftar($this->pelatihan)
            ->searchTable('3507010101900001')
            ->assertCanSeeTableRecords([$siti])
            ->assertCanNotSeeTableRecords([$budi]);
    });
});

describe('verifikasi', function () {
    it('memverifikasi dari tabel sekaligus merapikan salah ketik', function () {
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();

        halamanPendaftar($this->pelatihan)
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

    it('menampilkan pratinjau KTP dan pas foto di modal verifikasi', function (string $ktp, string $tag) {
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $pendaftaran->update(['data_isian' => [...$pendaftaran->data_isian, 'file_ktp' => "pendaftaran/{$pendaftaran->id}/{$ktp}", 'foto' => "pendaftaran/{$pendaftaran->id}/foto.jpg"]]);

        halamanPendaftar($this->pelatihan)
            ->mountAction(TestAction::make('verifikasi')->table($pendaftaran))
            ->assertMountedActionModalSeeHtml(e(route('berkas.pendaftaran', [$pendaftaran, 'ktp'])))
            ->assertMountedActionModalSeeHtml(e(route('berkas.pendaftaran', [$pendaftaran, 'foto'])))
            ->assertMountedActionModalSeeHtml($tag);
    })->with([
        'KTP PDF' => ['ktp.pdf', '<iframe'],
        'KTP JPG' => ['ktp.jpg', 'alt="KTP"'],
    ]);

    it('membiarkan modal terbuka dan memberi tahu jika koreksi ditolak service', function () {
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $lain = Peserta::factory()->create();

        halamanPendaftar($this->pelatihan)
            ->callAction(TestAction::make('verifikasi')->table($pendaftaran), data: ['nik' => $lain->nik])
            ->assertNotified('Pendaftaran belum dapat diverifikasi')
            ->assertActionMounted(TestAction::make('verifikasi')->table($pendaftaran));

        expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terdaftar);
    });

    it('menyembunyikan tombol verifikasi untuk pendaftaran yang sudah diproses', function () {
        $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        halamanPendaftar($this->pelatihan)
            ->assertActionHidden(TestAction::make('verifikasi')->table($pendaftaran));
    });

    it('memverifikasi massal dan melewati isian yang berbeda', function () {
        $siap = PelatihanPeserta::factory()->for($this->pelatihan)->count(2)->create();
        $berbeda = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        $berbeda->update(['data_isian' => [...$berbeda->data_isian, 'nama_lengkap' => 'Nama Lain']]);

        halamanPendaftar($this->pelatihan)
            ->selectTableRecords([...$siap, $berbeda])
            ->callAction(TestAction::make('verifikasiMassal')->table()->bulk())
            ->assertNotified('2 pendaftaran terverifikasi');

        expect($berbeda->fresh()->status)->toBe(StatusPendaftaran::Terdaftar)
            ->and($siap->first()->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);
    });

    it('menetapkan pengganti dari panel verifikasi', function () {
        Carbon::setTestNow('2026-10-01 09:00');
        $pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
        $batal = PelatihanPeserta::factory()->batal()->for($pelatihan)->create();
        $calon = PelatihanPeserta::factory()->for($pelatihan)
            ->for(Peserta::factory()->state(['desa_id' => $batal->peserta->desa_id]))
            ->create();

        halamanPendaftar($pelatihan)
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

        halamanPendaftar($pelatihan)
            ->mountAction(TestAction::make('verifikasi')->table($calon))
            ->assertFormFieldHidden('menggantikan_id');
    });
});

describe('detail pendaftaran', function () {
    it('menampilkan detail, tautan berkas, isian yang berbeda, dan tombol kembali', function () {
        $lama = PelatihanPeserta::factory()->selesai()->create();
        $baru = PelatihanPeserta::factory()->for($this->pelatihan)->for($lama->peserta)->create();
        $baru->update(['data_isian' => [...$baru->data_isian, 'alamat_domisili' => 'Alamat Pindahan Baru']]);

        Livewire::test(ViewPendaftaran::class, ['record' => $baru->getRouteKey()])
            ->assertSee('Isian berbeda dari data lama')
            ->assertSee('Alamat Pindahan Baru')
            ->assertSee($lama->peserta->alamat_domisili)
            ->assertSee(route('berkas.pendaftaran', [$baru, 'ktp']))
            ->assertActionVisible('verifikasi')
            ->assertActionHasUrl('kembali', ManagePendaftar::getUrl(['record' => $this->pelatihan]));

        Livewire::test(ViewPendaftaran::class, ['record' => $lama->getRouteKey()])
            ->assertDontSee('Isian berbeda dari data lama')
            ->assertSee('Snapshot saat pelatihan')
            ->assertActionHidden('verifikasi');
    });
});

describe('pembatalan, pengembalian, dan penghapusan', function () {
    it('membatalkan peserta dengan alasan wajib', function () {
        $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        halamanPendaftar($this->pelatihan)
            ->callAction(TestAction::make('batalkan')->table($pendaftaran), data: ['alasan_batal' => ''])
            ->assertHasFormErrors(['alasan_batal' => 'required']);

        expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);

        halamanPendaftar($this->pelatihan)
            ->callAction(TestAction::make('batalkan')->table($pendaftaran), data: ['alasan_batal' => 'Sakit'])
            ->assertNotified('Peserta dibatalkan');

        expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Batal)
            ->and($pendaftaran->fresh()->alasan_batal)->toBe('Sakit');

        halamanPendaftar($this->pelatihan)
            ->assertActionHidden(TestAction::make('batalkan')->table($pendaftaran))
            ->assertActionVisible(TestAction::make('kembalikan')->table($pendaftaran));
    });

    it('mengembalikan pendaftaran yang salah dibatalkan', function () {
        $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->batal()->for($this->pelatihan)->create();

        halamanPendaftar($this->pelatihan)
            ->callAction(TestAction::make('kembalikan')->table($pendaftaran))
            ->assertNotified('Pendaftaran dikembalikan: Terverifikasi');

        expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);
    });

    it('menghapus pendaftaran iseng yang belum diverifikasi beserta berkasnya', function () {
        Storage::fake('local');
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();
        Storage::disk('local')->put("pendaftaran/{$pendaftaran->id}/ktp.pdf", 'x');
        $terverifikasi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();

        halamanPendaftar($this->pelatihan)
            ->assertActionHidden(TestAction::make('hapus')->table($terverifikasi))
            ->callAction(TestAction::make('hapus')->table($pendaftaran))
            ->assertNotified('Pendaftaran dihapus');

        expect(PelatihanPeserta::find($pendaftaran->id))->toBeNull();
        Storage::disk('local')->assertMissing("pendaftaran/{$pendaftaran->id}/ktp.pdf");
    });

    it('kembali ke daftar pendaftar setelah menghapus dari halaman detail', function () {
        Storage::fake('local');
        $pendaftaran = PelatihanPeserta::factory()->for($this->pelatihan)->create();

        Livewire::test(ViewPendaftaran::class, ['record' => $pendaftaran->getRouteKey()])
            ->callAction('hapus')
            ->assertRedirect(ManagePendaftar::getUrl(['record' => $this->pelatihan]));
    });
});

it('menampilkan snapshot jabatan beserta periodenya di detail pendaftaran (PF-25)', function () {
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());
    $peserta = Peserta::factory()->for(Jabatan::factory()->berperiode()->state(['nama_jabatan' => 'Kepala Desa']))->create(['periode_jabatan' => 1]);
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->for($peserta)->create();

    Livewire::test(ViewPendaftaran::class, ['record' => $pendaftaran->getRouteKey()])
        ->assertSee('Kepala Desa (periode ke-1)')
        ->assertSee('Periode ke-1');
});
