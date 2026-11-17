<?php

use App\Enums\JenisKelamin;
use App\Models\Jabatan;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SertifikatPeserta;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Models\User;
use App\Services\ExportService;
use App\Support\Instansi;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->service = app(ExportService::class);
    $this->pelatihan = Pelatihan::factory()->dibuka()->tanggal('2026-11-02', '2026-11-04')->create();
    $this->kelas = KelasPelatihan::factory()->for($this->pelatihan)->create(['nama_kelas' => 'A']);
});

/**
 * @return array<string, list<string>>
 */
function galatExport(Closure $aksi): array
{
    try {
        $aksi();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('menyusun satu lembar per hari terpilih dengan kolom L/P dan tanda tangan selang-seling', function () {
    $budi = PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Budi', 'jenis_kelamin' => JenisKelamin::LakiLaki]))
        ->create(['kelas_pelatihan_id' => $this->kelas->id]);
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Ani', 'jenis_kelamin' => JenisKelamin::Perempuan]))
        ->create(['kelas_pelatihan_id' => $this->kelas->id]);
    PelatihanPeserta::factory()->batal()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelas->id]);

    $lembar = $this->service->daftarHadirKelas($this->kelas, ['2026-11-04', '2026-11-02', '2026-11-02']);
    $senin = $lembar[0];

    expect($lembar)->toHaveCount(2)
        ->and(array_column($lembar, 'namaLembar'))->toBe(['Sen 2 Nov', 'Rab 4 Nov'])
        ->and(array_column($lembar, 'tanggal'))->toBe(['2026-11-02', '2026-11-04'])
        ->and($senin['keterangan'][1])->toStartWith('Kelas A')
        ->and($senin['keterangan'][2])->toBe('Hari/tanggal: Senin, 2 November 2026')
        ->and($senin['kolom'])->toBe(['No', 'Nama', 'L/P', 'Jabatan', 'Desa', 'Tanda tangan', ''])
        ->and($senin['kolomTtd'])->toBe(2)
        ->and(array_column($senin['baris'], 1))->toBe(['Ani', 'Budi'])
        ->and($senin['baris'][0][2])->toBe('P')
        ->and($senin['baris'][1][2])->toBe('L')
        ->and($senin['baris'][1][4])->toBe("{$budi->desaSaatPelatihan->nama}, {$budi->desaSaatPelatihan->kecamatan->kabKota->nama}")
        ->and(array_slice($senin['baris'][0], 5))->toBe(['1.', ''])
        ->and(array_slice($senin['baris'][1], 5))->toBe(['', '2.'])
        ->and(json_encode($lembar))->not->toContain($budi->peserta->nik);
});

it('menolak tanggal di luar jadwal atau pilihan kosong', function () {
    expect(galatExport(fn () => $this->service->daftarHadirKelas($this->kelas, ['2026-11-05']))['tanggal'][0])
        ->toBe('Tanggal 5 November 2026 di luar jadwal pelatihan.')
        ->and(galatExport(fn () => $this->service->daftarHadirKelas($this->kelas, []))['tanggal'][0])
        ->toBe('Pilih minimal satu hari.');
});

it('menolak tanggal rusak tanpa galat server', function (mixed $nilai) {
    expect(galatExport(fn () => $this->service->daftarHadirKelas($this->kelas, ['2026-11-02', $nilai]))['tanggal'][0])
        ->toBe('Tanggal tidak valid.');
})->with([
    'bukan tanggal' => 'kemarin',
    'tanggal tidak ada' => '2026-11-31',
    'format lain' => '02/11/2026',
    'dengan jam' => '2026-11-02 10:00',
    'bukan string' => [['2026-11-02']],
    'angka' => 20261102,
]);

it('tetap membuat lembar untuk kelas tanpa peserta aktif', function () {
    $lembar = $this->service->daftarHadirKelas($this->kelas, ['2026-11-02', '2026-11-03']);

    expect($lembar)->toHaveCount(2)
        ->and($lembar[0]['baris'])->toBe([])
        ->and($lembar[1]['baris'])->toBe([]);
});

it('menawarkan pilihan hari sepanjang pelatihan', function () {
    expect($this->service->pilihanHari($this->pelatihan))->toBe([
        '2026-11-02' => 'Sen, 2 Nov',
        '2026-11-03' => 'Sel, 3 Nov',
        '2026-11-04' => 'Rab, 4 Nov',
    ]);
});

it('mencetak PDF tepat satu halaman per hari tanpa halaman kosong', function () {
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create(['kelas_pelatihan_id' => $this->kelas->id]);
    $this->actingAs(User::factory()->create());

    $pdf = $this->service->unduhPdf($this->service->daftarHadirKelas($this->kelas, ['2026-11-02', '2026-11-03']), 'uji', $this->kelas, User::factory()->create());

    ob_start();
    $pdf->sendContent();
    $isi = ob_get_clean();

    expect($isi)->toContain('/Count 2')->not->toContain('/Count 3');
});

it('memasang kop surat balai di setiap lembar daftar hadir PDF (PF-39)', function () {
    $lembar = $this->service->daftarHadirKelas($this->kelas, ['2026-11-02', '2026-11-03']);
    $html = view('export.tabel', ['lembar' => $lembar, 'dicetak' => '2 November 2026, 08:00'])->render();

    expect(substr_count($html, 'class="kop"'))->toBe(2)
        ->and($html)->toContain('KEMENTERIAN DALAM NEGERI', 'REPUBLIK INDONESIA', 'DIREKTORAT JENDERAL BINA PEMERINTAHAN DESA', 'BALAI BESAR PENGEMBANGAN KAPASITAS PEMERINTAHAN DESA')
        ->and($html)->toContain(Instansi::ALAMAT, Instansi::KONTAK)
        ->and($html)->toContain('src="data:image/png;base64,');

    ob_start();
    $this->service->unduhPdf($lembar, 'uji', $this->kelas, User::factory()->create())->sendContent();

    expect(ob_get_clean())->toContain('/Subtype /Image')->toContain('/Count 2');
});

it('menyusun data peserta per pelatihan dengan NIK lengkap dan snapshot jabatan (PF-37)', function () {
    $peserta = Peserta::factory()->create([
        'nama_lengkap' => 'Budi Santoso',
        'nik' => '3507123456780001',
        'tempat_lahir' => 'Malang',
        'tanggal_lahir' => '1980-11-03',
        'jenis_kelamin' => JenisKelamin::LakiLaki,
        'agama' => 'Islam',
        'alamat_domisili' => 'Jl. Melati 1',
        'alamat_kantor_desa' => 'Jl. Balai Desa 2',
        'jabatan_id' => Jabatan::factory()->create(['nama_jabatan' => 'Sekretaris Desa'])->id,
        'periode_jabatan' => null,
        'waktu_pelantikan' => '2020-01-15',
        'jenjang_pendidikan' => 'S1',
        'jurusan_pendidikan' => 'Akuntansi',
        'no_hp' => '081234567890',
        'status_ptkp_id' => StatusPtkp::factory()->create(['kode' => 'K/1'])->id,
    ]);
    $budi = PelatihanPeserta::factory()->selesai()->for($this->pelatihan)->for($peserta)->create([
        'sumber_dana_id' => SumberDana::factory()->create(['nama' => 'Lainnya'])->id,
        'sumber_dana_keterangan' => 'Dana desa',
    ]);
    SertifikatPeserta::factory()->for($budi, 'pelatihanPeserta')->create(['nomor_sertifikat' => '001/BBPKPD/2026']);
    // Profil terkini berubah setelah pelatihan; export membaca snapshot.
    $peserta->update(['jabatan_id' => Jabatan::factory()->create(['nama_jabatan' => 'Kepala Dusun'])->id]);

    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)
        ->for(Peserta::factory()->state(['nama_lengkap' => 'Ani']))->create();
    PelatihanPeserta::factory()->for($this->pelatihan)->for(Peserta::factory()->state(['nama_lengkap' => 'Menunggu']))->create();
    PelatihanPeserta::factory()->batal()->for($this->pelatihan)->for(Peserta::factory()->state(['nama_lengkap' => 'Batal']))->create();

    $lembar = $this->service->dataPeserta($this->pelatihan);

    expect($lembar['kolom'])->toBe([
        'No', 'Tahun', 'Waktu pelaksanaan', 'Nama lengkap', 'NIK', 'Tempat, tanggal lahir', 'Jenis kelamin', 'Usia',
        'Agama', 'Status PTKP', 'Alamat domisili', 'Alamat kantor desa', 'Jabatan', 'Waktu pelantikan', 'Tahun menjabat',
        'Pendidikan terakhir', 'Nomor sertifikat', 'Nomor telepon', 'Sumber dana',
    ])
        ->and($lembar['kolomTtd'])->toBe(0)
        ->and(array_column($lembar['baris'], 3))->toBe(['Ani', 'Budi Santoso'])
        ->and($lembar['baris'][1])->toBe([
            2, 2026, '2–4 November 2026', 'Budi Santoso', '3507123456780001', 'Malang, 3 November 1980', 'Laki-laki', 45,
            'Islam', 'K/1', 'Jl. Melati 1', 'Jl. Balai Desa 2', 'Sekretaris Desa', '15 Januari 2020', 7,
            'S1 Akuntansi', '001/BBPKPD/2026', '081234567890', 'Lainnya (Dana desa)',
        ])
        ->and($lembar['baris'][0][16])->toBe('');
});

it('mengunduh data peserta sebagai Excel dan mencatatnya di audit log (PF-37)', function () {
    PelatihanPeserta::factory()->terverifikasi()->for($this->pelatihan)->create();
    $admin = User::factory()->create();

    // Pelaku diterima sebagai parameter, tidak diambil dari sesi login.
    $respons = $this->service->unduhExcel([$this->service->dataPeserta($this->pelatihan)], 'data-peserta', $this->pelatihan, $admin);

    expect($respons->headers->get('Content-Disposition'))->toContain('data-peserta.xlsx')
        ->and(Activity::query()->where('log_name', 'unduhan')->sole())
        ->subject->is($this->pelatihan)->toBeTrue()
        ->causer->is($admin)->toBeTrue()
        ->description->toBe('Mengunduh data-peserta.xlsx');
});
