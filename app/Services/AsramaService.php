<?php

namespace App\Services;

use App\Enums\JenisKelamin;
use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\TipeKamar;
use App\Enums\TipeLokasi;
use App\Models\Asrama;
use App\Models\AsramaPeserta;
use App\Models\KamarAsrama;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\User;
use App\Services\Concerns\MenolakAturan;
use App\Support\FormatTanggal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penempatan peserta di kamar asrama (PRD §8.9, ARCHITECTURE §7.3, CLAUDE.md aturan 4–5).
 */
class AsramaService
{
    use MenolakAturan;

    public const MAKS_PASUTRI = 2;

    /**
     * Menempatkan satu peserta di kamar. Kamar kosong bersifat netral; tipe L/P mengikuti
     * penghuni pertama, kecuali admin menjadikannya kamar pasutri (FR-ASR-02–07).
     *
     * @throws ValidationException
     */
    public function tempatkan(PelatihanPeserta $pendaftaran, KamarAsrama $kamar, User $oleh, bool $pasutri = false): AsramaPeserta
    {
        $penempatan = DB::transaction(function () use ($pendaftaran, $kamar, $oleh, $pasutri): AsramaPeserta {
            $terkunci = PelatihanPeserta::query()->with(['peserta', 'pelatihan.judulPelatihan'])->lockForUpdate()->findOrFail($pendaftaran->getKey());
            // Kunci baris kamar: penempatan bersamaan ke kamar yang sama dijalankan bergiliran.
            $kamar = KamarAsrama::query()->with('asrama')->lockForUpdate()->findOrFail($kamar->getKey());

            $this->pastikanPesertaBolehDitempatkan($terkunci);
            $this->pastikanKamarTersedia($kamar, $terkunci->pelatihan);

            $penggunaan = $this->pemakaianUntuk($kamar, $terkunci, $oleh, $pasutri);
            $jumlahPenghuni = $penggunaan->penghuni()->count();
            $kapasitas = $this->kapasitasEfektif($kamar, $penggunaan->tipe);

            if ($jumlahPenghuni >= $kapasitas) {
                $this->tolak('kamar_asrama_id', "Kamar {$kamar->nama_tampilan} sudah penuh ({$jumlahPenghuni}/{$kapasitas}).");
            }

            try {
                return AsramaPeserta::create([
                    'pelatihan_peserta_id' => $terkunci->getKey(),
                    'penggunaan_kamar_id' => $penggunaan->getKey(),
                    'ditempatkan_oleh' => $oleh->getKey(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->tolak('pelatihan_peserta_id', "{$terkunci->peserta->nama_lengkap} sudah ditempatkan di kamar lain.");
            }
        });

        $pendaftaran->unsetRelation('penempatanKamar');

        return $penempatan;
    }

    /**
     * Pelatihan di BBPD, peserta terverifikasi, dan belum punya kamar (FR-ASR-01, -02, -08).
     *
     * @throws ValidationException
     */
    private function pastikanPesertaBolehDitempatkan(PelatihanPeserta $pendaftaran): void
    {
        if ($pendaftaran->pelatihan->tipe_lokasi !== TipeLokasi::Bbpd) {
            $this->tolak('pelatihan_peserta_id', 'Asrama hanya untuk pelatihan di BBPD Malang.');
        }

        if ($pendaftaran->status !== StatusPendaftaran::Terverifikasi) {
            $this->tolak('pelatihan_peserta_id', 'Hanya peserta terverifikasi yang dapat ditempatkan di kamar.');
        }

        if ($sekarang = $pendaftaran->penempatanKamar()->with('penggunaanKamar.kamarAsrama.asrama')->first()) {
            $this->tolak('pelatihan_peserta_id', "{$pendaftaran->peserta->nama_lengkap} sudah ditempatkan di kamar {$sekarang->penggunaanKamar->kamarAsrama->nama_tampilan}. Keluarkan dulu sebelum memindahkan.");
        }
    }

    /**
     * Kamar & asramanya aktif, dan tidak dipakai pelatihan lain pada tanggal yang sama (FR-ASR-07).
     *
     * @throws ValidationException
     */
    private function pastikanKamarTersedia(KamarAsrama $kamar, Pelatihan $pelatihan): void
    {
        if (! $kamar->is_aktif || ! $kamar->asrama->is_aktif) {
            $this->tolak('kamar_asrama_id', "Kamar {$kamar->nama_tampilan} tidak tersedia karena sudah dinonaktifkan.");
        }

        if ($bentrok = $this->pemakaianBentrok($kamar->getKey(), $pelatihan->getKey(), $pelatihan->tanggal_mulai, $pelatihan->tanggal_selesai)) {
            $this->tolak('kamar_asrama_id', $this->pesanBentrok($kamar->nama_tampilan, $bentrok->pelatihan));
        }
    }

    /**
     * Pemakaian kamar oleh pelatihan peserta ini. Kamar kosong bersifat netral: pemakaian
     * baru bertipe gender penghuni pertama, atau pasutri bila admin memilihnya (FR-ASR-03, -05).
     * Pemakaian yang sudah ada harus cocok dengan gender peserta (FR-ASR-04).
     *
     * @throws ValidationException
     */
    private function pemakaianUntuk(KamarAsrama $kamar, PelatihanPeserta $pendaftaran, User $oleh, bool $pasutri): PenggunaanKamar
    {
        $penggunaan = PenggunaanKamar::query()
            ->where('pelatihan_id', $pendaftaran->pelatihan_id)
            ->where('kamar_asrama_id', $kamar->getKey())
            ->first();

        if ($penggunaan !== null) {
            $penghuni = $penggunaan->penghuni()->with('pelatihanPeserta.peserta')->get();
            $this->pastikanCocok($penggunaan, $penghuni, $pendaftaran->peserta->jenis_kelamin, $kamar->nama_tampilan, $pasutri);

            return $penggunaan;
        }

        if ($pasutri && $kamar->kapasitas < self::MAKS_PASUTRI) {
            $this->tolak('kamar_asrama_id', "Kamar {$kamar->nama_tampilan} hanya untuk 1 orang, sehingga tidak dapat dijadikan kamar pasutri.");
        }

        $penggunaan = new PenggunaanKamar([
            'pelatihan_id' => $pendaftaran->pelatihan_id,
            'kamar_asrama_id' => $kamar->getKey(),
            'disetujui_oleh' => $pasutri ? $oleh->getKey() : null,
        ]);
        $penggunaan->forceFill(['tipe' => $pasutri ? TipeKamar::Pasutri : TipeKamar::dariJenisKelamin($pendaftaran->peserta->jenis_kelamin)])->save();

        return $penggunaan;
    }

    /**
     * Menempatkan beberapa peserta di satu kamar sekaligus: semua berhasil atau tidak ada yang disimpan.
     *
     * @param  iterable<PelatihanPeserta>  $daftar
     *
     * @throws ValidationException
     */
    public function tempatkanBanyak(iterable $daftar, KamarAsrama $kamar, User $oleh, bool $pasutri = false): int
    {
        return DB::transaction(function () use ($daftar, $kamar, $oleh, $pasutri): int {
            $jumlah = 0;

            foreach ($daftar as $pendaftaran) {
                $this->tempatkan($pendaftaran, $kamar, $oleh, $pasutri);
                $jumlah++;
            }

            return $jumlah;
        });
    }

    /**
     * Mengeluarkan peserta dari kamar. Jika penghuni terakhir keluar, pemakaian
     * kamar dihapus sehingga kamar kembali netral (FR-ASR-09).
     *
     * @throws ValidationException
     */
    public function keluarkan(AsramaPeserta $penempatan): void
    {
        DB::transaction(function () use ($penempatan): void {
            $penggunaan = PenggunaanKamar::query()->findOrFail($penempatan->penggunaan_kamar_id);

            // Kunci baris kamar yang sama dengan penempatan (CLAUDE.md aturan 4).
            KamarAsrama::query()->lockForUpdate()->findOrFail($penggunaan->kamar_asrama_id);

            if (PelatihanPeserta::query()->findOrFail($penempatan->pelatihan_peserta_id)->status === StatusPendaftaran::Selesai) {
                $this->tolak('penempatan', 'Penempatan kamar peserta yang sudah selesai pelatihan tidak dapat diubah.');
            }

            $penempatan->delete();

            if ($penggunaan->penghuni()->doesntExist()) {
                $penggunaan->delete();
            }
        });
    }

    /**
     * Denah kamar satu pelatihan, dikelompokkan per asrama (FR-ASR-10). Memuat kamar aktif
     * ditambah kamar nonaktif yang sudah berpenghuni di pelatihan ini.
     *
     * @return list<array{id: int, nama: string, kamar: list<array<string, mixed>>}>
     */
    public function denah(Pelatihan $pelatihan): array
    {
        $penggunaan = $pelatihan->penggunaanKamar()
            ->with(['penghuni.pelatihanPeserta.peserta', 'penghuni.pelatihanPeserta.kelasPelatihan'])
            ->get()
            ->keyBy('kamar_asrama_id');
        $bentrok = $this->pemakaianBentrokQuery($pelatihan->getKey(), $pelatihan->tanggal_mulai, $pelatihan->tanggal_selesai)
            ->with('pelatihan.judulPelatihan')
            ->get()
            ->keyBy('kamar_asrama_id');
        $terpakai = $penggunaan->keys()->all();

        return Asrama::query()
            ->with(['kamar' => fn ($query) => $query
                ->where(fn ($q) => $q->where('is_aktif', true)->orWhereIn('id', $terpakai))
                ->orderBy('no_kamar')])
            ->orderBy('nama_asrama')
            ->get()
            ->map(fn (Asrama $asrama): array => [
                'id' => $asrama->id,
                'nama' => $asrama->nama_asrama,
                'kamar' => $asrama->kamar
                    ->filter(fn (KamarAsrama $kamar): bool => $asrama->is_aktif || $penggunaan->has($kamar->id))
                    ->map(fn (KamarAsrama $kamar): array => $this->keadaanKamar(
                        $kamar->setRelation('asrama', $asrama),
                        $penggunaan->get($kamar->id),
                        $bentrok->get($kamar->id),
                    ))
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $asrama): bool => $asrama['kamar'] !== [])
            ->values()
            ->all();
    }

    /**
     * Ringkasan kecukupan tempat tidur (FR-ASR-11). Tempat tidur di kamar kosong dapat dipakai
     * laki-laki maupun perempuan, sehingga `kurang` adalah perkiraan.
     *
     * @param  list<array{kamar: list<array<string, mixed>>}>|null  $denah
     * @return array{peserta: array{L: int, P: int}, belum: array{L: int, P: int}, sisa: array{L: int, P: int}, kosong: int, kurang: int}
     */
    public function ringkasan(Pelatihan $pelatihan, ?array $denah = null): array
    {
        $hasil = ['peserta' => ['L' => 0, 'P' => 0], 'belum' => ['L' => 0, 'P' => 0], 'sisa' => ['L' => 0, 'P' => 0], 'kosong' => 0, 'kurang' => 0];

        $pelatihan->pendaftaran()
            ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai])
            ->with('peserta:id,jenis_kelamin')
            ->withExists('penempatanKamar')
            ->get()
            ->each(function (PelatihanPeserta $pendaftaran) use (&$hasil): void {
                $jk = $pendaftaran->peserta->jenis_kelamin->value;
                $hasil['peserta'][$jk]++;

                if (! $pendaftaran->penempatan_kamar_exists) {
                    $hasil['belum'][$jk]++;
                }
            });

        foreach ($denah ?? $this->denah($pelatihan) as $asrama) {
            foreach ($asrama['kamar'] as $kamar) {
                if (! $kamar['tersedia']) {
                    continue;
                }

                match ($kamar['tipe']) {
                    null => $hasil['kosong'] += $kamar['kapasitas'],
                    TipeKamar::LakiLaki => $hasil['sisa']['L'] += $kamar['kapasitas'] - $kamar['jumlah'],
                    TipeKamar::Perempuan => $hasil['sisa']['P'] += $kamar['kapasitas'] - $kamar['jumlah'],
                    TipeKamar::Pasutri => $kamar['jumlah'] === 1
                        ? $hasil['sisa'][$this->lawanJenis($kamar['penghuni'][0]['jenis_kelamin'])->value]++
                        : null,
                };
            }
        }

        $kurang = max(0, $hasil['belum']['L'] - $hasil['sisa']['L']) + max(0, $hasil['belum']['P'] - $hasil['sisa']['P']);
        $hasil['kurang'] = max(0, $kurang - $hasil['kosong']);

        return $hasil;
    }

    /**
     * Peserta terverifikasi yang belum punya kamar, disaring sesuai kamar yang dipilih:
     * kamar L/P hanya gender yang sama, kamar pasutri berisi satu orang hanya lawan jenisnya.
     *
     * @return Collection<int, PelatihanPeserta>
     */
    public function calonPenghuni(Pelatihan $pelatihan, ?KamarAsrama $kamar = null): Collection
    {
        $jenisKelamin = $kamar ? $this->jenisKelaminCalon($pelatihan, $kamar) : null;

        return $pelatihan->pendaftaran()
            ->where('status', StatusPendaftaran::Terverifikasi)
            ->whereDoesntHave('penempatanKamar')
            ->when($jenisKelamin, fn (Builder $query) => $query->whereHas('peserta', fn (Builder $q) => $q->where('jenis_kelamin', $jenisKelamin)))
            ->with(['peserta', 'kelasPelatihan', 'menggantikan.peserta'])
            ->get()
            ->sortBy('peserta.nama_lengkap')
            ->values();
    }

    /**
     * Penghuni terbanyak kamar ini di pelatihan yang belum selesai; kapasitas kamar
     * tidak boleh diturunkan di bawah angka ini.
     */
    public function penghuniTerbanyak(KamarAsrama $kamar): int
    {
        return (int) $kamar->penggunaan()
            ->whereHas('pelatihan', fn (Builder $q) => $q->where('status', '!=', StatusPelatihan::Selesai))
            ->withCount('penghuni')
            ->get()
            ->max('penghuni_count');
    }

    /**
     * Pesan bentrok jika pelatihan dipindah ke tanggal yang membuat kamar yang sudah
     * dipakainya tumpang tindih dengan pelatihan lain (FR-ASR-07). `null` jika aman.
     */
    public function bentrokJadwal(Pelatihan $pelatihan, CarbonInterface $mulai, CarbonInterface $selesai): ?string
    {
        $kamarDipakai = $pelatihan->penggunaanKamar()->whereHas('penghuni')->pluck('kamar_asrama_id');

        if ($kamarDipakai->isEmpty()) {
            return null;
        }

        $bentrok = $this->pemakaianBentrokQuery($pelatihan->getKey(), $mulai, $selesai)
            ->whereIn('kamar_asrama_id', $kamarDipakai)
            ->with(['pelatihan.judulPelatihan', 'kamarAsrama.asrama'])
            ->first();

        return $bentrok ? $this->pesanBentrok($bentrok->kamarAsrama->nama_tampilan, $bentrok->pelatihan) : null;
    }

    public function kapasitasEfektif(KamarAsrama $kamar, ?TipeKamar $tipe): int
    {
        return $tipe === TipeKamar::Pasutri ? min(self::MAKS_PASUTRI, $kamar->kapasitas) : $kamar->kapasitas;
    }

    /**
     * @param  PenggunaanKamar|null  $penggunaan  Pemakaian kamar oleh pelatihan ini.
     * @param  PenggunaanKamar|null  $bentrok  Pemakaian oleh pelatihan lain yang tanggalnya tumpang tindih.
     * @return array<string, mixed>
     */
    private function keadaanKamar(KamarAsrama $kamar, ?PenggunaanKamar $penggunaan, ?PenggunaanKamar $bentrok): array
    {
        $tipe = $penggunaan?->tipe;
        $kapasitas = $this->kapasitasEfektif($kamar, $tipe);
        $penghuni = ($penggunaan?->penghuni ?? new Collection)
            ->map(fn (AsramaPeserta $p): array => [
                'id' => $p->id,
                'nama' => $p->pelatihanPeserta->peserta->nama_lengkap,
                'jenis_kelamin' => $p->pelatihanPeserta->peserta->jenis_kelamin,
                'kelas' => $p->pelatihanPeserta->kelasPelatihan?->nama_kelas,
            ])
            ->sortBy('nama')
            ->values()
            ->all();
        $jumlah = count($penghuni);
        $aktif = $kamar->is_aktif && $kamar->asrama->is_aktif;

        return [
            'id' => $kamar->id,
            'nama' => $kamar->nama_tampilan,
            'no_kamar' => $kamar->no_kamar,
            'kapasitas' => $kapasitas,
            'tipe' => $tipe,
            'penghuni' => $penghuni,
            'jumlah' => $jumlah,
            'penuh' => $jumlah >= $kapasitas,
            'tersedia' => $aktif && $bentrok === null,
            'keterangan' => match (true) {
                ! $aktif => 'Kamar dinonaktifkan',
                $bentrok !== null => "Dipakai {$bentrok->pelatihan->nama_tampilan} (".FormatTanggal::rentang($bentrok->pelatihan->tanggal_mulai, $bentrok->pelatihan->tanggal_selesai).')',
                default => null,
            },
        ];
    }

    private function jenisKelaminCalon(Pelatihan $pelatihan, KamarAsrama $kamar): ?JenisKelamin
    {
        $penggunaan = $pelatihan->penggunaanKamar()->where('kamar_asrama_id', $kamar->getKey())->with('penghuni.pelatihanPeserta.peserta')->first();

        return match ($penggunaan?->tipe) {
            null => null,
            TipeKamar::LakiLaki => JenisKelamin::LakiLaki,
            TipeKamar::Perempuan => JenisKelamin::Perempuan,
            TipeKamar::Pasutri => ($pertama = $penggunaan->penghuni->first())
                ? $this->lawanJenis($pertama->pelatihanPeserta->peserta->jenis_kelamin)
                : null,
        };
    }

    /**
     * @param  Collection<int, AsramaPeserta>  $penghuni
     *
     * @throws ValidationException
     */
    private function pastikanCocok(PenggunaanKamar $penggunaan, Collection $penghuni, JenisKelamin $jenisKelamin, string $nama, bool $pasutri): void
    {
        if ($pasutri && $penggunaan->tipe !== TipeKamar::Pasutri) {
            $this->tolak('kamar_asrama_id', "Kamar {$nama} sudah ditempati peserta ".mb_strtolower($penggunaan->tipe->getLabel()).', sehingga tidak dapat dijadikan kamar pasutri.');
        }

        if ($penggunaan->tipe === TipeKamar::Pasutri) {
            $sama = $penghuni->first(fn (AsramaPeserta $p): bool => $p->pelatihanPeserta->peserta->jenis_kelamin === $jenisKelamin);

            if ($sama) {
                $this->tolak('kamar_asrama_id', "Kamar pasutri {$nama} sudah berisi peserta ".mb_strtolower($jenisKelamin->getLabel()).'. Penghuni kedua harus '.mb_strtolower($this->lawanJenis($jenisKelamin)->getLabel()).'.');
            }

            return;
        }

        if ($penggunaan->tipe !== TipeKamar::dariJenisKelamin($jenisKelamin)) {
            $this->tolak('kamar_asrama_id', "Kamar {$nama} sudah ditempati peserta ".mb_strtolower($penggunaan->tipe->getLabel()).'. Pilih kamar lain.');
        }
    }

    private function lawanJenis(JenisKelamin $jenisKelamin): JenisKelamin
    {
        return $jenisKelamin === JenisKelamin::LakiLaki ? JenisKelamin::Perempuan : JenisKelamin::LakiLaki;
    }

    private function pemakaianBentrok(int $kamarId, int $pelatihanId, CarbonInterface $mulai, CarbonInterface $selesai): ?PenggunaanKamar
    {
        return $this->pemakaianBentrokQuery($pelatihanId, $mulai, $selesai)
            ->where('kamar_asrama_id', $kamarId)
            ->with('pelatihan.judulPelatihan')
            ->first();
    }

    /**
     * Pemakaian kamar berpenghuni oleh pelatihan lain yang tanggalnya tumpang tindih:
     * A.mulai <= B.selesai AND B.mulai <= A.selesai (ARCHITECTURE §7.3).
     *
     * @return Builder<PenggunaanKamar>
     */
    private function pemakaianBentrokQuery(int $pelatihanId, CarbonInterface $mulai, CarbonInterface $selesai): Builder
    {
        return PenggunaanKamar::query()
            ->where('pelatihan_id', '!=', $pelatihanId)
            ->whereHas('penghuni')
            ->whereHas('pelatihan', fn (Builder $q) => $q
                ->whereDate('tanggal_mulai', '<=', $selesai->toDateString())
                ->whereDate('tanggal_selesai', '>=', $mulai->toDateString()));
    }

    private function pesanBentrok(string $namaKamar, Pelatihan $lain): string
    {
        return "Kamar {$namaKamar} sedang dipakai {$lain->nama_tampilan} (".FormatTanggal::rentang($lain->tanggal_mulai, $lain->tanggal_selesai).').';
    }
}
