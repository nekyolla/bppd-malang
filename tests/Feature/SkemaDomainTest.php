<?php

use App\Models\Desa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function barisMaster(): array
{
    $kategori = DB::table('kategori_pelatihan')->insertGetId(['nama_kategori' => 'Teknis']);

    return [
        'jabatan' => DB::table('jabatan')->insertGetId(['nama_jabatan' => 'Sekretaris Desa']),
        'status_ptkp' => DB::table('status_ptkp')->insertGetId(['kode' => 'TK/0', 'nama' => 'Tidak kawin, tanpa tanggungan']),
        'sumber_dana' => DB::table('sumber_dana')->insertGetId(['nama' => 'APBN']),
        'judul_pelatihan' => DB::table('judul_pelatihan')->insertGetId([
            'kategori_pelatihan_id' => $kategori,
            'judul' => 'Pelatihan Pengelolaan Keuangan Desa',
        ]),
        'desa' => Desa::factory()->create()->id,
    ];
}

function barisPelatihan(array $master, array $ubah = []): int
{
    return DB::table('pelatihan')->insertGetId([
        'judul_pelatihan_id' => $master['judul_pelatihan'],
        'tahun_anggaran' => 2026,
        'batch_ke' => 1,
        'tanggal_mulai' => '2026-10-05',
        'tanggal_selesai' => '2026-10-08',
        'tipe_lokasi' => 'bbpd',
        ...$ubah,
    ]);
}

function barisPeserta(array $master, string $nik = '3507010101900001'): int
{
    return DB::table('peserta')->insertGetId([
        'nik' => $nik,
        'nama_lengkap' => 'Siti Aminah',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Malang',
        'tanggal_lahir' => '1990-01-01',
        'agama' => 'Islam',
        'alamat_domisili' => 'Jl. Raya Donomulyo 1',
        'no_hp' => '081234567890',
        'jenjang_pendidikan' => 'S1',
        'jabatan_id' => $master['jabatan'],
        'waktu_pelantikan' => '2022-03-01',
        'desa_id' => $master['desa'],
        'alamat_kantor_desa' => 'Jl. Balai Desa 2',
        'status_ptkp_id' => $master['status_ptkp'],
    ]);
}

function barisPendaftaran(array $master, int $pelatihan, int $peserta, array $ubah = []): int
{
    return DB::table('pelatihan_peserta')->insertGetId([
        'pelatihan_id' => $pelatihan,
        'peserta_id' => $peserta,
        'data_isian' => json_encode(['nama_lengkap' => 'Siti Aminah']),
        'sumber_dana_id' => $master['sumber_dana'],
        ...$ubah,
    ]);
}

beforeEach(function () {
    $this->master = barisMaster();
});

it('membuat seluruh tabel domain', function () {
    foreach (['jabatan', 'status_ptkp', 'sumber_dana', 'kategori_pelatihan', 'judul_pelatihan', 'asrama', 'kamar_asrama'] as $tabel) {
        expect(Schema::hasColumn($tabel, 'is_aktif'))->toBeTrue("{$tabel}.is_aktif");
    }

    expect(Schema::hasTable('user_profiles'))->toBeFalse()
        ->and(Schema::hasColumns('pelatihan_peserta', [
            'peserta_id', 'data_isian', 'jabatan_id_saat_pelatihan', 'desa_id_saat_pelatihan',
            'status_ptkp_id_saat_pelatihan', 'waktu_pelantikan_saat_pelatihan', 'menggantikan_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('peserta', 'sumber_dana_default_id'))->toBeFalse();

    foreach (['lembar_presensi', 'presensi', 'sertifikat_peserta', 'penggunaan_kamar', 'asrama_peserta'] as $tabel) {
        expect(Schema::hasTable($tabel))->toBeTrue($tabel);
    }
});

it('mengisi nilai bawaan status dan is_aktif', function () {
    $pelatihan = barisPelatihan($this->master);
    $pendaftaran = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master));

    expect(DB::table('jabatan')->value('is_aktif'))->toBe(1)
        ->and(DB::table('pelatihan')->find($pelatihan)->status)->toBe('draft')
        ->and(DB::table('pelatihan_peserta')->find($pendaftaran)->status)->toBe('terdaftar');
});

it('menolak NIK peserta yang sama', function () {
    barisPeserta($this->master);

    expect(fn () => barisPeserta($this->master))->toThrow(QueryException::class);
});

it('menolak kombinasi judul, tahun, dan batch yang sama', function () {
    barisPelatihan($this->master);

    expect(fn () => barisPelatihan($this->master))->toThrow(QueryException::class);

    barisPelatihan($this->master, ['batch_ke' => 2]);
    barisPelatihan($this->master, ['tahun_anggaran' => 2027]);

    expect(DB::table('pelatihan')->count())->toBe(3);
});

it('menolak tanggal selesai sebelum tanggal mulai', function () {
    expect(fn () => barisPelatihan($this->master, [
        'tanggal_mulai' => '2026-10-08',
        'tanggal_selesai' => '2026-10-05',
    ]))->toThrow(QueryException::class);

    barisPelatihan($this->master, ['tanggal_mulai' => '2026-10-05', 'tanggal_selesai' => '2026-10-05']);

    expect(DB::table('pelatihan')->count())->toBe(1);
});

it('menolak nama kelas ganda di pelatihan yang sama', function () {
    $pelatihan = barisPelatihan($this->master);
    DB::table('kelas_pelatihan')->insert(['pelatihan_id' => $pelatihan, 'nama_kelas' => 'A']);

    expect(fn () => DB::table('kelas_pelatihan')->insert(['pelatihan_id' => $pelatihan, 'nama_kelas' => 'A']))
        ->toThrow(QueryException::class);
});

it('menolak nomor kamar ganda di asrama yang sama', function () {
    $asrama = DB::table('asrama')->insertGetId(['nama_asrama' => 'Anggrek']);
    DB::table('kamar_asrama')->insert(['asrama_id' => $asrama, 'no_kamar' => '01', 'kapasitas' => 4]);

    expect(fn () => DB::table('kamar_asrama')->insert(['asrama_id' => $asrama, 'no_kamar' => '01', 'kapasitas' => 2]))
        ->toThrow(QueryException::class);
});

it('menolak peserta terdaftar dua kali di pelatihan yang sama', function () {
    $pelatihan = barisPelatihan($this->master);
    $peserta = barisPeserta($this->master);
    barisPendaftaran($this->master, $pelatihan, $peserta);

    expect(fn () => barisPendaftaran($this->master, $pelatihan, $peserta))->toThrow(QueryException::class);

    barisPendaftaran($this->master, barisPelatihan($this->master, ['batch_ke' => 2]), $peserta);

    expect(DB::table('pelatihan_peserta')->count())->toBe(2);
});

it('hanya mengizinkan satu pengganti untuk satu peserta batal', function () {
    $pelatihan = barisPelatihan($this->master);
    $batal = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master), ['status' => 'batal']);
    barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master, '3507010101900002'), ['menggantikan_id' => $batal]);

    expect(fn () => barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master, '3507010101900003'), ['menggantikan_id' => $batal]))
        ->toThrow(QueryException::class);
});

it('mencegah data master yang dipakai terhapus', function () {
    barisPeserta($this->master);

    expect(fn () => DB::table('jabatan')->where('id', $this->master['jabatan'])->delete())
        ->toThrow(QueryException::class);
});

it('mencatat satu lembar presensi per kelas per tanggal dan satu status per peserta', function () {
    $admin = User::factory()->internal()->create()->id;
    $pelatihan = barisPelatihan($this->master);
    $kelas = DB::table('kelas_pelatihan')->insertGetId(['pelatihan_id' => $pelatihan, 'nama_kelas' => 'A']);
    $pendaftaran = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master));
    $lembar = DB::table('lembar_presensi')->insertGetId(['kelas_pelatihan_id' => $kelas, 'tanggal' => '2026-10-05', 'diinput_oleh' => $admin]);
    DB::table('presensi')->insert(['lembar_presensi_id' => $lembar, 'pelatihan_peserta_id' => $pendaftaran, 'status' => 'hadir']);

    expect(fn () => DB::table('lembar_presensi')->insert(['kelas_pelatihan_id' => $kelas, 'tanggal' => '2026-10-05', 'diinput_oleh' => $admin]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('presensi')->insert(['lembar_presensi_id' => $lembar, 'pelatihan_peserta_id' => $pendaftaran, 'status' => 'alpa']))
        ->toThrow(QueryException::class);
});

it('mempertahankan presensi saat pendaftaran dibatalkan', function () {
    $admin = User::factory()->internal()->create()->id;
    $pelatihan = barisPelatihan($this->master);
    $kelas = DB::table('kelas_pelatihan')->insertGetId(['pelatihan_id' => $pelatihan, 'nama_kelas' => 'A']);
    $pendaftaran = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master));
    $lembar = DB::table('lembar_presensi')->insertGetId(['kelas_pelatihan_id' => $kelas, 'tanggal' => '2026-10-05', 'diinput_oleh' => $admin]);
    DB::table('presensi')->insert(['lembar_presensi_id' => $lembar, 'pelatihan_peserta_id' => $pendaftaran, 'status' => 'hadir']);

    DB::table('pelatihan_peserta')->where('id', $pendaftaran)->update(['status' => 'batal']);

    expect(DB::table('presensi')->count())->toBe(1)
        ->and(fn () => DB::table('pelatihan_peserta')->where('id', $pendaftaran)->delete())
        ->toThrow(QueryException::class);
});

it('menerbitkan satu sertifikat per pendaftaran dengan nomor unik', function () {
    $admin = User::factory()->internal()->create()->id;
    $pelatihan = barisPelatihan($this->master);
    $pertama = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master));
    $kedua = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master, '3507010101900002'));
    $sertifikat = fn (int $pendaftaran, string $nomor) => DB::table('sertifikat_peserta')->insert([
        'pelatihan_peserta_id' => $pendaftaran,
        'nomor_sertifikat' => $nomor,
        'tanggal_terbit' => '2026-10-08',
        'file_sertifikat' => "sertifikat/{$pelatihan}/{$pendaftaran}.pdf",
        'diupload_oleh' => $admin,
    ]);

    $sertifikat($pertama, 'BBPD/001');

    expect(fn () => $sertifikat($pertama, 'BBPD/002'))->toThrow(QueryException::class)
        ->and(fn () => $sertifikat($kedua, 'BBPD/001'))->toThrow(QueryException::class);
});

it('mencatat satu penggunaan per kamar per pelatihan dan satu kamar per peserta', function () {
    $admin = User::factory()->internal()->create()->id;
    $pelatihan = barisPelatihan($this->master);
    $asrama = DB::table('asrama')->insertGetId(['nama_asrama' => 'Anggrek']);
    $kamar = DB::table('kamar_asrama')->insertGetId(['asrama_id' => $asrama, 'no_kamar' => '01', 'kapasitas' => 4]);
    $kamarLain = DB::table('kamar_asrama')->insertGetId(['asrama_id' => $asrama, 'no_kamar' => '02', 'kapasitas' => 4]);
    $penggunaan = DB::table('penggunaan_kamar')->insertGetId(['pelatihan_id' => $pelatihan, 'kamar_asrama_id' => $kamar, 'tipe' => 'P']);
    $penggunaanLain = DB::table('penggunaan_kamar')->insertGetId(['pelatihan_id' => $pelatihan, 'kamar_asrama_id' => $kamarLain, 'tipe' => 'P']);
    $pendaftaran = barisPendaftaran($this->master, $pelatihan, barisPeserta($this->master));
    DB::table('asrama_peserta')->insert(['pelatihan_peserta_id' => $pendaftaran, 'penggunaan_kamar_id' => $penggunaan, 'ditempatkan_oleh' => $admin]);

    expect(fn () => DB::table('penggunaan_kamar')->insert(['pelatihan_id' => $pelatihan, 'kamar_asrama_id' => $kamar, 'tipe' => 'L']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('asrama_peserta')->insert(['pelatihan_peserta_id' => $pendaftaran, 'penggunaan_kamar_id' => $penggunaanLain, 'ditempatkan_oleh' => $admin]))
        ->toThrow(QueryException::class);
});

it('mewajibkan persetujuan untuk kamar pasutri', function () {
    $admin = User::factory()->internal()->create()->id;
    $pelatihan = barisPelatihan($this->master);
    $asrama = DB::table('asrama')->insertGetId(['nama_asrama' => 'Anggrek']);
    $kamar = DB::table('kamar_asrama')->insertGetId(['asrama_id' => $asrama, 'no_kamar' => '01', 'kapasitas' => 2]);

    expect(fn () => DB::table('penggunaan_kamar')->insert(['pelatihan_id' => $pelatihan, 'kamar_asrama_id' => $kamar, 'tipe' => 'PASUTRI']))
        ->toThrow(QueryException::class);

    DB::table('penggunaan_kamar')->insert(['pelatihan_id' => $pelatihan, 'kamar_asrama_id' => $kamar, 'tipe' => 'PASUTRI', 'disetujui_oleh' => $admin]);

    expect(DB::table('penggunaan_kamar')->count())->toBe(1);
});
