<?php

use App\Enums\Peran;
use App\Models\Asrama;
use App\Models\AsramaPeserta;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KabKota;
use App\Models\KamarAsrama;
use App\Models\Kecamatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Provinsi;
use App\Models\SertifikatPeserta;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Models\User;
use App\Services\PendaftaranService;
use App\Services\StatistikService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StatusPtkpSeeder;
use Database\Seeders\SumberDanaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

it('mengisi delapan status PTKP', function () {
    $this->seed(StatusPtkpSeeder::class);

    expect(StatusPtkp::orderBy('id')->pluck('kode')->all())
        ->toBe(['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3']);
});

it('mengisi sumber dana dengan keterangan wajib hanya untuk Lainnya', function () {
    $this->seed(SumberDanaSeeder::class);

    expect(SumberDana::count())->toBe(5)
        ->and(SumberDana::where('butuh_keterangan', true)->pluck('nama')->all())->toBe(['Lainnya']);
});

it('aman dijalankan ulang tanpa menduplikasi atau mengaktifkan kembali data', function () {
    $this->seed([StatusPtkpSeeder::class, SumberDanaSeeder::class]);
    StatusPtkp::where('kode', 'K/3')->update(['is_aktif' => false]);
    SumberDana::where('nama', 'Mandiri')->update(['is_aktif' => false]);

    $this->seed([StatusPtkpSeeder::class, SumberDanaSeeder::class]);

    expect(StatusPtkp::count())->toBe(8)
        ->and(SumberDana::count())->toBe(5)
        ->and(StatusPtkp::where('kode', 'K/3')->value('is_aktif'))->toBeFalse()
        ->and(SumberDana::aktif()->pluck('nama')->all())->not->toContain('Mandiri');
});

it('mengisi data contoh lokal secara idempoten', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Jabatan::count())->toBe(count(DemoSeeder::JABATAN))
        ->and(Jabatan::where('memiliki_periode', true)->pluck('nama_jabatan')->all())->toBe(['Kepala Desa'])
        ->and(JudulPelatihan::count())->toBe(4)
        ->and(Asrama::count())->toBe(2)
        ->and(KamarAsrama::count())->toBe(16)
        ->and(KamarAsrama::whereHas('asrama', fn ($q) => $q->where('nama_asrama', 'Melati'))->value('kapasitas'))->toBe(2)
        ->and(User::role(Peran::Admin->value)->pluck('username')->all())->toBe(DemoSeeder::AKUN_PANITIA)
        ->and(Pelatihan::count())->toBe(0);
});

it('tidak berjalan di luar lingkungan lokal', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])
        ->expectsOutputToContain('DemoSeeder hanya untuk lingkungan lokal.')
        ->assertSuccessful();

    expect(Jabatan::count())->toBe(0)
        ->and(User::count())->toBe(0);
});

describe('skenario pelatihan', function () {
    beforeEach(function () {
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow('2026-11-04 10:00');

        // Satu provinsi Jawa Timur dan dua provinsi lain, masing-masing satu kecamatan berisi 6 desa.
        foreach (['35', '53', '73'] as $kode) {
            $kecamatan = Kecamatan::factory()->for(KabKota::factory()->for(Provinsi::factory()->state(['kode' => $kode])))->create();
            Desa::factory()->count(6)->for($kecamatan)->create();
        }

        $this->seed(DemoSeeder::class);
    });

    it('membuat lima pelatihan dengan tahap berbeda, tanggal relatif terhadap hari ini, sekali saja', function () {
        $this->seed(DemoSeeder::class);

        expect(Pelatihan::orderBy('tanggal_mulai')->get()->map(fn (Pelatihan $p): array => [$p->status->value, $p->tanggal_mulai->toDateString()])->all())->toBe([
            ['selesai', '2025-11-03'],
            ['selesai', '2026-09-28'],
            ['berjalan', '2026-11-03'],
            ['dibuka', '2026-12-02'],
            ['draft', '2026-12-30'],
        ])->and(now()->toDateTimeString())->toBe('2026-11-04 10:00:00')
            ->and(Auth::user())->toBeNull();
    });

    it('mengisi status pendaftaran, kelas, pengganti, kamar, dan sertifikat sesuai skenario', function () {
        $ringkasan = Pelatihan::orderBy('tanggal_mulai')->get()->map(fn (Pelatihan $p): array => [
            ...$p->pendaftaran()->toBase()->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status')->map(fn ($n): int => (int) $n)->sortKeys()->all(),
            'kelas' => $p->kelas()->count(),
            'pengganti' => $p->pendaftaran()->whereNotNull('menggantikan_id')->count(),
            'kamar' => AsramaPeserta::whereHas('pelatihanPeserta', fn ($q) => $q->where('pelatihan_id', $p->id))->count(),
            'sertifikat' => SertifikatPeserta::whereHas('pelatihanPeserta', fn ($q) => $q->where('pelatihan_id', $p->id))->count(),
        ])->all();

        expect($ringkasan)->toBe([
            ['batal' => 1, 'selesai' => 19, 'kelas' => 1, 'pengganti' => 0, 'kamar' => 0, 'sertifikat' => 19],
            ['batal' => 2, 'selesai' => 22, 'kelas' => 2, 'pengganti' => 0, 'kamar' => 22, 'sertifikat' => 22],
            ['batal' => 1, 'terdaftar' => 2, 'terverifikasi' => 30, 'kelas' => 2, 'pengganti' => 1, 'kamar' => 30, 'sertifikat' => 5],
            ['terdaftar' => 19, 'terverifikasi' => 7, 'kelas' => 0, 'pengganti' => 0, 'kamar' => 0, 'sertifikat' => 0],
            ['kelas' => 0, 'pengganti' => 0, 'kamar' => 0, 'sertifikat' => 0],
        ]);
    });

    it('mengikuti aturan bisnis: snapshot, kapasitas kamar, pengganti sedesa, berkas privat', function () {
        $diverifikasi = PelatihanPeserta::whereIn('status', ['terverifikasi', 'selesai'])->get();
        $pengganti = PelatihanPeserta::whereNotNull('menggantikan_id')->sole();

        expect($diverifikasi->every(fn (PelatihanPeserta $p): bool => $p->desa_id_saat_pelatihan && $p->jabatan_id_saat_pelatihan && $p->diverifikasi_oleh))->toBeTrue()
            ->and(PenggunaanKamar::withCount('penghuni')->with('kamarAsrama')->get()->every(fn ($k): bool => $k->penghuni_count <= $k->kamarAsrama->kapasitas))->toBeTrue()
            ->and(PelatihanPeserta::where('status', 'batal')->whereHas('penempatanKamar')->exists())->toBeFalse()
            ->and($pengganti->desa_id_saat_pelatihan)->toBe($pengganti->menggantikan->desa_id_saat_pelatihan)
            ->and($pengganti->kelas_pelatihan_id)->toBe($pengganti->menggantikan->kelas_pelatihan_id)
            ->and(Storage::disk('public')->allFiles())->toBeEmpty();

        Storage::disk('local')->assertExists([
            ...PelatihanPeserta::pluck('file_surat_tugas')->all(),
            ...SertifikatPeserta::pluck('file_sertifikat')->all(),
        ]);
    });

    it('menyiapkan bahan UAT: perbedaan isian NIK lama, kepala desa berperiode, data iseng', function () {
        $menunggu = PelatihanPeserta::where('status', 'terdaftar')
            ->whereHas('pelatihan', fn ($q) => $q->where('status', 'dibuka'))
            ->with('peserta.jabatan')->get();
        $layanan = app(PendaftaranService::class);

        expect($menunggu->filter(fn (PelatihanPeserta $p): bool => $layanan->perbedaanIsian($p) !== [])->count())->toBe(3)
            ->and($menunggu->filter(fn (PelatihanPeserta $p): bool => $p->peserta->jabatan->memiliki_periode && $p->peserta->periode_jabatan)->count())->toBeGreaterThanOrEqual(2)
            ->and($menunggu->pluck('peserta.nama_lengkap'))->toContain('Coba Coba');
    });

    it('mengisi statistik dashboard dan audit log atas nama panitia', function () {
        $ringkasan = app(StatistikService::class)->ringkasan();
        $panitia = User::role(Peran::Admin->value)->orderBy('id')->pluck('id')->all();

        expect($ringkasan['desa_terlatih'])->toBeGreaterThan(0)
            ->and($ringkasan['peserta_terlatih'])->toBe(41)
            // Pendaftaran dari form publik tidak punya causer; semua aksi admin dibagi dua akun panitia.
            ->and(Activity::whereNotNull('causer_id')->distinct()->orderBy('causer_id')->pluck('causer_id')->all())->toBe($panitia)
            ->and(Activity::where('created_at', '>', now())->exists())->toBeFalse();
    });
});
