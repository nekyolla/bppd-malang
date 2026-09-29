<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\StatusPresensi;
use App\Enums\TipeKamar;
use App\Enums\TipeLokasi;
use App\Models\Asrama;
use App\Models\AsramaPeserta;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KamarAsrama;
use App\Models\KategoriPelatihan;
use App\Models\KelasPelatihan;
use App\Models\LembarPresensi;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\SertifikatPeserta;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->jabatan = Jabatan::create(['nama_jabatan' => 'Sekretaris Desa']);
    $this->ptkp = StatusPtkp::create(['kode' => 'K/1', 'nama' => 'Kawin, 1 tanggungan']);
    $this->sumberDana = SumberDana::create(['nama' => 'APBN']);
    $this->desa = Desa::factory()->create();
    $this->judul = JudulPelatihan::create([
        'kategori_pelatihan_id' => KategoriPelatihan::create(['nama_kategori' => 'Teknis'])->id,
        'judul' => 'Pelatihan Pengelolaan Keuangan Desa',
    ]);

    $this->pelatihan = Pelatihan::create([
        'judul_pelatihan_id' => $this->judul->id,
        'tahun_anggaran' => 2026,
        'batch_ke' => 1,
        'tanggal_mulai' => '2026-10-05',
        'tanggal_selesai' => '2026-10-08',
        'tipe_lokasi' => TipeLokasi::Bbpd,
    ]);

    $this->buatPeserta = fn (string $nik, JenisKelamin $jk = JenisKelamin::Perempuan) => Peserta::create([
        'nik' => $nik,
        'nama_lengkap' => 'Peserta '.$nik,
        'jenis_kelamin' => $jk,
        'tempat_lahir' => 'Malang',
        'tanggal_lahir' => '1990-01-01',
        'agama' => 'Islam',
        'alamat_domisili' => 'Jl. Raya 1',
        'no_hp' => '081234567890',
        'jenjang_pendidikan' => 'S1',
        'jabatan_id' => $this->jabatan->id,
        'waktu_pelantikan' => '2022-03-01',
        'desa_id' => $this->desa->id,
        'alamat_kantor_desa' => 'Jl. Balai Desa 2',
        'status_ptkp_id' => $this->ptkp->id,
    ]);

    $this->daftarkan = fn (Peserta $peserta) => PelatihanPeserta::create([
        'pelatihan_id' => $this->pelatihan->id,
        'peserta_id' => $peserta->id,
        'data_isian' => ['nama_lengkap' => $peserta->nama_lengkap],
        'sumber_dana_id' => $this->sumberDana->id,
    ]);

    $this->peserta = ($this->buatPeserta)('3507010101900001');
    $this->pendaftaran = ($this->daftarkan)($this->peserta);
});

describe('nilai turunan pelatihan', function () {
    it('merangkai nama tampilan dari judul, tahun, dan batch', function () {
        expect($this->pelatihan->nama_tampilan)->toBe('Pelatihan Pengelolaan Keuangan Desa 2026 Batch 1');
    });

    it('menghitung jumlah hari dan JP', function (string $mulai, string $selesai, int $hari, int $jp) {
        $pelatihan = new Pelatihan(['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);

        expect($pelatihan->jumlah_hari)->toBe($hari)
            ->and($pelatihan->jumlah_jp)->toBe($jp);
    })->with([
        'satu hari' => ['2026-10-05', '2026-10-05', 1, 10],
        'empat hari' => ['2026-10-05', '2026-10-08', 4, 40],
        'lintas bulan' => ['2026-10-30', '2026-11-02', 4, 40],
    ]);

    it('tidak menyimpan nilai turunan di database', function () {
        expect(array_keys($this->pelatihan->fresh()->getAttributes()))
            ->not->toContain('nama_tampilan', 'jumlah_hari', 'jumlah_jp');
    });
});

describe('tahun menjabat', function () {
    it('dihitung per tahun kalender dari tahun mulai pelatihan', function () {
        expect($this->pendaftaran->tahun_menjabat)->toBe(5)
            ->and($this->peserta->tahunMenjabatPada(2022))->toBe(1);
    });

    it('memakai snapshot pelantikan setelah verifikasi walau peserta dilantik ulang', function () {
        $this->pendaftaran->forceFill(['waktu_pelantikan_saat_pelatihan' => '2022-03-01'])->save();
        $this->peserta->update(['waktu_pelantikan' => '2028-01-10']);

        $pendaftaran = $this->pendaftaran->fresh();

        expect($pendaftaran->tahun_menjabat)->toBe(5)
            ->and($pendaftaran->waktu_pelantikan_saat_pelatihan->toDateString())->toBe('2022-03-01');
    });

    it('memakai tahun berjalan di data peserta', function () {
        Carbon::setTestNow('2027-01-15');

        expect($this->peserta->tahun_menjabat)->toBe(6);
    });
});

it('menghitung jumlah hadir langsung maupun lewat withCount', function () {
    $kelas = KelasPelatihan::create(['pelatihan_id' => $this->pelatihan->id, 'nama_kelas' => 'A']);

    foreach (['2026-10-05' => StatusPresensi::Hadir, '2026-10-06' => StatusPresensi::Sakit, '2026-10-07' => StatusPresensi::Hadir] as $tanggal => $status) {
        $lembar = LembarPresensi::create(['kelas_pelatihan_id' => $kelas->id, 'tanggal' => $tanggal, 'diinput_oleh' => $this->admin->id]);
        Presensi::create(['lembar_presensi_id' => $lembar->id, 'pelatihan_peserta_id' => $this->pendaftaran->id, 'status' => $status]);
    }

    expect($this->pendaftaran->jumlah_hadir)->toBe(2)
        ->and(PelatihanPeserta::denganJumlahHadir()->find($this->pendaftaran->id)->jumlah_hadir)->toBe(2);
});

it('menghitung jumlah penghuni kamar langsung maupun lewat withCount', function () {
    $kamar = KamarAsrama::create([
        'asrama_id' => Asrama::create(['nama_asrama' => 'Anggrek'])->id,
        'no_kamar' => '01',
        'kapasitas' => 4,
    ]);
    $penggunaan = new PenggunaanKamar(['pelatihan_id' => $this->pelatihan->id, 'kamar_asrama_id' => $kamar->id]);
    $penggunaan->tipe = TipeKamar::Perempuan;
    $penggunaan->save();

    foreach ([$this->pendaftaran, ($this->daftarkan)(($this->buatPeserta)('3507010101900002'))] as $pendaftaran) {
        AsramaPeserta::create([
            'pelatihan_peserta_id' => $pendaftaran->id,
            'penggunaan_kamar_id' => $penggunaan->id,
            'ditempatkan_oleh' => $this->admin->id,
        ]);
    }

    expect($penggunaan->jumlah_penghuni)->toBe(2)
        ->and(PenggunaanKamar::denganJumlahPenghuni()->find($penggunaan->id)->jumlah_penghuni)->toBe(2)
        ->and($this->pendaftaran->penempatanKamar->penggunaanKamar->kamarAsrama->asrama->nama_asrama)->toBe('Anggrek');
});

it('menyaring data master aktif', function () {
    Jabatan::create(['nama_jabatan' => 'Kaur Keuangan', 'is_aktif' => false]);

    expect(Jabatan::aktif()->pluck('nama_jabatan')->all())->toBe(['Sekretaris Desa'])
        ->and(Jabatan::count())->toBe(2)
        ->and($this->jabatan->fresh()->is_aktif)->toBeTrue();
});

it('menampilkan pelatihan dibuka dan berjalan di form registrasi', function () {
    $status = [StatusPelatihan::Dibuka, StatusPelatihan::Berjalan, StatusPelatihan::Selesai];

    foreach ($status as $i => $s) {
        $pelatihan = Pelatihan::create([...$this->pelatihan->only(['judul_pelatihan_id', 'tahun_anggaran', 'tanggal_mulai', 'tanggal_selesai', 'tipe_lokasi']), 'batch_ke' => $i + 2]);
        $pelatihan->status = $s;
        $pelatihan->save();
    }

    expect(Pelatihan::menerimaPendaftaran()->orderBy('batch_ke')->pluck('batch_ke')->all())->toBe([2, 3]);
});

it('mengabaikan status, snapshot, dan pengganti dari mass assignment', function () {
    $pelatihan = Pelatihan::create([
        ...$this->pelatihan->only(['judul_pelatihan_id', 'tahun_anggaran', 'tanggal_mulai', 'tanggal_selesai', 'tipe_lokasi']),
        'batch_ke' => 9,
        'status' => StatusPelatihan::Selesai,
    ]);

    $this->pendaftaran->update([
        'status' => StatusPendaftaran::Selesai,
        'desa_id_saat_pelatihan' => $this->desa->id,
        'waktu_pelantikan_saat_pelatihan' => '2020-01-01',
        'menggantikan_id' => $this->pendaftaran->id,
    ]);
    $pendaftaran = $this->pendaftaran->fresh();

    expect($pelatihan->fresh()->status)->toBe(StatusPelatihan::Draft)
        ->and($pendaftaran->status)->toBe(StatusPendaftaran::Terdaftar)
        ->and($pendaftaran->desa_id_saat_pelatihan)->toBeNull()
        ->and($pendaftaran->waktu_pelantikan_saat_pelatihan)->toBeNull()
        ->and($pendaftaran->menggantikan_id)->toBeNull();
});

it('menghubungkan pendaftaran dengan snapshot, pengganti, dan sertifikat', function () {
    $batal = $this->pendaftaran;
    $batal->forceFill([
        'status' => StatusPendaftaran::Batal,
        'jabatan_id_saat_pelatihan' => $this->jabatan->id,
        'desa_id_saat_pelatihan' => $this->desa->id,
        'status_ptkp_id_saat_pelatihan' => $this->ptkp->id,
        'dibatalkan_oleh' => $this->admin->id,
        'dibatalkan_pada' => now(),
    ])->save();

    $pengganti = ($this->daftarkan)(($this->buatPeserta)('3507010101900002', JenisKelamin::LakiLaki));
    $pengganti->forceFill(['menggantikan_id' => $batal->id])->save();

    SertifikatPeserta::create([
        'pelatihan_peserta_id' => $pengganti->id,
        'nomor_sertifikat' => 'BBPD/001',
        'tanggal_terbit' => '2026-10-08',
        'file_sertifikat' => 'sertifikat/1/2.pdf',
        'diupload_oleh' => $this->admin->id,
    ]);

    $batal->refresh();

    expect($batal->pengganti->is($pengganti))->toBeTrue()
        ->and($pengganti->menggantikan->is($batal))->toBeTrue()
        ->and($batal->jabatanSaatPelatihan->is($this->jabatan))->toBeTrue()
        ->and($batal->desaSaatPelatihan->is($this->desa))->toBeTrue()
        ->and($batal->statusPtkpSaatPelatihan->is($this->ptkp))->toBeTrue()
        ->and($batal->dibatalkanOleh->is($this->admin))->toBeTrue()
        ->and($pengganti->sertifikat->nomor_sertifikat)->toBe('BBPD/001')
        ->and($this->peserta->pendaftaran()->count())->toBe(1)
        ->and($this->pelatihan->pendaftaran()->count())->toBe(2)
        ->and($this->pelatihan->judulPelatihan->kategoriPelatihan->nama_kategori)->toBe('Teknis');
});

it('mencatat perubahan pendaftaran ke audit log beserta nilai lama', function () {
    $this->actingAs($this->admin);

    $this->pendaftaran->forceFill(['status' => StatusPendaftaran::Terverifikasi])->save();

    $log = Activity::query()->where('subject_type', $this->pendaftaran->getMorphClass())->latest('id')->first();

    expect($log->event)->toBe('updated')
        ->and($log->causer->is($this->admin))->toBeTrue()
        ->and($log->attribute_changes['old']['status'])->toBe('terdaftar')
        ->and($log->attribute_changes['attributes']['status'])->toBe('terverifikasi');
});
