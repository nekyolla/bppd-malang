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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sertifikat dibuat manual di luar sistem; admin mengunggah satu PDF per peserta (PF-27)
 * beserta nomornya bila ada (opsional, unik; PF-37). Berkas disimpan privat (CLAUDE.md
 * aturan 1); peserta batal tidak mendapat sertifikat.
 */
class SertifikatService
{
    use MenolakAturan;

    /**
     * Path unik per unggahan, agar berkas lama tidak tertimpa sebelum baris tersimpan.
     */
    public function pathBaru(PelatihanPeserta $pendaftaran): string
    {
        return "sertifikat/{$pendaftaran->pelatihan_id}/{$pendaftaran->getKey()}-".Str::lower(Str::random(10)).'.pdf';
    }

    /**
     * Mengunggah atau mengganti sertifikat peserta terverifikasi/selesai. Berkas baru ditulis
     * ke path baru; berkas lama dihapus setelah transaksi berhasil, berkas baru dihapus bila gagal.
     * Nomor kosong saat mengganti berarti nomor lama dipertahankan.
     *
     * @throws ValidationException
     */
    public function unggah(PelatihanPeserta $pendaftaran, UploadedFile $berkas, User $oleh, ?string $nomor = null): SertifikatPeserta
    {
        Validator::make(['berkas' => $berkas], [
            'berkas' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.RegistrasiService::MAKS_UKURAN_BERKAS_KB],
        ], [
            'berkas.mimetypes' => 'Sertifikat harus berupa PDF.',
            'berkas.max' => 'Ukuran sertifikat maksimal 2 MB.',
        ])->validate();

        $nomor = $this->rapikanNomor($nomor);
        $this->tolakNomorTerpakai($nomor, $pendaftaran->sertifikat()->value('id'));

        $disk = Storage::disk(RegistrasiService::DISK);
        $path = null;
        $pathLama = null;

        try {
            $sertifikat = DB::transaction(function () use ($pendaftaran, $berkas, $oleh, $nomor, $disk, &$path, &$pathLama): SertifikatPeserta {
                // Kunci baris pendaftaran agar tidak bersamaan dengan pembatalan.
                $terkunci = PelatihanPeserta::query()->lockForUpdate()->findOrFail($pendaftaran->getKey());

                if (! in_array($terkunci->status, [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai], true)) {
                    $this->tolak('status', 'Sertifikat hanya untuk peserta terverifikasi atau selesai.');
                }

                $path = $this->pathBaru($terkunci);
                $disk->putFileAs(dirname($path), $berkas, basename($path));

                $sertifikat = SertifikatPeserta::query()->firstOrNew(['pelatihan_peserta_id' => $terkunci->getKey()]);

                if (! $sertifikat->exists) {
                    $sertifikat->fill(['nomor_sertifikat' => $nomor, 'file_sertifikat' => $path, 'diupload_oleh' => $oleh->getKey()])->save();

                    return $sertifikat;
                }

                if ($nomor !== null && $nomor !== $sertifikat->nomor_sertifikat) {
                    $sertifikat->update(['nomor_sertifikat' => $nomor]);
                }

                // Ganti dicatat sebagai satu kejadian "ganti" agar selalu tampil di audit log
                // dengan pengunggah lama, juga bila admin yang sama (FR-AUD-01).
                $pathLama = $sertifikat->file_sertifikat;
                $pengunggahLama = $sertifikat->diupload_oleh;
                $sertifikat->disableLogging();
                $sertifikat->fill(['file_sertifikat' => $path, 'diupload_oleh' => $oleh->getKey()])->save();
                $sertifikat->enableLogging();

                activity()
                    ->performedOn($sertifikat)
                    ->causedBy($oleh)
                    ->event('ganti')
                    ->withProperties([
                        'old' => ['file_sertifikat' => $pathLama, 'diupload_oleh' => $pengunggahLama],
                        'attributes' => ['file_sertifikat' => $path, 'diupload_oleh' => $oleh->getKey()],
                    ])
                    ->log('Mengganti sertifikat');

                return $sertifikat;
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                $disk->delete($path);
            }

            throw $e;
        }

        if ($pathLama !== null && $pathLama !== $path) {
            $disk->delete($pathLama);
        }

        return $sertifikat;
    }

    /**
     * Mengubah atau mengosongkan nomor sertifikat tanpa mengganti berkas (PF-37).
     *
     * @throws ValidationException
     */
    public function ubahNomor(SertifikatPeserta $sertifikat, ?string $nomor): void
    {
        $nomor = $this->rapikanNomor($nomor);
        $this->tolakNomorTerpakai($nomor, $sertifikat->getKey());

        $sertifikat->update(['nomor_sertifikat' => $nomor]);
    }

    private function rapikanNomor(?string $nomor): ?string
    {
        $nomor = trim((string) $nomor);

        if (mb_strlen($nomor) > 100) {
            $this->tolak('nomor_sertifikat', 'Nomor sertifikat maksimal 100 karakter.');
        }

        return $nomor === '' ? null : $nomor;
    }

    /**
     * @throws ValidationException
     */
    private function tolakNomorTerpakai(?string $nomor, ?int $kecualiId): void
    {
        $terpakai = $nomor !== null && SertifikatPeserta::query()
            ->where('nomor_sertifikat', $nomor)
            ->when($kecualiId, fn ($query) => $query->whereKeyNot($kecualiId))
            ->exists();

        if ($terpakai) {
            $this->tolak('nomor_sertifikat', 'Nomor sertifikat sudah dipakai peserta lain.');
        }
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
