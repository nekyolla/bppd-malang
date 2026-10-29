<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Actions\AturKelasAction;
use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Filament\Admin\Support\GalatService;
use App\Models\KelasPelatihan;
use App\Models\PelatihanPeserta;
use App\Services\ExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Peserta satu kelas dan daftar hadir cetak per hari (PF-18, PF-23). Daftar hadir tidak memuat NIK.
 */
class PesertaKelas extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = PelatihanResource::class;

    protected string $view = 'filament.admin.pages.tabel';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $kelasId;

    public function mount(int|string $record, int|string $kelas): void
    {
        $this->record = $this->resolveRecord($record);
        $this->kelasId = $this->getRecord()->kelas()->findOrFail($kelas)->getKey();
    }

    public function kelas(): KelasPelatihan
    {
        return KelasPelatihan::query()->findOrFail($this->kelasId);
    }

    public function getTitle(): string|Htmlable
    {
        return "Kelas {$this->kelas()->nama_kelas} · {$this->getRecord()->nama_tampilan}";
    }

    public function getBreadcrumb(): string
    {
        return "Kelas {$this->kelas()->nama_kelas}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PelatihanPeserta::query()
                ->where('kelas_pelatihan_id', $this->kelasId)
                ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai])
                ->with(['peserta', 'jabatanSaatPelatihan', 'desaSaatPelatihan.kecamatan.kabKota', 'kelasPelatihan']))
            ->columns([
                TextColumn::make('no')->label('No')->rowIndex(),
                TextColumn::make('peserta.nama_lengkap')->label('Nama')->searchable()->sortable(),
                TextColumn::make('peserta.jenis_kelamin')->label('Jenis kelamin'),
                TextColumn::make('jabatanSaatPelatihan.nama_jabatan')->label('Jabatan'),
                TextColumn::make('desaSaatPelatihan.nama')
                    ->label('Desa')
                    ->description(fn (PelatihanPeserta $record): ?string => $record->desaSaatPelatihan?->kecamatan->kabKota->nama),
            ])
            ->defaultSort('peserta.nama_lengkap')
            ->recordUrl(fn (PelatihanPeserta $record): string => PendaftaranResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                AturKelasAction::make()->label('Pindah kelas'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Kelas ini belum berisi peserta')
            ->emptyStateDescription('Bagi peserta di sub-menu Pembagian Kelas.');
    }

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(fn (): string => ManageKelas::getUrl(['record' => $this->getRecord()]), 'Kembali ke kelas'),
            Action::make('unduhDaftarHadir')
                ->label('Unduh daftar hadir')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalHeading('Unduh daftar hadir')
                ->modalDescription('Satu lembar per hari, A4 tegak. Presensi diisi di kertas.')
                ->modalSubmitActionLabel('Unduh')
                ->schema([
                    CheckboxList::make('tanggal')
                        ->label('Hari')
                        ->options(fn (): array => app(ExportService::class)->pilihanHari($this->getRecord()))
                        ->default(fn (): array => array_key_exists(today()->toDateString(), app(ExportService::class)->pilihanHari($this->getRecord()))
                            ? [today()->toDateString()]
                            : [])
                        ->bulkToggleable()
                        ->columns(2)
                        ->required(),
                    ToggleButtons::make('format')
                        ->label('Format')
                        ->options(['pdf' => 'PDF', 'excel' => 'Excel'])
                        ->default('pdf')
                        ->inline()
                        ->required(),
                ])
                ->visible(fn (): bool => auth()->user()->can('view', $this->getRecord()))
                ->action(fn (Action $action, array $data): StreamedResponse => GalatService::jalankan(
                    $action,
                    'Daftar hadir tidak dapat diunduh',
                    fn (): StreamedResponse => $this->unduh($data['tanggal'], $data['format']),
                )),
        ];
    }

    /**
     * @param  list<string>  $tanggal
     */
    private function unduh(array $tanggal, string $format): StreamedResponse
    {
        $kelas = $this->kelas();
        $service = app(ExportService::class);
        $lembar = $service->daftarHadirKelas($kelas, $tanggal);
        // Dari lembar hasil service (sudah divalidasi & dirapikan), bukan input mentah.
        $akhiran = count($lembar) === 1 ? $lembar[0]['tanggal'] : count($lembar).' hari';
        $nama = Str::slug("daftar-hadir {$this->getRecord()->nama_tampilan} kelas {$kelas->nama_kelas} {$akhiran}");

        return $format === 'pdf'
            ? $service->unduhPdf($lembar, $nama, $kelas)
            : $service->unduhExcel($lembar, $nama, $kelas);
    }
}
