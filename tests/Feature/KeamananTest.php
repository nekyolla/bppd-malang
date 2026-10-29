<?php

use App\Enums\Peran;
use App\Livewire\Registrasi;
use App\Models\PelatihanPeserta;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('header keamanan', function () {
    it('melarang panel admin dan berkas pribadi dibingkai situs lain', function () {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $pendaftaran = PelatihanPeserta::factory()->create();
        $pendaftaran->update(['data_isian' => [...$pendaftaran->data_isian, 'file_ktp' => "pendaftaran/{$pendaftaran->id}/ktp.pdf"]]);
        Storage::disk('local')->put("pendaftaran/{$pendaftaran->id}/ktp.pdf", 'x');

        $halaman = [
            $this->get('/admin/login'),
            $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get(route('berkas.pendaftaran', [$pendaftaran, 'ktp'])),
        ];

        foreach ($halaman as $respons) {
            $respons->assertOk()
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    });

    it('belum membatasi form registrasi karena integrasi website balai belum diputuskan', function () {
        $this->get('/daftar')->assertOk()->assertHeaderMissing('X-Frame-Options');
    });
});

it('tidak membuka route /storage bawaan untuk disk privat', function () {
    expect(Route::has('storage.local'))->toBeFalse();
});

describe('unggahan sementara form publik', function () {
    it('menolak jenis berkas selain PDF/JPG dan berkas di atas 2 MB sebelum form dikirim', function (UploadedFile $berkas) {
        Livewire::test(Registrasi::class)
            ->set('data.ktp', $berkas)
            ->assertHasErrors('data.ktp');
    })->with([
        'program' => fn () => UploadedFile::fake()->create('ktp.exe', 100, 'application/x-msdownload'),
        'SVG' => fn () => UploadedFile::fake()->create('ktp.svg', 10, 'image/svg+xml'),
        'PDF 3 MB' => fn () => UploadedFile::fake()->create('ktp.pdf', 3072, 'application/pdf'),
    ]);

    it('menerima PDF kecil', function () {
        Livewire::test(Registrasi::class)
            ->set('data.ktp', UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'))
            ->assertHasNoErrors('data.ktp');
    });

    it('membatasi frekuensi unggahan', function () {
        expect(config('livewire.temporary_file_upload.middleware'))->toBe('throttle:20,1');
    });
});

describe('proxy tepercaya', function () {
    afterEach(fn () => TrustProxies::flushState());

    it('membaca IP asli pengunjung dari proxy yang dikonfigurasi', function () {
        config(['bbpd.proxy_tepercaya' => '10.0.0.1, 10.0.0.2']);
        (new AppServiceProvider(app()))->boot();
        Route::get('/_ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/_ip')
            ->assertSeeText('203.0.113.5');
    });

    it('mengabaikan X-Forwarded-For bila tidak ada proxy yang dikonfigurasi', function () {
        Route::get('/_ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/_ip')
            ->assertSeeText('10.0.0.1');
    });
});
