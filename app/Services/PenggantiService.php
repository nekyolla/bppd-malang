<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Pengganti peserta batal (PRD §8.7, ARCHITECTURE §7.4, CLAUDE.md aturan 8):
 * desa sama, pelatihan sama, sebelum tanggal mulai, satu pengganti per peserta batal.
 */
class PenggantiService
{
    /**
     * Pendaftaran batal yang dapat digantikan oleh pendaftaran ini, untuk desa tertentu
     * (desa pengganti setelah dirapikan admin).
     *
     * @return Collection<int, PelatihanPeserta>
     */
    public function kandidat(PelatihanPeserta $pengganti, ?int $desaId): Collection
    {
        if ($desaId === null || ! $this->masihBisaDiganti($pengganti)) {
            return new Collection;
        }

        return PelatihanPeserta::query()
            ->with('peserta')
            ->where('pelatihan_id', $pengganti->pelatihan_id)
            ->where('status', StatusPendaftaran::Batal)
            ->whereKeyNot($pengganti->getKey())
            ->whereDoesntHave('pengganti')
            ->where(fn ($query) => $query
                ->where('desa_id_saat_pelatihan', $desaId)
                ->orWhere(fn ($q) => $q->whereNull('desa_id_saat_pelatihan')->whereHas('peserta', fn ($q) => $q->where('desa_id', $desaId))))
            ->orderBy('dibatalkan_pada')
            ->get();
    }

    /**
     * Menandai `$pengganti` sebagai pengganti `$digantikan`. Pengganti otomatis
     * masuk kelas peserta yang digantikan (FR-BTL-08). Dipanggil di dalam transaksi verifikasi.
     *
     * @throws ValidationException
     */
    public function tetapkan(PelatihanPeserta $pengganti, PelatihanPeserta $digantikan): void
    {
        $digantikan = PelatihanPeserta::query()->with('peserta')->lockForUpdate()->findOrFail($digantikan->getKey());
        $gagal = fn (string $pesan) => throw ValidationException::withMessages(['menggantikan_id' => $pesan]);

        match (true) {
            $digantikan->status !== StatusPendaftaran::Batal => $gagal('Hanya peserta yang batal yang dapat digantikan.'),
            $digantikan->pelatihan_id !== $pengganti->pelatihan_id => $gagal('Peserta yang digantikan harus dari pelatihan yang sama.'),
            ! $this->masihBisaDiganti($pengganti) => $gagal('Pengganti tidak dapat ditetapkan karena pelatihan sudah dimulai.'),
            $this->desa($digantikan) !== $this->desa($pengganti) => $gagal('Pengganti harus berasal dari desa yang sama dengan peserta yang digantikan.'),
            $digantikan->pengganti()->whereKeyNot($pengganti->getKey())->exists() => $gagal('Peserta ini sudah memiliki pengganti.'),
            default => null,
        };

        $pengganti->forceFill([
            'menggantikan_id' => $digantikan->getKey(),
            'kelas_pelatihan_id' => $digantikan->kelas_pelatihan_id ?? $pengganti->kelas_pelatihan_id,
        ])->save();
    }

    /**
     * Penggantian hanya sebelum tanggal mulai pelatihan (FR-BTL-06).
     */
    public function masihBisaDiganti(PelatihanPeserta $pendaftaran): bool
    {
        return today()->lt($pendaftaran->pelatihan->tanggal_mulai);
    }

    private function desa(PelatihanPeserta $pendaftaran): ?int
    {
        return $pendaftaran->desa_id_saat_pelatihan ?? $pendaftaran->peserta->desa_id;
    }
}
