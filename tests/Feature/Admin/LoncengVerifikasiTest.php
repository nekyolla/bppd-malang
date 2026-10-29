<?php

use App\Enums\Peran;
use App\Filament\Admin\Livewire\LoncengVerifikasi;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Admin\Resources\Peserta\PesertaResource;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->peran(Peran::Admin)->create());
});

describe('lonceng verifikasi', function () {
    it('merinci pendaftar menunggu per pelatihan dengan tautan ke daftar tersaring', function () {
        $pelatihan = Pelatihan::factory()->dibuka()->create();
        PelatihanPeserta::factory()->count(3)->for($pelatihan)->create();
        PelatihanPeserta::factory()->for($pelatihan)->terverifikasi()->create();

        Livewire::test(LoncengVerifikasi::class)
            ->assertViewHas('total', 3)
            ->assertSee('3 pendaftar di 1 pelatihan')
            ->assertSee($pelatihan->nama_tampilan)
            ->assertSee(ManagePendaftar::urlMenunggu($pelatihan), escape: false);
    });

    it('menampilkan keadaan kosong bila semua sudah diperiksa', function () {
        PelatihanPeserta::factory()->terverifikasi()->create();

        Livewire::test(LoncengVerifikasi::class)
            ->assertViewHas('total', 0)
            ->assertSee('Semua pendaftar sudah diperiksa.');
    });

    it('membatasi daftar dan menyebut sisa pelatihan', function () {
        PelatihanPeserta::factory()->count(LoncengVerifikasi::BATAS + 2)->create();

        Livewire::test(LoncengVerifikasi::class)
            ->assertViewHas('pelatihan', fn ($pelatihan) => $pelatihan->count() === LoncengVerifikasi::BATAS)
            ->assertViewHas('lainnya', 2)
            ->assertSee('Lihat semua (+2 pelatihan)');
    });

    it('langsung diperbarui saat aksi pendaftaran mengirim event', function () {
        $lonceng = Livewire::test(LoncengVerifikasi::class)->assertViewHas('total', 0);

        PelatihanPeserta::factory()->count(2)->create();

        $lonceng->dispatch(LoncengVerifikasi::PERBARUI)->assertViewHas('total', 2);
    });

    it('tampil di topbar panel', function () {
        PelatihanPeserta::factory()->count(2)->create();

        $this->get(PelatihanResource::getUrl('index'))
            ->assertOk()
            ->assertSee('fi-lonceng', escape: false)
            ->assertSee('Cari peserta atau pelatihan');
    });
});

describe('menu samping', function () {
    it('menutup grup Data Master dan Pengguna secara bawaan', function () {
        $tertutup = collect(Filament::getPanel('admin')->getNavigationGroups())
            ->filter(fn ($grup): bool => $grup instanceof NavigationGroup && $grup->isCollapsed())
            ->map(fn (NavigationGroup $grup): string => $grup->getLabel())
            ->values()
            ->all();

        expect($tertutup)->toBe(['Data Master', 'Pengguna']);
    });

    it('mempertahankan urutan grup menu', function () {
        $this->actingAs(User::factory()->peran(Peran::Superadmin)->create());

        $grup = collect(Filament::getPanel('admin')->getNavigation())
            ->map(fn (NavigationGroup $grup): ?string => $grup->getLabel())
            ->filter()
            ->values()
            ->all();

        expect($grup)->toBe(['Pelatihan', 'Asrama', 'Data Master', 'Pengguna']);
    });
});

describe('pencarian topbar', function () {
    it('hanya mencari di peserta dan pelatihan', function () {
        $sumber = collect(Filament::getResources())
            ->filter(fn (string $resource): bool => $resource::canGloballySearch())
            ->values()
            ->all();

        expect($sumber)->toEqualCanonicalizing([PesertaResource::class, PelatihanResource::class]);
    });

    it('menemukan peserta lewat NIK dengan NIK tersamar di hasil', function () {
        $peserta = Peserta::factory()->create(['nik' => '3507123456789012']);

        $hasil = PesertaResource::getGlobalSearchResults('3507123456789012');

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->details)->toMatchArray(['NIK' => '3507********9012'])
            ->and(json_encode($hasil->first()->details))->not->toContain('3507123456789012');
    });

    it('menemukan pelatihan lewat judul', function () {
        $pelatihan = Pelatihan::factory()->create();

        $hasil = PelatihanResource::getGlobalSearchResults($pelatihan->judulPelatihan->judul);

        expect($hasil->pluck('title')->all())->toContain($pelatihan->nama_tampilan);
    });
});
