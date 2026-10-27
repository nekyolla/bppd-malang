<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use App\Models\SertifikatPeserta;
use App\Models\User;
use App\Services\Concerns\MenolakAturan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sertifikat dibuat manual di luar sistem; admin mengunggah satu PDF per peserta (PF-27).
 * Berkas disimpan privat (CLAUDE.md aturan 1); peserta batal tidak mendapat sertifikat.
 */
class SertifikatService
{
    use MenolakAturan;

    public function path(PelatihanPeserta $pendaftaran): string
    {
        return "sertifikat/{$pendaftaran->pelatihan_id}/{$pendaftaran->getKey()}.pdf";
    }

    /**
     * Mengunggah atau mengganti (menimpa) sertifikat peserta terverifikasi/selesai.
     *
     * @throws ValidationException
     */
    public function unggah(PelatihanPeserta $pendaftaran, UploadedFile $berkas, User $oleh): SertifikatPeserta
    {
        Validator::make(['berkas' => $berkas], [
            'berkas' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.RegistrasiService::MAKS_UKURAN_BERKAS_KB],
        ], [
            'berkas.mimetypes' => 'Sertifikat harus berupa PDF.',
            'berkas.max' => 'Ukuran sertifikat maksimal 2 MB.',
        ])->validate();

        return DB::transaction(function () use ($pendaftaran, $berkas, $oleh): SertifikatPeserta {
            // Kunci baris pendaftaran agar tidak bersamaan dengan pembatalan.
            $terkunci = PelatihanPeserta::query()->lockForUpdate()->findOrFail($pendaftaran->getKey());

            if (! in_array($terkunci->status, [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai], true)) {
                $this->tolak('status', 'Sertifikat hanya dapat diunggah untuk peserta terverifikasi atau selesai.');
            }

            $path = $this->path($terkunci);
            Storage::disk(RegistrasiService::DISK)->putFileAs(dirname($path), $berkas, basename($path));

            $sertifikat = SertifikatPeserta::query()->firstOrNew(['pelatihan_peserta_id' => $terkunci->getKey()]);

            if (! $sertifikat->exists) {
                $sertifikat->fill(['file_sertifikat' => $path, 'diupload_oleh' => $oleh->getKey()])->save();

                return $sertifikat;
            }

            // Ganti: berkas ditimpa di path yang sama, jadi perubahan kolom bisa kosong. Dicatat
            // sebagai satu kejadian "ganti" agar selalu tampil di audit log (FR-AUD-01).
            $pengunggahLama = $sertifikat->diupload_oleh;
            $sertifikat->disableLogging();
            $sertifikat->fill(['file_sertifikat' => $path, 'diupload_oleh' => $oleh->getKey()]);
            $sertifikat->updated_at = now();
            $sertifikat->save();
            $sertifikat->enableLogging();

            activity()
                ->performedOn($sertifikat)
                ->causedBy($oleh)
                ->event('ganti')
                ->withProperties([
                    'old' => ['diupload_oleh' => $pengunggahLama],
                    'attributes' => ['file_sertifikat' => $path, 'diupload_oleh' => $oleh->getKey()],
                ])
                ->log('Mengganti sertifikat');

            return $sertifikat;
        });
    }

    /**
     * Menghapus sertifikat beserta berkasnya; berkas dihapus setelah transaksi berhasil.
     */
    public function hapus(SertifikatPeserta $sertifikat): void
    {
        $path = DB::transaction(function () use ($sertifikat): string {
            $sertifikat->delete();

            return $sertifikat->file_sertifikat;
        });

        Storage::disk(RegistrasiService::DISK)->delete($path);
    }
}
