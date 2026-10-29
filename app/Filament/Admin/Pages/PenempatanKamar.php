<?php

namespace App\Filament\Admin\Pages;

use App\Enums\StatusPelatihan;
use App\Enums\TipeKamar;
use App\Enums\TipeLokasi;
use App\Filament\Admin\Support\GalatService;
use App\Models\AsramaPeserta;
use App\Models\KamarAsrama;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Services\AsramaService;
use App\Services\PenggantiService;
use App\Support\FormatTanggal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Denah kamar per pelatihan dan penempatan peserta (FR-ASR-02–11, DESIGN_SYSTEM §6.4).
 * Semua aturan penempatan ada di AsramaService.
 */
class PenempatanKamar extends Page
{
    protected static ?string $slug = 'penempatan-kamar';

    protected static ?string $title = 'Penempatan Kamar';

    protected static ?string $navigationLabel = 'Penempatan Kamar';

    protected static string|UnitEnum|null $navigationGroup = 'Asrama';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected string $view = 'filament.admin.pages.penempatan-kamar';

    #[Url]
    public ?int $pelatihan = null;

    /**
     * @var array<string, mixed>
     */
    public ?array $pilihan = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', AsramaPeserta::class) ?? false;
    }

    public function mount(): void
    {
        $this->pelatihan ??= self::pelatihanBawaan();
        $this->form->fill(['pelatihan' => $this->pelatihan]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('pilihan')
            ->components([
                Select::make('pelatihan')
                    ->label('Pelatihan')
                    ->placeholder('Pilih pelatihan di Balai Besar')
                    ->options(fn (): array => Pelatihan::query()
                        ->with('judulPelatihan')
                        ->where('tipe_lokasi', TipeLokasi::Bbpd)
                        ->orderByDesc('tanggal_mulai')
                        ->get()
                        ->mapWithKeys(fn (Pelatihan $p): array => [
                            $p->id => "{$p->nama_tampilan} · ".FormatTanggal::rentang($p->tanggal_mulai, $p->tanggal_selesai)." · {$p->status->getLabel()}",
                        ])
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->pelatihan = filled($state) ? (int) $state : null;
                        unset($this->denah, $this->pelatihanTerpilih);
                    }),
            ]);
    }

    #[Computed]
    public function pelatihanTerpilih(): ?Pelatihan
    {
        return $this->pelatihan
            ? Pelatihan::query()->with('judulPelatihan')->where('tipe_lokasi', TipeLokasi::Bbpd)->find($this->pelatihan)
            : null;
    }

    /**
     * @return list<array{id: int, nama: string, kamar: list<array<string, mixed>>}>
     */
    #[Computed]
    public function denah(): array
    {
        return $this->pelatihanTerpilih ? app(AsramaService::class)->denah($this->pelatihanTerpilih) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ringkasan(): ?array
    {
        return $this->pelatihanTerpilih ? app(AsramaService::class)->ringkasan($this->pelatihanTerpilih, $this->denah) : null;
    }

    public function jumlahBelumDapatKamar(): int
    {
        return array_sum($this->ringkasan()['belum'] ?? []);
    }

    /**
     * Penempatan hanya dapat diubah sebelum pelatihan selesai.
     */
    public function bisaDiubah(): bool
    {
        return $this->pelatihanTerpilih !== null && $this->pelatihanTerpilih->status !== StatusPelatihan::Selesai;
    }

    /**
     * Badge kamar sesuai DESIGN_SYSTEM §2.4; label selalu memuat jumlah/kapasitas.
     *
     * @param  array<string, mixed>  $kamar
     * @return array{label: string, color: string|array<int|string, string>, icon: Heroicon}
     */
    public function lencana(array $kamar): array
    {
        $jumlah = "{$kamar['jumlah']}/{$kamar['kapasitas']}";
        /** @var TipeKamar|null $tipe */
        $tipe = $kamar['tipe'];

        return match (true) {
            ! $kamar['tersedia'] && $kamar['jumlah'] === 0 => ['label' => 'Tidak tersedia', 'color' => 'gray', 'icon' => Heroicon::OutlinedNoSymbol],
            $tipe === null => ['label' => "Kosong · {$jumlah}", 'color' => 'gray', 'icon' => Heroicon::OutlinedHome],
            $kamar['penuh'] => ['label' => "{$tipe->getLabel()} · Penuh {$jumlah}", 'color' => $tipe->getColor(), 'icon' => Heroicon::OutlinedLockClosed],
            default => ['label' => "{$tipe->getLabel()} · {$jumlah}", 'color' => $tipe->getColor(), 'icon' => $tipe->getIcon()],
        };
    }

    public function tempatkanAction(): Action
    {
        return Action::make('tempatkan')
            ->label('Tempatkan')
            ->icon(Heroicon::OutlinedPlus)
            ->color('primary')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => 'Tempatkan peserta di kamar '.$this->kamar($arguments)->nama_tampilan)
            ->modalDescription(fn (array $arguments): string => $this->keteranganKamar($arguments))
            ->modalSubmitActionLabel('Tempatkan')
            ->modalWidth('xl')
            ->schema(fn (array $arguments): array => [
                Select::make('peserta')
                    ->label('Peserta')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options($this->calonPenghuni($arguments))
                    ->maxItems(max(1, $this->sisaTempat($arguments)))
                    ->helperText($this->calonPenghuni($arguments) === []
                        ? 'Tidak ada peserta yang cocok untuk kamar ini.'
                        : 'Peserta terverifikasi tanpa kamar, sesuai gender kamar.'),
                Toggle::make('pasutri')
                    ->label('Jadikan kamar pasutri')
                    ->helperText('Maksimal 2 orang: 1 laki-laki dan 1 perempuan.')
                    ->visible(fn (): bool => $this->keadaan($arguments)['tipe'] === null && $this->kamar($arguments)->kapasitas >= AsramaService::MAKS_PASUTRI),
            ])
            ->authorize(fn (): bool => auth()->user()->can('create', AsramaPeserta::class))
            ->visible(fn (): bool => $this->bisaDiubah())
            ->action(function (array $arguments, array $data, Action $action): void {
                $kamar = $this->kamar($arguments);
                $daftar = PelatihanPeserta::query()
                    ->where('pelatihan_id', $this->pelatihan)
                    ->whereIn('id', $data['peserta'])
                    ->get();

                $jumlah = GalatService::jalankan($action, 'Peserta belum dapat ditempatkan', fn () => app(AsramaService::class)->tempatkanBanyak($daftar, $kamar, auth()->user(), (bool) ($data['pasutri'] ?? false)));

                unset($this->denah);
                Notification::make()->title("{$jumlah} peserta ditempatkan di kamar {$kamar->nama_tampilan}")->success()->send();
            });
    }

    public function keluarkanAction(): Action
    {
        // Ikon saja: tombol merah berlabel di setiap baris penghuni terlalu ramai.
        return Action::make('keluarkan')
            ->label('Keluarkan')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->size('sm')
            ->iconButton()
            ->tooltip('Keluarkan dari kamar')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => "Keluarkan {$this->penempatan($arguments)->pelatihanPeserta->peserta->nama_lengkap}?")
            ->modalDescription(fn (array $arguments): string => "Peserta keluar dari kamar {$this->penempatan($arguments)->penggunaanKamar->kamarAsrama->nama_tampilan}. Kamar yang kosong kembali netral.")
            ->modalSubmitActionLabel('Keluarkan')
            ->authorize(fn (array $arguments): bool => auth()->user()->can('delete', $this->penempatan($arguments)))
            ->visible(fn (): bool => $this->bisaDiubah())
            ->action(function (array $arguments, Action $action): void {
                GalatService::jalankan($action, 'Peserta tidak dapat dikeluarkan', fn () => app(AsramaService::class)->keluarkan($this->penempatan($arguments)));

                unset($this->denah);
                Notification::make()->title('Peserta keluar dari kamar')->success()->send();
            });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->isiOtomatisAction(),
        ];
    }

    public function isiOtomatisAction(): Action
    {
        return Action::make('isiOtomatis')
            ->label('Isi otomatis')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalHeading('Isi kamar otomatis?')
            ->modalDescription(fn (): string => "{$this->ringkasan()['belum']['L']} laki-laki dan {$this->ringkasan()['belum']['P']} perempuan belum dapat kamar. "
                .'Sistem mengisi kamar segender yang masih bersisa dulu, lalu kamar kosong; teman sekelas didekatkan. Kamar pasutri tidak diisi. Hasilnya tetap bisa diubah.')
            ->modalSubmitActionLabel('Isi otomatis')
            ->authorize(fn (): bool => auth()->user()->can('create', AsramaPeserta::class))
            ->visible(fn (): bool => $this->bisaDiubah())
            ->disabled(fn (): bool => $this->jumlahBelumDapatKamar() === 0)
            ->tooltip(fn (): ?string => $this->jumlahBelumDapatKamar() === 0 ? 'Semua peserta terverifikasi sudah dapat kamar.' : null)
            ->action(function (Action $action): void {
                $hasil = GalatService::jalankan($action, 'Kamar belum dapat diisi otomatis', fn () => app(AsramaService::class)->isiOtomatis($this->pelatihanTerpilih, auth()->user()));

                unset($this->denah);

                $notifikasi = Notification::make()->title("{$hasil['ditempatkan']} peserta ditempatkan otomatis");

                $hasil['sisa'] > 0
                    ? $notifikasi->body("{$hasil['sisa']} peserta belum kebagian kamar. Tambah kamar atau tempatkan manual.")->warning()->send()
                    : $notifikasi->success()->send();
            });
    }

    public function kosongkanAction(): Action
    {
        return Action::make('kosongkan')
            ->label('Kosongkan')
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('danger')
            ->size('sm')
            ->link()
            ->modalHeading(fn (array $arguments): string => 'Keluarkan penghuni kamar '.$this->kamar($arguments)->nama_tampilan)
            ->modalDescription('Penghuni yang dicentang keluar sekaligus. Kamar yang kosong kembali netral.')
            ->modalSubmitActionLabel('Keluarkan')
            ->modalWidth('md')
            ->schema(fn (array $arguments): array => [
                CheckboxList::make('penempatan')
                    ->label('Penghuni')
                    ->options(collect($this->keadaan($arguments)['penghuni'])->pluck('nama', 'id')->all())
                    ->default(collect($this->keadaan($arguments)['penghuni'])->pluck('id')->all())
                    ->bulkToggleable()
                    ->required(),
            ])
            ->authorize(fn (): bool => auth()->user()->can('viewAny', AsramaPeserta::class))
            ->visible(fn (): bool => $this->bisaDiubah())
            ->action(function (array $arguments, array $data, Action $action): void {
                $daftar = AsramaPeserta::query()
                    ->whereHas('penggunaanKamar', fn ($q) => $q->where('pelatihan_id', $this->pelatihan)->where('kamar_asrama_id', $arguments['kamar'] ?? null))
                    ->whereIn('id', $data['penempatan'])
                    ->get();

                abort_unless($daftar->every(fn (AsramaPeserta $p): bool => auth()->user()->can('delete', $p)), 403);

                $jumlah = GalatService::jalankan($action, 'Penghuni tidak dapat dikeluarkan', fn () => app(AsramaService::class)->keluarkanBanyak($daftar));

                unset($this->denah);
                Notification::make()->title("{$jumlah} peserta keluar dari kamar {$this->kamar($arguments)->nama_tampilan}")->success()->send();
            });
    }

    /**
     * Pelatihan yang sedang berjalan atau paling dekat dibuka; selain itu yang terbaru.
     */
    private static function pelatihanBawaan(): ?int
    {
        $bbpd = fn () => Pelatihan::query()->where('tipe_lokasi', TipeLokasi::Bbpd);

        return $bbpd()->where('status', StatusPelatihan::Berjalan)->orderBy('tanggal_mulai')->value('id')
            ?? $bbpd()->where('status', StatusPelatihan::Dibuka)->orderBy('tanggal_mulai')->value('id')
            ?? $bbpd()->orderByDesc('tanggal_mulai')->value('id');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function kamar(array $arguments): KamarAsrama
    {
        return KamarAsrama::query()->with('asrama')->findOrFail($arguments['kamar'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function keadaan(array $arguments): array
    {
        return collect($this->denah)->pluck('kamar')->flatten(1)->firstWhere('id', (int) ($arguments['kamar'] ?? 0))
            ?? throw new \RuntimeException('Kamar tidak ada di denah pelatihan ini.');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sisaTempat(array $arguments): int
    {
        $keadaan = $this->keadaan($arguments);

        // Kamar kosong yang dijadikan pasutri tetap dibatasi service menjadi 2 orang.
        return $keadaan['kapasitas'] - $keadaan['jumlah'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function keteranganKamar(array $arguments): string
    {
        $keadaan = $this->keadaan($arguments);

        return match ($keadaan['tipe']) {
            null => "Kamar kosong untuk {$keadaan['kapasitas']} orang. Gender ikut penghuni pertama.",
            TipeKamar::Pasutri => 'Kamar pasutri: 1 laki-laki dan 1 perempuan.',
            default => "Kamar {$keadaan['tipe']->getLabel()}, terisi {$keadaan['jumlah']}/{$keadaan['kapasitas']}.",
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, string>
     */
    private function calonPenghuni(array $arguments): array
    {
        $pengganti = app(PenggantiService::class);

        return app(AsramaService::class)
            ->calonPenghuni($this->pelatihanTerpilih, $this->kamar($arguments))
            ->mapWithKeys(fn (PelatihanPeserta $p): array => [$p->id => collect([
                $p->peserta->nama_lengkap,
                $p->peserta->jenis_kelamin->getLabel(),
                $p->kelasPelatihan ? "Kelas {$p->kelasPelatihan->nama_kelas}" : null,
                $p->menggantikan ? "pengganti {$p->menggantikan->peserta->nama_lengkap}"
                    .($pengganti->bedaJenisKelamin($p->menggantikan->id, $p->peserta->jenis_kelamin) ? ' (beda jenis kelamin, pilih kamar lain)' : '') : null,
            ])->filter()->implode(' · ')])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function penempatan(array $arguments): AsramaPeserta
    {
        return AsramaPeserta::query()
            ->with(['pelatihanPeserta.peserta', 'penggunaanKamar.kamarAsrama.asrama'])
            ->whereHas('penggunaanKamar', fn ($q) => $q->where('pelatihan_id', $this->pelatihan))
            ->findOrFail($arguments['penempatan'] ?? null);
    }
}
