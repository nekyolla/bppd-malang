<?php

namespace App\Support;

use App\Enums\JenisKelamin;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Kolom, normalisasi, dan aturan validasi data peserta yang dipakai bersama oleh
 * registrasi publik (RegistrasiService) dan verifikasi admin (PendaftaranService).
 */
class IsianPeserta
{
    /**
     * Kolom `peserta` yang diisi lewat form.
     */
    public const KOLOM = [
        'nik', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
        'alamat_domisili', 'no_hp', 'email', 'jenjang_pendidikan', 'jurusan_pendidikan',
        'jabatan_id', 'waktu_pelantikan', 'desa_id', 'alamat_kantor_desa', 'npwp', 'status_ptkp_id',
    ];

    public const LABEL = [
        'nik' => 'NIK',
        'nama_lengkap' => 'nama lengkap',
        'jenis_kelamin' => 'jenis kelamin',
        'tempat_lahir' => 'tempat lahir',
        'tanggal_lahir' => 'tanggal lahir',
        'agama' => 'agama',
        'alamat_domisili' => 'alamat domisili',
        'no_hp' => 'nomor HP',
        'email' => 'email',
        'jenjang_pendidikan' => 'jenjang pendidikan',
        'jurusan_pendidikan' => 'jurusan',
        'jabatan_id' => 'jabatan',
        'waktu_pelantikan' => 'tanggal pelantikan',
        'desa_id' => 'desa',
        'alamat_kantor_desa' => 'alamat kantor desa',
        'npwp' => 'NPWP',
        'status_ptkp_id' => 'status PTKP',
    ];

    /**
     * Merapikan isian: spasi di tepi, angka saja untuk NIK/HP/NPWP, email huruf kecil.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalisasi(array $data): array
    {
        $teks = fn (mixed $nilai): mixed => is_string($nilai) ? (trim($nilai) === '' ? null : trim($nilai)) : $nilai;
        $angka = fn (mixed $nilai): ?string => is_string($nilai) && filled($nilai) ? preg_replace('/\D/', '', $nilai) : null;

        foreach (['nama_lengkap', 'tempat_lahir', 'alamat_domisili', 'jurusan_pendidikan', 'alamat_kantor_desa'] as $kolom) {
            $data[$kolom] = $teks($data[$kolom] ?? null);
        }

        $data['nik'] = $angka($data['nik'] ?? null);
        $data['no_hp'] = $angka($data['no_hp'] ?? null);
        $data['npwp'] = $angka($data['npwp'] ?? null) ?: null;
        $data['email'] = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        $data['jenis_kelamin'] = ($data['jenis_kelamin'] ?? null) instanceof JenisKelamin ? $data['jenis_kelamin']->value : ($data['jenis_kelamin'] ?? null);

        return $data;
    }

    /**
     * @param  bool  $masterHarusAktif  Form publik hanya menerima jabatan/PTKP aktif; admin boleh memakai yang sudah dipakai peserta.
     * @param  Peserta|null  $nikUnikKecuali  Jika diisi, NIK tidak boleh dipakai peserta lain (koreksi NIK oleh admin).
     * @return array<string, array<int, mixed>>
     */
    public static function aturan(bool $masterHarusAktif = true, ?Peserta $nikUnikKecuali = null): array
    {
        $master = fn (string $tabel) => $masterHarusAktif
            ? Rule::exists($tabel, 'id')->where('is_aktif', true)
            : Rule::exists($tabel, 'id');

        return [
            'nik' => ['required', 'digits:16', ...($nikUnikKecuali ? [Rule::unique('peserta', 'nik')->ignore($nikUnikKecuali)] : [])],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before:-17 years'],
            'agama' => ['required', Rule::in(Peserta::AGAMA)],
            'alamat_domisili' => ['required', 'string', 'max:1000'],
            'no_hp' => ['required', 'regex:/^08\d{8,12}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'jenjang_pendidikan' => ['required', Rule::in(Peserta::JENJANG_PENDIDIKAN)],
            'jurusan_pendidikan' => ['nullable', 'string', 'max:255'],
            'jabatan_id' => ['required', 'integer', $master('jabatan')],
            'waktu_pelantikan' => ['required', 'date', 'before_or_equal:today', 'after:tanggal_lahir'],
            'desa_id' => ['required', 'integer', Rule::exists('desa', 'id')],
            'alamat_kantor_desa' => ['required', 'string', 'max:1000'],
            'npwp' => ['nullable', 'regex:/^(\d{15}|\d{16})$/'],
            'status_ptkp_id' => ['required', 'integer', $master('status_ptkp')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function pesan(): array
    {
        return [
            'nik.unique' => 'NIK ini sudah dipakai peserta lain.',
            'tanggal_lahir.before' => 'Peserta minimal berusia 17 tahun.',
            'no_hp.regex' => 'Nomor HP diawali 08 dan terdiri dari 10–14 angka.',
            'npwp.regex' => 'NPWP terdiri dari 15 atau 16 angka.',
            'waktu_pelantikan.after' => 'Tanggal pelantikan harus setelah tanggal lahir.',
            '*.exists' => 'Pilihan :attribute tidak tersedia.',
        ];
    }

    /**
     * Nilai kolom isian dari data peserta, dalam bentuk yang sama dengan `data_isian`.
     *
     * @return array<string, mixed>
     */
    public static function dariPeserta(Peserta $peserta): array
    {
        return collect(self::KOLOM)->mapWithKeys(fn (string $kolom): array => [$kolom => match (true) {
            $peserta->{$kolom} instanceof CarbonInterface => $peserta->{$kolom}->toDateString(),
            $peserta->{$kolom} instanceof JenisKelamin => $peserta->{$kolom}->value,
            default => $peserta->{$kolom},
        }])->all();
    }

    /**
     * Kolom yang isiannya berbeda: [kolom => [nilai lama, nilai baru]].
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function perbedaan(array $lama, array $baru): array
    {
        $sama = fn (mixed $a, mixed $b): bool => (string) ($a ?? '') === (string) ($b ?? '');

        return collect(self::KOLOM)
            ->reject(fn (string $kolom): bool => $sama($lama[$kolom] ?? null, $baru[$kolom] ?? null))
            ->mapWithKeys(fn (string $kolom): array => [$kolom => [$lama[$kolom] ?? null, $baru[$kolom] ?? null]])
            ->all();
    }

    /**
     * Nilai isian untuk ditampilkan ke admin (nama, bukan id).
     */
    public static function tampilkan(string $kolom, mixed $nilai): string
    {
        if (blank($nilai)) {
            return '–';
        }

        return match ($kolom) {
            'jenis_kelamin' => JenisKelamin::tryFrom((string) $nilai)?->getLabel() ?? (string) $nilai,
            'jabatan_id' => Jabatan::find($nilai)?->nama_jabatan ?? '–',
            'status_ptkp_id' => StatusPtkp::find($nilai)?->kode ?? '–',
            'desa_id' => ($desa = Desa::with('kecamatan.kabKota')->find($nilai))
                ? "{$desa->nama}, Kec. {$desa->kecamatan->nama}, {$desa->kecamatan->kabKota->nama}"
                : '–',
            'tanggal_lahir', 'waktu_pelantikan' => FormatTanggal::tanggal(Carbon::parse($nilai)),
            default => (string) $nilai,
        };
    }
}
