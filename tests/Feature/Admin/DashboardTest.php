<?php

use App\Enums\Peran;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\RincianWilayah;
use App\Filament\Admin\Widgets\PendaftarTerbaru;
use App\Filament\Admin\Widgets\PetaProvinsi;
use App\Filament\Admin\Widgets\RingkasanStatistik;
use App\Filament\Admin\Widgets\StatistikProvinsi;
use App\Models\Desa;
use App\Models\KabKota;
use App\Models\Kecamatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\Provinsi;
use App\Models\User;
use App\Services\StatistikService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());

    $this->jatim = Provinsi::factory()->create(['kode' => '35', 'nama' => 'Jawa Timur']);
    $this->malang = KabKota::factory()->for($this->jatim)->create(['nama' => 'Kab. Malang']);
    $this->kedungsalam = Desa::factory()->for(Kecamatan::factory()->for($this->malang))->create(['nama' => 'Kedungsalam']);
    $banjarejo = Desa::factory()->for(Kecamatan::factory()->for($this->malang))->create(['nama' => 'Banjarejo']);

    $this->pelatihan2025 = Pelatihan::factory()->selesai()->tanggal('2025-05-05', '2025-05-08')->create();
    $this->selesai = PelatihanPeserta::factory()->selesai()->for($this->pelatihan2025)
        ->for(Peserta::factory()->for($this->kedungsalam)->state(['nama_lengkap' => 'Siti Aminah']))
        ->create();
    $this->menunggu = PelatihanPeserta::factory()->count(2)
        ->for(Pelatihan::factory()->dibuka()->tanggal('2026-10-05', '2026-10-08'))
        ->state(fn () => ['peserta_id' => Peserta::factory()->for($banjarejo)->create()->id])
        ->create();
});

it('menampilkan dashboard berisi kartu, peta, tabel provinsi, dan pendaftar terbaru', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(RingkasanStatistik::class)
        ->assertSeeLivewire(PetaProvinsi::class)
        ->assertSeeLivewire(StatistikProvinsi::class)
        ->assertSeeLivewire(PendaftarTerbaru::class);
});

it('menawarkan filter tahun dari tahun anggaran pelatihan', function () {
    Livewire::test(Dashboard::class)
        ->assertFormFieldExists('tahun', 'filtersForm', fn ($field): bool => array_keys($field->getOptions()) === [2026, 2025]);
});

it('menampilkan kartu statistik sesuai filter tahun', function () {
    Livewire::test(RingkasanStatistik::class)
        ->assertSee('Total pendaftar')
        ->assertSee('Menunggu verifikasi')
        ->assertSee('dari 2 desa')
        ->assertSee('/admin/pendaftaran?tableFilters');

    $semua = app(StatistikService::class)->ringkasan();
    $tahun2026 = app(StatistikService::class)->ringkasan(2026);

    expect($semua['pendaftar'])->toBe(3)
        ->and($semua['desa_terlatih'])->toBe(1)
        ->and($tahun2026['desa_terlatih'])->toBe(0);

    $widget = Livewire::test(RingkasanStatistik::class, ['pageFilters' => ['tahun' => '2026']])->instance();
    $stats = (fn (): array => $this->getStats())->call($widget);

    expect($stats[0]->getValue())->toBe('2')
        ->and($stats[1]->getValue())->toBe('2')
        ->and($stats[4]->getValue())->toBe('0');
});

it('mengirim data provinsi ke peta dengan kode Kemendagri dan tautan rincian', function () {
    Livewire::test(PetaProvinsi::class)
        // PHP menjadikan kunci '35' integer; di JavaScript kunci objek tetap string.
        ->assertViewHas('data', fn (array $data): bool => array_keys($data) === [35]
            && $data['35']['desa_terlatih'] === 1
            && $data['35']['desa_belum_terlatih'] === 1
            && str_contains($data['35']['url'], "provinsi={$this->jatim->id}"));
});

it('mencantumkan atribusi dan data peta 38 provinsi yang kodenya unik', function () {
    $geo = json_decode(file_get_contents(resource_path('geo/provinsi.json')), true);
    $kode = array_column(array_column($geo['features'], 'properties'), 'kode');

    expect($kode)->toHaveCount(38)
        ->and(array_unique($kode))->toHaveCount(38)
        ->and($kode)->toContain('35', '91', '92', '93', '94', '95', '96')
        ->and(file_get_contents(resource_path('js/peta-wilayah.js')))->toContain('CC BY 4.0');
});

it('menampilkan tabel provinsi sebagai alternatif peta', function () {
    Livewire::test(StatistikProvinsi::class)
        ->assertSee('Jawa Timur')
        ->assertSee('1 dari 1');
});

it('menampilkan pendaftar terbaru dengan NIK tersamar', function () {
    $nik = $this->menunggu->first()->peserta->nik;

    Livewire::test(PendaftarTerbaru::class)
        ->assertCanSeeTableRecords($this->menunggu)
        ->assertDontSee($nik);

    Livewire::test(PendaftarTerbaru::class, ['pageFilters' => ['tahun' => '2025']])
        ->assertCanSeeTableRecords([$this->selesai])
        ->assertCanNotSeeTableRecords($this->menunggu);
});

it('merinci provinsi, kab/kota, dan desa terlatih tanpa nama peserta', function () {
    $this->get(RincianWilayah::getUrl())->assertOk()->assertSee('Jawa Timur');

    $this->get(RincianWilayah::getUrl(['provinsi' => $this->jatim->id]))
        ->assertOk()
        ->assertSee('Kab. Malang')
        ->assertSee('1 dari 2');

    $this->get(RincianWilayah::getUrl(['provinsi' => $this->jatim->id, 'kab_kota' => $this->malang->id]))
        ->assertOk()
        ->assertSee('Kedungsalam')
        ->assertDontSee('Banjarejo')
        ->assertDontSee('Siti Aminah');
});

it('menyaring rincian wilayah per tahun', function () {
    Livewire::withQueryParams(['provinsi' => $this->jatim->id, 'kab_kota' => $this->malang->id, 'tahun' => 2026])
        ->test(RincianWilayah::class)
        ->assertSee('Belum ada desa terlatih');

    Livewire::withQueryParams(['provinsi' => $this->jatim->id, 'kab_kota' => $this->malang->id, 'tahun' => 2025])
        ->test(RincianWilayah::class)
        ->assertSee('Kedungsalam');
});

it('menampilkan peta provinsi lalu peta kab/kota di rincian wilayah, tanpa peta di tingkat desa', function () {
    $provinsi = Livewire::test(RincianWilayah::class)->instance()->peta();

    expect($provinsi['geo'])->toBe('provinsi')
        ->and(array_keys($provinsi['data']))->toBe([35])
        ->and($provinsi['data'][35]['url'])->toContain("provinsi={$this->jatim->id}");

    $kabKota = Livewire::withQueryParams(['provinsi' => $this->jatim->id])->test(RincianWilayah::class)
        ->assertSee('Peta kab/kota di Jawa Timur')
        ->instance()
        ->peta();

    expect($kabKota['geo'])->toBe('kab-kota/35')
        ->and($kabKota['data'][$this->malang->kode]['desa_terlatih'])->toBe(1)
        ->and($kabKota['data'][$this->malang->kode]['url'])->toContain("kab_kota={$this->malang->id}");

    Livewire::withQueryParams(['provinsi' => $this->jatim->id, 'kab_kota' => $this->malang->id])
        ->test(RincianWilayah::class)
        ->assertDontSee('Peta kab/kota');

    expect(Livewire::withQueryParams(['provinsi' => $this->jatim->id, 'kab_kota' => $this->malang->id])
        ->test(RincianWilayah::class)->instance()->peta())->toBeNull();
});
