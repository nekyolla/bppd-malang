<?php

use App\Enums\JenisKelamin;
use App\Enums\Peran;
use App\Enums\TipeKamar;
use App\Enums\TipeLokasi;
use App\Filament\Admin\Pages\PenempatanKamar;
use App\Filament\Admin\Resources\Asrama\Pages\EditAsrama;
use App\Filament\Admin\Resources\Asrama\RelationManagers\KamarRelationManager;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ViewPendaftaran;
use App\Models\Asrama;
use App\Models\AsramaPeserta;
use App\Models\KamarAsrama;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\User;
use App\Services\AsramaService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 09:00');
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->peran(Peran::Admin)->create();
    $this->actingAs($this->admin);
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08')->create();
    $this->asrama = Asrama::factory()->create(['nama_asrama' => 'Anggrek']);
    $this->kamar = KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '01', 'kapasitas' => 4]);
    $this->halaman = fn () => Livewire::test(PenempatanKamar::class, ['pelatihan' => $this->pelatihan->id]);
});

function pesertaKamar(Pelatihan $pelatihan, JenisKelamin $jenisKelamin = JenisKelamin::LakiLaki): PelatihanPeserta
{
    return PelatihanPeserta::factory()->terverifikasi()->for($pelatihan)
        ->for(Peserta::factory()->state(['jenis_kelamin' => $jenisKelamin]))
        ->create();
}

it('dapat dibuka superadmin dan admin dan memilih pelatihan BBPD yang sedang dibuka', function () {
    Pelatihan::factory()->selesai()->tanggal('2025-06-02', '2025-06-05')->create();

    $this->get('/admin/penempatan-kamar')->assertOk()->assertSee('Asrama Anggrek');
    $this->actingAs(User::factory()->peran(Peran::Superadmin)->create())->get('/admin/penempatan-kamar')->assertOk();

    Livewire::test(PenempatanKamar::class)->assertSet('pelatihan', $this->pelatihan->id);

    auth()->logout();
    $this->get('/admin/penempatan-kamar')->assertRedirect('/admin/login');
});

it('tidak menampilkan pelatihan di luar BBPD', function () {
    $luar = Pelatihan::factory()->dibuka()->create(['tipe_lokasi' => TipeLokasi::Luar, 'keterangan_lokasi' => 'Surabaya']);

    Livewire::test(PenempatanKamar::class, ['pelatihan' => $luar->id])
        ->assertSee('Hanya untuk pelatihan di Balai Besar.')
        ->assertDontSee('Asrama Anggrek');
});

it('menampilkan denah, penghuni, dan ringkasan tempat tidur', function () {
    $penghuni = pesertaKamar($this->pelatihan, JenisKelamin::Perempuan);
    app(AsramaService::class)->tempatkan($penghuni, $this->kamar, $this->admin);
    KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 2]);
    pesertaKamar($this->pelatihan);

    ($this->halaman)()
        ->assertSee('Perempuan · 1/4')
        ->assertSee('Kosong · 0/2')
        ->assertSee($penghuni->peserta->nama_lengkap)
        ->assertSee('1 laki-laki · 1 perempuan')
        ->assertSee('1 laki-laki · 0 perempuan')
        ->assertSee('Cukup');
});

it('menempatkan beberapa peserta sekaligus lewat modal kamar', function () {
    $peserta = [pesertaKamar($this->pelatihan), pesertaKamar($this->pelatihan)];

    ($this->halaman)()
        ->callAction(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]), data: [
            'peserta' => collect($peserta)->pluck('id')->all(),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2 peserta ditempatkan di kamar Anggrek-01')
        ->assertSee('Laki-laki · 2/4');

    expect(PenggunaanKamar::sole()->tipe)->toBe(TipeKamar::LakiLaki)
        ->and(AsramaPeserta::count())->toBe(2);
});

it('hanya menawarkan peserta yang cocok dengan gender kamar', function () {
    app(AsramaService::class)->tempatkan(pesertaKamar($this->pelatihan), $this->kamar, $this->admin);
    $laki = pesertaKamar($this->pelatihan);
    $perempuan = pesertaKamar($this->pelatihan, JenisKelamin::Perempuan);

    ($this->halaman)()
        ->mountAction(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]))
        ->assertFormFieldExists('peserta', function (Select $field) use ($laki, $perempuan): bool {
            return array_key_exists($laki->id, $field->getOptions()) && ! array_key_exists($perempuan->id, $field->getOptions());
        });

    ($this->halaman)()
        ->callAction(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]), data: ['peserta' => [$perempuan->id]])
        ->assertHasActionErrors(['peserta.0']);

    expect($perempuan->fresh()->penempatanKamar)->toBeNull();

    ($this->halaman)()
        ->callAction(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]), data: ['peserta' => [$laki->id]])
        ->assertHasNoActionErrors();
});

it('menjadikan kamar kosong sebagai kamar pasutri', function () {
    $suami = pesertaKamar($this->pelatihan);
    $istri = pesertaKamar($this->pelatihan, JenisKelamin::Perempuan);

    ($this->halaman)()
        ->callAction(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]), data: [
            'peserta' => [$suami->id, $istri->id],
            'pasutri' => true,
        ])
        ->assertHasNoActionErrors()
        ->assertSee('Pasutri · Penuh 2/2');

    expect(PenggunaanKamar::sole()->tipe)->toBe(TipeKamar::Pasutri);
});

it('menampilkan alasan penolakan dari service sebagai notifikasi', function () {
    $laki = pesertaKamar($this->pelatihan);
    $perempuan = pesertaKamar($this->pelatihan, JenisKelamin::Perempuan);

    ($this->halaman)()
        ->callAction(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]), data: ['peserta' => [$laki->id, $perempuan->id]])
        ->assertNotified('Peserta belum dapat ditempatkan');

    expect(AsramaPeserta::count())->toBe(0);
});

it('mengeluarkan peserta dari kamar dan mengembalikan kamar menjadi kosong', function () {
    $penempatan = app(AsramaService::class)->tempatkan(pesertaKamar($this->pelatihan), $this->kamar, $this->admin);

    ($this->halaman)()
        ->callAction(TestAction::make('keluarkan')->arguments(['penempatan' => $penempatan->id]))
        ->assertNotified('Peserta keluar dari kamar')
        ->assertSee('Kosong · 0/4');

    expect(AsramaPeserta::count())->toBe(0)->and(PenggunaanKamar::count())->toBe(0);
});

it('mengosongkan kamar sekaligus dengan penghuni tercentang semua secara bawaan', function () {
    app(AsramaService::class)->tempatkanBanyak([pesertaKamar($this->pelatihan), pesertaKamar($this->pelatihan), pesertaKamar($this->pelatihan)], $this->kamar, $this->admin);

    ($this->halaman)()
        ->mountAction(TestAction::make('kosongkan')->arguments(['kamar' => $this->kamar->id]))
        // Urutan mengikuti denah (nama penghuni).
        ->assertSchemaStateSet(['penempatan' => AsramaPeserta::query()->with('pelatihanPeserta.peserta')->get()->sortBy('pelatihanPeserta.peserta.nama_lengkap')->pluck('id')->values()->all()])
        ->callMountedAction()
        ->assertNotified('3 peserta keluar dari kamar Anggrek-01')
        ->assertSee('Kosong · 0/4');

    expect(AsramaPeserta::count())->toBe(0)->and(PenggunaanKamar::count())->toBe(0);
});

it('mengeluarkan sebagian penghuni lewat kosongkan kamar', function () {
    [$a, $b] = [pesertaKamar($this->pelatihan), pesertaKamar($this->pelatihan)];
    app(AsramaService::class)->tempatkanBanyak([$a, $b], $this->kamar, $this->admin);

    ($this->halaman)()
        ->callAction(TestAction::make('kosongkan')->arguments(['kamar' => $this->kamar->id]), data: ['penempatan' => [$a->fresh()->penempatanKamar->id]])
        ->assertNotified('1 peserta keluar dari kamar Anggrek-01');

    expect($a->fresh()->penempatanKamar)->toBeNull()->and($b->fresh()->penempatanKamar)->not->toBeNull();
});

it('mengisi kamar otomatis dari tombol di kepala halaman', function () {
    pesertaKamar($this->pelatihan);
    pesertaKamar($this->pelatihan, JenisKelamin::Perempuan);
    KamarAsrama::factory()->for($this->asrama)->create(['no_kamar' => '02', 'kapasitas' => 4]);

    ($this->halaman)()
        ->callAction('isiOtomatis')
        ->assertNotified('2 peserta ditempatkan otomatis');

    expect(AsramaPeserta::count())->toBe(2)->and(PenggunaanKamar::count())->toBe(2);
});

it('menonaktifkan isi otomatis bila semua peserta sudah dapat kamar', function () {
    ($this->halaman)()->assertActionDisabled('isiOtomatis');
});

it('menandai kamar yang dipakai pelatihan lain sebagai tidak tersedia', function () {
    $lain = Pelatihan::factory()->dibuka()->tanggal('2026-10-07', '2026-10-09')->create();
    app(AsramaService::class)->tempatkan(pesertaKamar($lain), $this->kamar, $this->admin);

    ($this->halaman)()
        ->assertSee('Tidak tersedia')
        ->assertSee("Dipakai {$lain->nama_tampilan}");
});

it('hanya menampilkan penempatan tanpa aksi setelah pelatihan selesai', function () {
    $selesai = Pelatihan::factory()->selesai()->tanggal('2026-09-01', '2026-09-03')->create();
    $penempatan = AsramaPeserta::factory()->for(PenggunaanKamar::factory()->for($selesai)->for($this->kamar))->create();

    Livewire::test(PenempatanKamar::class, ['pelatihan' => $selesai->id])
        ->assertSee('Pelatihan sudah selesai. Penempatan hanya dapat dilihat.')
        ->assertSee($penempatan->pelatihanPeserta->peserta->nama_lengkap)
        ->assertActionHidden(TestAction::make('keluarkan')->arguments(['penempatan' => $penempatan->id]))
        ->assertActionHidden(TestAction::make('tempatkan')->arguments(['kamar' => $this->kamar->id]))
        ->assertActionHidden('isiOtomatis');
});

it('menolak menurunkan kapasitas kamar di bawah jumlah penghuni', function () {
    app(AsramaService::class)->tempatkanBanyak([pesertaKamar($this->pelatihan), pesertaKamar($this->pelatihan), pesertaKamar($this->pelatihan)], $this->kamar, $this->admin);
    $this->actingAs(User::factory()->peran(Peran::Superadmin)->create());

    Livewire::test(KamarRelationManager::class, ['ownerRecord' => $this->asrama, 'pageClass' => EditAsrama::class])
        ->callAction(TestAction::make('edit')->table($this->kamar), data: ['no_kamar' => '01', 'kapasitas' => 2])
        ->assertHasActionErrors(['kapasitas']);

    Livewire::test(KamarRelationManager::class, ['ownerRecord' => $this->asrama, 'pageClass' => EditAsrama::class])
        ->callAction(TestAction::make('edit')->table($this->kamar), data: ['no_kamar' => '01', 'kapasitas' => 3])
        ->assertHasNoActionErrors();

    expect($this->kamar->fresh()->kapasitas)->toBe(3);
});

it('menampilkan kamar di detail pendaftaran', function () {
    $pendaftaran = pesertaKamar($this->pelatihan);

    Livewire::test(ViewPendaftaran::class, ['record' => $pendaftaran->getRouteKey()])->assertSee('Belum ditempatkan');

    app(AsramaService::class)->tempatkan($pendaftaran, $this->kamar, $this->admin);

    Livewire::test(ViewPendaftaran::class, ['record' => $pendaftaran->getRouteKey()])->assertSee('Anggrek-01');
});
