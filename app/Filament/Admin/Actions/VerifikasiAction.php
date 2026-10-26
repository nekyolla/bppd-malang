<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Support\GalatService;
use App\Filament\Admin\Support\IsianPesertaForm;
use App\Filament\Admin\Support\PratinjauBerkas;
use App\Models\PelatihanPeserta;
use App\Models\SumberDana;
use App\Services\PendaftaranService;
use App\Services\PenggantiService;
use App\Support\IsianPeserta;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Admin memeriksa isian terhadap KTP & surat tugas (pratinjau di modal yang sama),
 * merapikan bila salah ketik, lalu memverifikasi (FR-DFT-04–05). Isian yang berbeda dari data peserta
 * sebelumnya diberi petunjuk "Data lama".
 */
class VerifikasiAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'verifikasi';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Verifikasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('info')
            ->slideOver()
            ->modalWidth('7xl')
            ->modalHeading(fn (PelatihanPeserta $record): string => "Verifikasi {$record->peserta->nama_lengkap}")
            ->modalSubmitActionLabel('Verifikasi')
            ->authorize('verifikasi')
            ->visible(fn (PelatihanPeserta $record): bool => $record->status === StatusPendaftaran::Terdaftar)
            ->fillForm(fn (PelatihanPeserta $record): array => [
                ...app(PendaftaranService::class)->isianVerifikasi($record),
                'sumber_dana_id' => $record->sumber_dana_id,
                'sumber_dana_keterangan' => $record->sumber_dana_keterangan,
                'catatan_panitia' => $record->catatan_panitia,
            ])
            ->schema(fn (PelatihanPeserta $record): array => $this->isianForm($record))
            ->action(function (PelatihanPeserta $record, array $data): void {
                GalatService::jalankan($this, 'Pendaftaran belum dapat diverifikasi', fn () => app(PendaftaranService::class)->verifikasi(
                    $record,
                    auth()->user(),
                    Arr::only($data, IsianPeserta::KOLOM),
                    $data['catatan_panitia'] ?? null,
                    filled($data['menggantikan_id'] ?? null) ? (int) $data['menggantikan_id'] : null,
                    Arr::only($data, ['sumber_dana_id', 'sumber_dana_keterangan']),
                ));

                Notification::make()->title('Pendaftaran terverifikasi')->success()->send();
            });
    }

    /**
     * Pratinjau KTP & foto di kiri, isian di kanan, agar admin dapat mencocokkan tanpa
     * berpindah tab. Isian yang berbeda dari data peserta lama diberi petunjuk "Data lama".
     *
     * @return array<int, mixed>
     */
    private function isianForm(PelatihanPeserta $record): array
    {
        $perbedaan = app(PendaftaranService::class)->perbedaanIsian($record);
        $dataLama = fn (string $kolom): ?string => array_key_exists($kolom, $perbedaan)
            ? 'Data lama: '.IsianPeserta::tampilkan($kolom, $perbedaan[$kolom][0])
            : null;

        return [
            Grid::make(['default' => 1, 'lg' => 5])->schema([
                PratinjauBerkas::pendaftaran($record)->columnSpan(['lg' => 2]),
                Group::make([
                    Text::make($perbedaan === []
                        ? 'Periksa isian dengan KTP dan surat tugas. Perbaiki bila ada salah ketik.'
                        : 'Peserta ini pernah terdaftar. Isian bertanda "Data lama" berbeda dari data sebelumnya.')
                        ->color($perbedaan === [] ? 'gray' : 'warning'),
                    ...IsianPesertaForm::bagian($dataLama),
                    $this->bagianSumberDana(),
                    $this->bagianPengganti($record),
                    Textarea::make('catatan_panitia')
                        ->label('Catatan panitia')
                        ->helperText('Opsional, misal data yang dirapikan.')
                        ->rows(2),
                ])->columnSpan(['lg' => 3]),
            ]),
        ];
    }

    private function bagianSumberDana(): Section
    {
        return Section::make('Sumber dana')
            ->description('Perbaiki bila peserta salah memilih, misal ternyata dibiayai APBN.')
            ->columns(2)
            ->schema([
                Select::make('sumber_dana_id')
                    ->label('Sumber dana')
                    ->options(fn (Get $get): array => IsianPesertaForm::masterAktif(SumberDana::class, $get('sumber_dana_id'))->orderBy('id')->pluck('nama', 'id')->all())
                    ->required()
                    ->live(),
                TextInput::make('sumber_dana_keterangan')
                    ->label('Keterangan sumber dana')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $this->butuhKeterangan($get('sumber_dana_id')))
                    ->required(fn (Get $get): bool => $this->butuhKeterangan($get('sumber_dana_id'))),
            ]);
    }

    private function bagianPengganti(PelatihanPeserta $record): Section
    {
        return Section::make('Pengganti')
            ->description('Peserta batal dari desa yang sama di pelatihan ini. Kosongkan jika peserta ini bukan pengganti.')
            ->visible(fn (Get $get): bool => $this->kandidatPengganti($record, $get('desa_id')) !== [])
            ->schema([
                Select::make('menggantikan_id')
                    ->label('Menggantikan')
                    ->options(fn (Get $get): array => $this->kandidatPengganti($record, $get('desa_id')))
                    ->live()
                    ->helperText('Pengganti otomatis masuk kelas peserta yang digantikan.')
                    ->hint(fn (Get $get): ?string => filled($get('menggantikan_id'))
                        && app(PenggantiService::class)->bedaJenisKelamin((int) $get('menggantikan_id'), $get('jenis_kelamin'))
                            ? 'Jenis kelamin berbeda dengan peserta yang digantikan. Pilih kamar lain saat penempatan.'
                            : null)
                    ->hintColor('warning'),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function kandidatPengganti(PelatihanPeserta $record, mixed $desaId): array
    {
        return app(PenggantiService::class)
            ->kandidat($record, filled($desaId) ? (int) $desaId : null)
            ->mapWithKeys(fn (PelatihanPeserta $batal): array => [
                $batal->id => "{$batal->peserta->nama_lengkap} — batal: {$batal->alasan_batal}",
            ])
            ->all();
    }

    private function butuhKeterangan(mixed $sumberDanaId): bool
    {
        return filled($sumberDanaId) && (bool) SumberDana::query()->whereKey($sumberDanaId)->value('butuh_keterangan');
    }
}
