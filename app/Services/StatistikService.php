<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\Desa;
use App\Models\KabKota;
use App\Models\Pelatihan;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Statistik dashboard admin (PRD §8.16, ARCHITECTURE §7.7). Hanya query agregat.
 *
 * - Desa terlatih: desa dengan ≥ 1 pendaftaran berstatus `selesai`, menurut snapshot desa.
 * - Kab/kota terlatih: kab/kota dengan ≥ 1 desa terlatih.
 * - Desa belum terlatih: jumlah desa di wilayah − desa terlatih.
 *
 * `$tahun` menyaring tahun anggaran pelatihan; null = semua tahun (PF-11).
 */
class StatistikService
{
    public const CACHE_DETIK = 600;

    private const KUNCI_VERSI = 'statistik:versi';

    /**
     * @return array{pendaftar: int, per_status: array<string, int>, menunggu: int, peserta_terlatih: int, desa_terlatih: int, total_desa: int, kab_kota_terlatih: int, total_kab_kota: int}
     */
    public function ringkasan(?int $tahun = null): array
    {
        return $this->ingat("ringkasan:{$tahun}", function () use ($tahun): array {
            $perStatus = DB::table('pelatihan_peserta as pp')
                ->join('pelatihan as p', 'p.id', '=', 'pp.pelatihan_id')
                ->when($tahun, fn (Builder $q) => $q->where('p.tahun_anggaran', $tahun))
                ->groupBy('pp.status')
                ->pluck(DB::raw('count(*)'), 'pp.status')
                ->map(fn ($jumlah): int => (int) $jumlah);

            $status = collect(StatusPendaftaran::cases())
                ->mapWithKeys(fn (StatusPendaftaran $s): array => [$s->value => $perStatus[$s->value] ?? 0])
                ->all();

            $selesai = $this->selesai($tahun)
                ->join('desa as d', 'd.id', '=', 'pp.desa_id_saat_pelatihan')
                ->join('kecamatan as kc', 'kc.id', '=', 'd.kecamatan_id')
                ->selectRaw('count(distinct pp.peserta_id) as peserta, count(distinct d.id) as desa, count(distinct kc.kab_kota_id) as kab_kota')
                ->first();

            return [
                'pendaftar' => array_sum($status) - $status[StatusPendaftaran::Batal->value],
                'per_status' => $status,
                'menunggu' => $status[StatusPendaftaran::Terdaftar->value],
                'peserta_terlatih' => (int) $selesai->peserta,
                'desa_terlatih' => (int) $selesai->desa,
                'total_desa' => Desa::query()->count(),
                'kab_kota_terlatih' => (int) $selesai->kab_kota,
                'total_kab_kota' => KabKota::query()->count(),
            ];
        });
    }

    /**
     * Data peta & tabel provinsi.
     *
     * @return Collection<int, array{id: int, kode: string, nama: string, total_kab_kota: int, kab_kota_terlatih: int, total_desa: int, desa_terlatih: int, desa_belum_terlatih: int, peserta_terlatih: int}>
     */
    public function perProvinsi(?int $tahun = null): Collection
    {
        return $this->ingat("provinsi:{$tahun}", function () use ($tahun): Collection {
            $total = DB::table('provinsi as pr')
                ->leftJoin('kab_kota as kk', 'kk.provinsi_id', '=', 'pr.id')
                ->leftJoin('kecamatan as kc', 'kc.kab_kota_id', '=', 'kk.id')
                ->leftJoin('desa as d', 'd.kecamatan_id', '=', 'kc.id')
                ->groupBy('pr.id', 'pr.kode', 'pr.nama')
                ->orderBy('pr.kode')
                ->get(['pr.id', 'pr.kode', 'pr.nama', DB::raw('count(distinct kk.id) as total_kab_kota'), DB::raw('count(distinct d.id) as total_desa')]);

            $terlatih = $this->terlatihPer('kk.provinsi_id', $tahun);

            return $total->map(fn (object $baris): array => $this->baris($baris, $terlatih[$baris->id] ?? null) + [
                'total_kab_kota' => (int) $baris->total_kab_kota,
                'kab_kota_terlatih' => (int) ($terlatih[$baris->id]->kab_kota ?? 0),
            ]);
        });
    }

    /**
     * @return Collection<int, array{id: int, kode: string, nama: string, total_desa: int, desa_terlatih: int, desa_belum_terlatih: int, peserta_terlatih: int}>
     */
    public function perKabKota(int $provinsiId, ?int $tahun = null): Collection
    {
        return $this->ingat("kab-kota:{$provinsiId}:{$tahun}", function () use ($provinsiId, $tahun): Collection {
            $total = DB::table('kab_kota as kk')
                ->leftJoin('kecamatan as kc', 'kc.kab_kota_id', '=', 'kk.id')
                ->leftJoin('desa as d', 'd.kecamatan_id', '=', 'kc.id')
                ->where('kk.provinsi_id', $provinsiId)
                ->groupBy('kk.id', 'kk.kode', 'kk.nama')
                ->orderBy('kk.nama')
                ->get(['kk.id', 'kk.kode', 'kk.nama', DB::raw('count(distinct d.id) as total_desa')]);

            $terlatih = $this->terlatihPer('kk.id', $tahun, fn (Builder $q) => $q->where('kk.provinsi_id', $provinsiId));

            return $total->map(fn (object $baris): array => $this->baris($baris, $terlatih[$baris->id] ?? null));
        });
    }

    /**
     * Desa terlatih di satu kab/kota beserta jumlah peserta selesai. Tanpa nama peserta.
     *
     * @return Collection<int, array{id: int, kode: string, nama: string, kecamatan: string, peserta_terlatih: int}>
     */
    public function desaTerlatih(int $kabKotaId, ?int $tahun = null): Collection
    {
        return $this->ingat("desa:{$kabKotaId}:{$tahun}", fn (): Collection => $this->selesai($tahun)
            ->join('desa as d', 'd.id', '=', 'pp.desa_id_saat_pelatihan')
            ->join('kecamatan as kc', 'kc.id', '=', 'd.kecamatan_id')
            ->where('kc.kab_kota_id', $kabKotaId)
            ->groupBy('d.id', 'd.kode', 'd.nama', 'kc.nama')
            ->orderBy('kc.nama')
            ->orderBy('d.nama')
            ->get(['d.id', 'd.kode', 'd.nama', 'kc.nama as kecamatan', DB::raw('count(distinct pp.peserta_id) as peserta')])
            ->map(fn (object $baris): array => [
                'id' => (int) $baris->id,
                'kode' => $baris->kode,
                'nama' => $baris->nama,
                'kecamatan' => $baris->kecamatan,
                'peserta_terlatih' => (int) $baris->peserta,
            ]));
    }

    /**
     * Tahun anggaran yang punya pelatihan, terbaru dulu.
     *
     * @return list<int>
     */
    public function daftarTahun(): array
    {
        return Pelatihan::query()->distinct()->orderByDesc('tahun_anggaran')->pluck('tahun_anggaran')->map(fn ($t): int => (int) $t)->all();
    }

    /**
     * Dipanggil saat pendaftaran atau pelatihan berubah agar angka dashboard langsung terbaru.
     */
    public static function bersihkanCache(): void
    {
        Cache::forever(self::KUNCI_VERSI, (int) Cache::get(self::KUNCI_VERSI, 0) + 1);
    }

    /**
     * Pendaftaran selesai, disaring tahun anggaran pelatihan.
     */
    private function selesai(?int $tahun): Builder
    {
        return DB::table('pelatihan_peserta as pp')
            ->join('pelatihan as p', 'p.id', '=', 'pp.pelatihan_id')
            ->where('pp.status', StatusPendaftaran::Selesai->value)
            ->whereNotNull('pp.desa_id_saat_pelatihan')
            ->when($tahun, fn (Builder $q) => $q->where('p.tahun_anggaran', $tahun));
    }

    /**
     * Jumlah desa, kab/kota, dan peserta terlatih dikelompokkan per kolom wilayah.
     *
     * @return Collection<int|string, object>
     */
    private function terlatihPer(string $kolom, ?int $tahun, ?Closure $saring = null): Collection
    {
        return $this->selesai($tahun)
            ->join('desa as d', 'd.id', '=', 'pp.desa_id_saat_pelatihan')
            ->join('kecamatan as kc', 'kc.id', '=', 'd.kecamatan_id')
            ->join('kab_kota as kk', 'kk.id', '=', 'kc.kab_kota_id')
            ->when($saring, $saring)
            ->groupBy($kolom)
            ->get([
                DB::raw("{$kolom} as wilayah_id"),
                DB::raw('count(distinct d.id) as desa'),
                DB::raw('count(distinct kk.id) as kab_kota'),
                DB::raw('count(distinct pp.peserta_id) as peserta'),
            ])
            ->keyBy('wilayah_id');
    }

    /**
     * @return array{id: int, kode: string, nama: string, total_desa: int, desa_terlatih: int, desa_belum_terlatih: int, peserta_terlatih: int}
     */
    private function baris(object $total, ?object $terlatih): array
    {
        $desaTerlatih = (int) ($terlatih->desa ?? 0);

        return [
            'id' => (int) $total->id,
            'kode' => $total->kode,
            'nama' => $total->nama,
            'total_desa' => (int) $total->total_desa,
            'desa_terlatih' => $desaTerlatih,
            'desa_belum_terlatih' => (int) $total->total_desa - $desaTerlatih,
            'peserta_terlatih' => (int) ($terlatih->peserta ?? 0),
        ];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $hitung
     * @return T
     */
    private function ingat(string $kunci, Closure $hitung): mixed
    {
        $versi = (int) Cache::get(self::KUNCI_VERSI, 0);

        return Cache::remember("statistik:{$versi}:{$kunci}", self::CACHE_DETIK, $hitung);
    }
}
