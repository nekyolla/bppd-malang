<?php

namespace App\Services;

use App\Enums\StatusPelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SumberDana;
use App\Support\IsianPeserta;
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
                $isianPeserta = Arr::only($data, IsianPeserta::KOLOM);
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
        $data = IsianPeserta::normalisasi($data);
        $data['sumber_dana_keterangan'] = is_string($data['sumber_dana_keterangan'] ?? null) && trim($data['sumber_dana_keterangan']) !== ''
            ? trim($data['sumber_dana_keterangan'])
            : null;

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
            ...IsianPeserta::aturan(),
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
            // Pesan spesifik harus di atas pola `*.` karena Laravel memakai pola pertama yang cocok.
            'pelatihan_id.exists' => 'Pelatihan ini tidak sedang menerima pendaftaran.',
            'sumber_dana_keterangan.required' => 'Keterangan sumber dana wajib diisi.',
            'ktp.mimetypes' => 'KTP harus berupa berkas PDF.',
            'surat_tugas.mimetypes' => 'Surat tugas harus berupa berkas PDF.',
            'foto.mimetypes' => 'Pas foto harus berupa berkas JPG.',
            ...IsianPeserta::pesan(),
            '*.max' => ':attribute maksimal 2 MB. Kompres berkas lalu unggah ulang.',
        ], [
            ...IsianPeserta::LABEL,
            'pelatihan_id' => 'pelatihan',
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
