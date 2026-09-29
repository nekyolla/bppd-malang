<?php

use App\Livewire\Registrasi;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Provinsi;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Services\RegistrasiService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');

    $this->pelatihan = Pelatihan::factory()->dibuka()->create();
    $this->desa = Desa::factory()->create();
    $this->isian = fn (array $ubah = []): array => [
        'pelatihan_id' => $this->pelatihan->id,
        'nik' => '3507010101900001',
        'nama_lengkap' => 'Siti Aminah',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Malang',
        'tanggal_lahir' => '1990-01-01',
        'agama' => 'Islam',
        'alamat_domisili' => 'Jl. Raya Donomulyo 1',
        'no_hp' => '081234567890',
        'jenjang_pendidikan' => 'S1',
        'jabatan_id' => Jabatan::factory()->create()->id,
        'waktu_pelantikan' => '2022-03-01',
        'provinsi_id' => $this->desa->kecamatan->kabKota->provinsi_id,
        'kab_kota_id' => $this->desa->kecamatan->kab_kota_id,
        'kecamatan_id' => $this->desa->kecamatan_id,
        'desa_id' => $this->desa->id,
        'alamat_kantor_desa' => 'Jl. Balai Desa 2',
        'status_ptkp_id' => StatusPtkp::factory()->create()->id,
        'sumber_dana_id' => SumberDana::factory()->create()->id,
        'ktp' => UploadedFile::fake()->create('ktp.pdf', 500, 'application/pdf'),
        'foto' => UploadedFile::fake()->image('foto.jpg', 300, 400),
        'surat_tugas' => UploadedFile::fake()->create('surat.pdf', 500, 'application/pdf'),
        'pernyataan' => true,
        ...$ubah,
    ];
});

it('membuka form registrasi tanpa login, termasuk dari beranda', function () {
    $this->get('/')->assertRedirect('/daftar');

    $this->get('/daftar')
        ->assertOk()
        ->assertSee('Registrasi Peserta Pelatihan')
        ->assertSee($this->pelatihan->nama_tampilan);
});

it('memberi tahu jika belum ada pelatihan yang dibuka', function () {
    $this->pelatihan->forceFill(['status' => 'draft'])->save();

    $this->get('/daftar')->assertSee('Saat ini belum ada pelatihan yang membuka pendaftaran.');
});

it('hanya menawarkan pelatihan dibuka/berjalan dan data master aktif', function () {
    $berjalan = Pelatihan::factory()->berjalan()->create();
    Pelatihan::factory()->create();
    Pelatihan::factory()->selesai()->create();
    $jabatan = Jabatan::factory()->create();
    Jabatan::factory()->nonaktif()->create();
    $ptkp = StatusPtkp::factory()->create();
    StatusPtkp::factory()->nonaktif()->create();

    Livewire::test(Registrasi::class)
        ->assertFormFieldExists('pelatihan_id', fn (Radio $field): bool => array_keys($field->getOptions()) === [$this->pelatihan->id, $berjalan->id]
            || array_keys($field->getOptions()) === [$berjalan->id, $this->pelatihan->id])
        ->assertFormFieldExists('jabatan_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$jabatan->id])
        ->assertFormFieldExists('status_ptkp_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$ptkp->id]);
});

it('mengirim pendaftaran dan menampilkan konfirmasi tanpa data pribadi', function () {
    Livewire::test(Registrasi::class)
        ->fillForm(($this->isian)())
        ->call('daftar')
        ->assertHasNoFormErrors()
        ->assertSet('terkirim', true)
        ->assertSee('Terima kasih, pendaftaran Anda sudah kami terima.')
        ->assertDontSee('3507010101900001');

    $pendaftaran = PelatihanPeserta::sole();

    expect($pendaftaran->peserta->desa_id)->toBe($this->desa->id)
        ->and($pendaftaran->pelatihan_id)->toBe($this->pelatihan->id);

    Storage::disk('local')->assertExists("pendaftaran/{$pendaftaran->id}/surat-tugas.pdf");
});

it('menampilkan pesan NIK ganda di bawah isian NIK', function () {
    Livewire::test(Registrasi::class)->fillForm(($this->isian)())->call('daftar');

    Livewire::test(Registrasi::class)
        ->fillForm(($this->isian)())
        ->call('daftar')
        ->assertHasFormErrors(['nik' => RegistrasiService::PESAN_NIK_GANDA])
        ->assertSet('terkirim', false);

    expect(PelatihanPeserta::count())->toBe(1);
});

it('mewajibkan pernyataan kebenaran data', function () {
    Livewire::test(Registrasi::class)
        ->fillForm(($this->isian)(['pernyataan' => false]))
        ->call('daftar')
        ->assertHasFormErrors(['pernyataan' => 'accepted']);

    expect(PelatihanPeserta::count())->toBe(0);
});

it('menolak berkas lebih dari 2 MB di form', function () {
    Livewire::test(Registrasi::class)
        ->fillForm(($this->isian)(['ktp' => UploadedFile::fake()->create('ktp.pdf', 2500, 'application/pdf')]))
        ->call('daftar')
        ->assertHasFormErrors(['ktp']);
});

it('menampilkan keterangan sumber dana hanya untuk Lainnya', function () {
    $lainnya = SumberDana::factory()->butuhKeterangan()->create();

    Livewire::test(Registrasi::class)
        ->assertFormFieldHidden('sumber_dana_keterangan')
        ->fillForm(['sumber_dana_id' => $lainnya->id])
        ->assertFormFieldVisible('sumber_dana_keterangan')
        ->fillForm(($this->isian)(['sumber_dana_id' => $lainnya->id]))
        ->call('daftar')
        ->assertHasFormErrors(['sumber_dana_keterangan' => 'required']);
});

it('mengosongkan pilihan wilayah di bawahnya saat wilayah induk berubah', function () {
    $provinsiLain = Provinsi::factory()->create();

    Livewire::test(Registrasi::class)
        ->fillForm(($this->isian)())
        ->fillForm(['provinsi_id' => $provinsiLain->id])
        ->assertFormSet(['kab_kota_id' => null, 'kecamatan_id' => null, 'desa_id' => null]);
});

it('hanya menampilkan desa di kecamatan yang dipilih', function () {
    $desaLain = Desa::factory()->create();

    Livewire::test(Registrasi::class)
        ->fillForm(['kecamatan_id' => $this->desa->kecamatan_id])
        ->assertFormFieldExists('desa_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$this->desa->id]);

    expect($desaLain->kecamatan_id)->not->toBe($this->desa->kecamatan_id);
});
