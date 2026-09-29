<?php

namespace App\Services;

use App\Enums\JenisKelamin;
use App\Enums\StatusPelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SumberDana;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registrasi peserta dari form publik tanpa login (PRD §8.2, ARCHITECTURE §7.0).
 *
 * NIK baru membuat baris `peserta`. NIK lama TIDAK mengubah `peserta`: isian disimpan
 * di `data_isian` dan diterapkan admin saat verifikasi (CLAUDE.md aturan 12).
 */
class RegistrasiService
{
    public const DISK = 'local';

    /**
     * Batas ukuran semua berkas dalam KB (PF-08).
     */
    public const MAKS_UKURAN_BERKAS_KB = 2048;

    /**
     * Kolom `peserta` yang diisi dari form.
     */
    public const KOLOM_PESERTA = [
        'nik', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
        'alamat_domisili', 'no_hp', 'email', 'jenjang_pendidikan', 'jurusan_pendidikan',
        'jabatan_id', 'waktu_pelantikan', 'desa_id', 'alamat_kantor_desa', 'npwp', 'status_ptkp_id',
    ];

    /**
     * Nama isian berkas => nama file tersimpan.
     */
    public const BERKAS = [
        'ktp' => 'ktp.pdf',
        'foto' => 'foto.jpg',
        'surat_tugas' => 'surat-tugas.pdf',
    ];

    public const PESAN_NIK_GANDA = 'NIK ini sudah terdaftar di pelatihan tersebut. Hubungi panitia jika ada data yang perlu diperbaiki.';

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function daftar(array $data): PelatihanPeserta
    {
        $data = $this->validasi($this->normalisasi($data));
        $folder = null;

        try {
            return DB::transaction(function () use ($data, &$folder): PelatihanPeserta {
                $isianPeserta = Arr::only($data, self::KOLOM_PESERTA);
                $peserta = Peserta::query()->where('nik', $data['nik'])->lockForUpdate()->first();
                $pesertaBaru = $peserta === null;

                if (! $pesertaBaru && $peserta->pendaftaran()->where('pelatihan_id', $data['pelatihan_id'])->exists()) {
                    throw ValidationException::withMessages(['nik' => self::PESAN_NIK_GANDA]);
                }

                $peserta ??= Peserta::create($isianPeserta);

                $pendaftaran = PelatihanPeserta::create([
                    'pelatihan_id' => $data['pelatihan_id'],
                    'peserta_id' => $peserta->id,
                    'data_isian' => $isianPeserta,
                    'sumber_dana_id' => $data['sumber_dana_id'],
                    'sumber_dana_keterangan' => $data['sumber_dana_keterangan'],
                ]);

                $folder = "pendaftaran/{$pendaftaran->id}";
                $path = [];

                foreach (self::BERKAS as $isian => $namaFile) {
                    $path[$isian] = Storage::disk(self::DISK)->putFileAs($folder, $data[$isian], $namaFile);
                }

                $pendaftaran->update([
                    'file_surat_tugas' => $path['surat_tugas'],
                    'data_isian' => [...$isianPeserta, 'file_ktp' => $path['ktp'], 'foto' => $path['foto']],
                ]);

                if ($pesertaBaru) {
                    $peserta->update(['file_ktp' => $path['ktp'], 'foto' => $path['foto']]);
                }

                return $pendaftaran;
            });
        } catch (UniqueConstraintViolationException) {
            $this->hapusFolder($folder);

            // Dua kiriman bersamaan dengan NIK yang sama.
            throw ValidationException::withMessages(['nik' => self::PESAN_NIK_GANDA]);
        } catch (Throwable $exception) {
            $this->hapusFolder($folder);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalisasi(array $data): array
    {
        $teks = fn (mixed $nilai): mixed => is_string($nilai) ? (trim($nilai) === '' ? null : trim($nilai)) : $nilai;
        $angka = fn (mixed $nilai): ?string => is_string($nilai) && filled($nilai) ? preg_replace('/\D/', '', $nilai) : null;

        foreach (['nama_lengkap', 'tempat_lahir', 'alamat_domisili', 'jurusan_pendidikan', 'alamat_kantor_desa', 'sumber_dana_keterangan'] as $kolom) {
            $data[$kolom] = $teks($data[$kolom] ?? null);
        }

        $data['nik'] = $angka($data['nik'] ?? null);
        $data['no_hp'] = $angka($data['no_hp'] ?? null);
        $data['npwp'] = $angka($data['npwp'] ?? null) ?: null;
        $data['email'] = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        $data['jenis_kelamin'] = ($data['jenis_kelamin'] ?? null) instanceof JenisKelamin ? $data['jenis_kelamin']->value : ($data['jenis_kelamin'] ?? null);

        foreach (array_keys(self::BERKAS) as $isian) {
            // FileUpload Filament dapat mengirim array; string path dari klien tidak diterima.
            $data[$isian] = is_array($data[$isian] ?? null) ? Arr::first($data[$isian]) : ($data[$isian] ?? null);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validasi(array $data): array
    {
        $berkasPdf = ['required', 'file', 'mimetypes:application/pdf', 'max:'.self::MAKS_UKURAN_BERKAS_KB];

        $validator = Validator::make($data, [
            'pelatihan_id' => ['required', 'integer', Rule::exists('pelatihan', 'id')->whereIn('status', array_map(
                fn (StatusPelatihan $status): string => $status->value,
                StatusPelatihan::yangMenerimaPendaftaran(),
            ))],
            'nik' => ['required', 'digits:16'],
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
            'jabatan_id' => ['required', 'integer', Rule::exists('jabatan', 'id')->where('is_aktif', true)],
            'waktu_pelantikan' => ['required', 'date', 'before_or_equal:today', 'after:tanggal_lahir'],
            'desa_id' => ['required', 'integer', Rule::exists('desa', 'id')],
            'alamat_kantor_desa' => ['required', 'string', 'max:1000'],
            'npwp' => ['nullable', 'regex:/^(\d{15}|\d{16})$/'],
            'status_ptkp_id' => ['required', 'integer', Rule::exists('status_ptkp', 'id')->where('is_aktif', true)],
            'sumber_dana_id' => ['required', 'integer', Rule::exists('sumber_dana', 'id')->where('is_aktif', true)],
            // Wajib jika sumber dana bertanda `butuh_keterangan`, misal "Lainnya" (FR-REG-04).
            'sumber_dana_keterangan' => [
                Rule::requiredIf(fn (): bool => (bool) SumberDana::query()->whereKey($data['sumber_dana_id'] ?? null)->value('butuh_keterangan')),
                'nullable', 'string', 'max:255',
            ],
            'ktp' => $berkasPdf,
            'foto' => ['required', 'file', 'mimetypes:image/jpeg', 'max:'.self::MAKS_UKURAN_BERKAS_KB],
            'surat_tugas' => $berkasPdf,
        ], [
            'pelatihan_id.exists' => 'Pelatihan ini tidak sedang menerima pendaftaran.',
            'sumber_dana_keterangan.required' => 'Keterangan sumber dana wajib diisi.',
            'tanggal_lahir.before' => 'Peserta minimal berusia 17 tahun.',
            'no_hp.regex' => 'Nomor HP diawali 08 dan terdiri dari 10–14 angka.',
            'npwp.regex' => 'NPWP terdiri dari 15 atau 16 angka.',
            'waktu_pelantikan.after' => 'Tanggal pelantikan harus setelah tanggal lahir.',
            '*.exists' => 'Pilihan :attribute tidak tersedia.',
            'ktp.mimetypes' => 'KTP harus berupa berkas PDF.',
            'surat_tugas.mimetypes' => 'Surat tugas harus berupa berkas PDF.',
            'foto.mimetypes' => 'Pas foto harus berupa berkas JPG.',
            '*.max' => ':attribute maksimal 2 MB. Kompres berkas lalu unggah ulang.',
        ], [
            'pelatihan_id' => 'pelatihan',
            'nik' => 'NIK',
            'nama_lengkap' => 'nama lengkap',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'alamat_domisili' => 'alamat domisili',
            'no_hp' => 'nomor HP',
            'jenjang_pendidikan' => 'jenjang pendidikan',
            'jurusan_pendidikan' => 'jurusan',
            'jabatan_id' => 'jabatan',
            'waktu_pelantikan' => 'tanggal pelantikan',
            'desa_id' => 'desa',
            'alamat_kantor_desa' => 'alamat kantor desa',
            'npwp' => 'NPWP',
            'status_ptkp_id' => 'status PTKP',
            'sumber_dana_id' => 'sumber dana',
            'sumber_dana_keterangan' => 'keterangan sumber dana',
            'ktp' => 'KTP',
            'foto' => 'Pas foto',
            'surat_tugas' => 'Surat tugas',
        ]);

        $valid = $validator->validate();

        foreach (array_keys(self::BERKAS) as $isian) {
            if (! $data[$isian] instanceof UploadedFile) {
                throw ValidationException::withMessages([$isian => 'Unggah ulang berkas ini.']);
            }
        }

        return [...$data, ...$valid];
    }

    private function hapusFolder(?string $folder): void
    {
        if ($folder !== null) {
            Storage::disk(self::DISK)->deleteDirectory($folder);
        }
    }
}
