<?php

use App\Livewire\UnduhSertifikat;
use App\Models\Desa;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SertifikatPeserta;
use App\Services\SertifikatService;
use App\Support\Masking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * PF-40: peserta mengunduh sendiri sertifikatnya di halaman publik dengan NIK.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->service = app(SertifikatService::class);
});

function sertifikatUntuk(Peserta $peserta, string $mulai, ?string $nomor = null): SertifikatPeserta
{
    $pelatihan = Pelatihan::factory()->selesai()->tanggal($mulai, date('Y-m-d', strtotime($mulai.' +3 days')))->create();
    $pendaftaran = PelatihanPeserta::factory()->selesai()->for($pelatihan)->for($peserta)->create();
    $path = "sertifikat/{$pelatihan->id}/{$pendaftaran->id}-uji.pdf";
    Storage::disk('local')->put($path, '%PDF-uji');

    return SertifikatPeserta::factory()->for($pendaftaran, 'pelatihanPeserta')->create(['file_sertifikat' => $path, 'nomor_sertifikat' => $nomor]);
}

describe('pencarian', function () {
    it('mengembalikan null untuk NIK tak dikenal dan NIK tanpa sertifikat', function () {
        $tanpaSertifikat = Peserta::factory()->create(['nik' => '3507000000000001']);
        PelatihanPeserta::factory()->batal()->for($tanpaSertifikat)->create();
        PelatihanPeserta::factory()->for($tanpaSertifikat)->create();

        expect($this->service->untukPublik('3507999999999999'))->toBeNull()
            ->and($this->service->untukPublik('3507000000000001'))->toBeNull();
    });

    it('hanya mengembalikan sertifikat milik NIK itu, terbaru dulu', function () {
        $ani = Peserta::factory()->create(['nik' => '3507000000000001', 'nama_lengkap' => 'Ani Lestari']);
        $lama = sertifikatUntuk($ani, '2025-05-05');
        $baru = sertifikatUntuk($ani, '2026-10-05', '001/BBPKPD/2026');
        sertifikatUntuk(Peserta::factory()->create(['nik' => '3507000000000002']), '2026-09-01');

        $hasil = $this->service->untukPublik('3507000000000001');

        expect($hasil['nama'])->toBe('An* Le*****')
            ->and($hasil['daftar'])->toHaveCount(2)
            ->and(array_column($hasil['daftar'], 'pelatihan'))->toBe([
                $baru->pelatihanPeserta->pelatihan->nama_tampilan,
                $lama->pelatihanPeserta->pelatihan->nama_tampilan,
            ])
            ->and($hasil['daftar'][0])->toMatchArray(['tanggal' => '5–8 Oktober 2026', 'nomor' => '001/BBPKPD/2026'])
            ->and($hasil['daftar'][1]['nomor'])->toBeNull();
    });

    it('memberi tautan unduh relatif bertanda tangan dan kedaluwarsa', function () {
        $sertifikat = sertifikatUntuk(Peserta::factory()->create(['nik' => '3507000000000001']), '2026-10-05');

        $url = $this->service->untukPublik('3507000000000001')['daftar'][0]['url'];

        expect($url)->toStartWith("/sertifikat/unduh/{$sertifikat->id}?")
            ->toContain('expires=')
            ->toContain('signature=');
    });
});

describe('unduhan', function () {
    beforeEach(function () {
        $this->sertifikat = sertifikatUntuk(Peserta::factory()->create(['nik' => '3507000000000001']), '2026-10-05');
        $this->url = $this->service->untukPublik('3507000000000001')['daftar'][0]['url'];
    });

    it('mengunduh sertifikat lewat tautan bertanda tangan dan mencatatnya', function () {
        $respons = $this->get($this->url);

        $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($respons->headers->get('Content-Disposition'))
            ->toContain('attachment')
            ->toContain(Str::slug('sertifikat '.$this->sertifikat->pelatihanPeserta->pelatihan->nama_tampilan).'.pdf')
            ->and($respons->streamedContent())->toBe('%PDF-uji')
            ->and(Activity::query()->where('event', 'unduh_publik')->sole())
            ->causer_id->toBeNull()
            ->subject->is($this->sertifikat)->toBeTrue();
    });

    it('menolak tautan tanpa tanda tangan atau yang kedaluwarsa', function () {
        $this->get("/sertifikat/unduh/{$this->sertifikat->id}")->assertForbidden();

        $this->travel(16)->minutes();
        $this->get($this->url)->assertForbidden();

        expect(Activity::query()->where('event', 'unduh_publik')->exists())->toBeFalse();
    });

    it('tetap sah bila diakses lewat host atau skema lain (di balik proxy)', function () {
        $this->get('https://lain.test'.$this->url)->assertOk();
    });

    it('memberi 404 bila berkas sudah hilang', function () {
        Storage::disk('local')->delete($this->sertifikat->file_sertifikat);

        $this->get($this->url)->assertNotFound();
    });
});

describe('halaman /sertifikat', function () {
    beforeEach(function () {
        RateLimiter::clear('sertifikat-publik:127.0.0.1');
        RateLimiter::clear('sertifikat-publik-gagal:127.0.0.1');
        $this->budi = Peserta::factory()->for(Desa::factory()->state(['nama' => 'Kedungsalam']))
            ->create(['nik' => '3507000000000001', 'nama_lengkap' => 'Budi Santoso']);
        $this->sertifikat = sertifikatUntuk($this->budi, '2026-10-05', '001/BBPKPD/2026');
        $this->pesanSeragam = 'Sertifikat untuk NIK ini belum tersedia. Bila Anda sudah menyelesaikan pelatihan, hubungi panitia.';
    });

    it('dapat dibuka dan ditautkan dari halaman registrasi', function () {
        $this->get('/sertifikat')->assertOk()->assertSee('Unduh Sertifikat');
        $this->get('/daftar')->assertOk()->assertSee(route('sertifikat.publik'), escape: false);
    });

    it('menampilkan sertifikat dengan nama tersamar tanpa data pribadi lain', function () {
        Livewire::test(UnduhSertifikat::class)
            ->fillForm(['nik' => '3507000000000001'])
            ->call('cari')
            ->assertHasNoFormErrors()
            ->assertSee('Bu** Sa*****')
            ->assertSee($this->sertifikat->pelatihanPeserta->pelatihan->nama_tampilan)
            ->assertSee('001/BBPKPD/2026')
            ->assertSee('/sertifikat/unduh/'.$this->sertifikat->id, escape: false)
            ->assertDontSee('Budi Santoso')
            ->assertDontSee('Kedungsalam')
            ->assertDontSee($this->pesanSeragam);
    });

    it('memberi pesan yang sama untuk NIK tak dikenal dan NIK tanpa sertifikat', function () {
        $tanpa = Peserta::factory()->create(['nik' => '3507000000000002']);
        PelatihanPeserta::factory()->for($tanpa)->create();

        foreach (['3507999999999999', '3507000000000002'] as $nik) {
            Livewire::test(UnduhSertifikat::class)
                ->fillForm(['nik' => $nik])
                ->call('cari')
                ->assertSee($this->pesanSeragam)
                ->assertDontSee($tanpa->nama_lengkap)
                ->assertDontSee(Masking::nama($tanpa->nama_lengkap));
        }
    });

    it('merapikan spasi dan tanda hubung pada NIK yang ditempel', function () {
        Livewire::test(UnduhSertifikat::class)
            ->fillForm(['nik' => '3507 0000-0000 0001'])
            ->call('cari')
            ->assertHasNoFormErrors()
            ->assertSee('Bu** Sa*****');
    });

    it('memvalidasi NIK 16 angka', function () {
        Livewire::test(UnduhSertifikat::class)
            ->fillForm(['nik' => '35070000'])
            ->call('cari')
            ->assertHasFormErrors(['nik'])
            ->assertDontSee($this->pesanSeragam);
    });

    it('tidak mencari bila honeypot terisi', function () {
        DB::enableQueryLog();

        Livewire::test(UnduhSertifikat::class)
            ->set('website', 'http://spam.test')
            ->fillForm(['nik' => '3507000000000001'])
            ->call('cari')
            ->assertSee($this->pesanSeragam)
            ->assertDontSee('Bu** Sa*****');

        expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q): bool => str_contains($q, '`peserta`')))->toBeEmpty();
    });

    it('membatasi 10 pencarian gagal per jam per IP', function () {
        foreach (range(1, 10) as $i) {
            Livewire::test(UnduhSertifikat::class)->fillForm(['nik' => sprintf('35079999999999%02d', $i)])->call('cari')->assertSee($this->pesanSeragam);
        }

        Livewire::test(UnduhSertifikat::class)
            ->fillForm(['nik' => '3507000000000001'])
            ->call('cari')
            ->assertSee('Terlalu banyak percobaan. Coba lagi dalam 60 menit.')
            ->assertDontSee('Bu** Sa*****');
    });

    it('memotong jatah gagal sebelum mencari, agar permintaan serentak tidak melewati batas', function () {
        $dilihat = null;
        app()->instance(SertifikatService::class, new class($dilihat) extends SertifikatService
        {
            public function __construct(public mixed &$dilihat) {}

            public function untukPublik(string $nik): ?array
            {
                $this->dilihat = RateLimiter::attempts('sertifikat-publik-gagal:127.0.0.1');

                return null;
            }
        });

        Livewire::test(UnduhSertifikat::class)->fillForm(['nik' => '3507999999999999'])->call('cari');

        expect($dilihat)->toBe(1)
            ->and(RateLimiter::attempts('sertifikat-publik-gagal:127.0.0.1'))->toBe(1);
    });

    it('mengembalikan jatah gagal bila sertifikat ditemukan', function () {
        Livewire::test(UnduhSertifikat::class)->fillForm(['nik' => '3507000000000001'])->call('cari')->assertSee('Bu** Sa*****');

        expect(RateLimiter::attempts('sertifikat-publik-gagal:127.0.0.1'))->toBe(0);
    });

    it('tidak memblokir satu kelas yang mencari bersamaan dari satu jaringan', function () {
        foreach (range(1, 11) as $i) {
            Livewire::test(UnduhSertifikat::class)->fillForm(['nik' => '3507000000000001'])->call('cari')->assertSee('Bu** Sa*****');
        }
    });

    it('membatasi total pencarian per jam per IP', function () {
        config(['bbpd.sertifikat_publik.maks_per_jam' => 3]);

        foreach (range(1, 3) as $i) {
            Livewire::test(UnduhSertifikat::class)->fillForm(['nik' => '3507000000000001'])->call('cari')->assertSee('Bu** Sa*****');
        }

        Livewire::test(UnduhSertifikat::class)->fillForm(['nik' => '3507000000000001'])->call('cari')->assertSee('Terlalu banyak percobaan.');
    });
});
