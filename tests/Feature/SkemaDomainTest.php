<?php

use App\Models\Desa;
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

it('membuat seluruh tabel master, peserta, pelatihan, dan pendaftaran', function () {
    foreach (['jabatan', 'status_ptkp', 'sumber_dana', 'kategori_pelatihan', 'judul_pelatihan', 'asrama', 'kamar_asrama'] as $tabel) {
        expect(Schema::hasColumn($tabel, 'is_aktif'))->toBeTrue("{$tabel}.is_aktif");
    }

    expect(Schema::hasTable('user_profiles'))->toBeFalse()
        ->and(Schema::hasColumns('pelatihan_peserta', [
            'peserta_id', 'data_isian', 'jabatan_id_saat_pelatihan', 'desa_id_saat_pelatihan',
            'status_ptkp_id_saat_pelatihan', 'menggantikan_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('peserta', 'sumber_dana_default_id'))->toBeFalse();
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
