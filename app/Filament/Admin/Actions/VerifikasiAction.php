<?php

namespace App\Filament\Admin\Actions;

use App\Enums\JenisKelamin;
use App\Enums\StatusPendaftaran;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use App\Services\PendaftaranService;
use App\Services\PenggantiService;
use App\Support\IsianPeserta;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Admin memeriksa isian terhadap KTP & surat tugas, merapikan bila salah ketik,
 * lalu memverifikasi (FR-DFT-04–05). Isian yang berbeda dari data peserta
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
            ->modalWidth('3xl')
            ->modalHeading(fn (PelatihanPeserta $record): string => "Verifikasi {$record->peserta->nama_lengkap}")
            ->modalSubmitActionLabel('Verifikasi')
            ->authorize('verifikasi')
            ->visible(fn (PelatihanPeserta $record): bool => $record->status === StatusPendaftaran::Terdaftar)
            ->fillForm(fn (PelatihanPeserta $record): array => [
                ...app(PendaftaranService::class)->isianVerifikasi($record),
                'catatan_panitia' => $record->catatan_panitia,
            ])
            ->schema(fn (PelatihanPeserta $record): array => $this->isianForm($record))
            ->action(function (PelatihanPeserta $record, array $data): void {
                try {
                    app(PendaftaranService::class)->verifikasi(
                        $record,
                        auth()->user(),
                        Arr::only($data, IsianPeserta::KOLOM),
                        $data['catatan_panitia'] ?? null,
                        filled($data['menggantikan_id'] ?? null) ? (int) $data['menggantikan_id'] : null,
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Pendaftaran belum dapat diverifikasi')
                        ->body(Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();

                    $this->halt();
                }

                Notification::make()->title('Pendaftaran terverifikasi')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function isianForm(PelatihanPeserta $record): array
    {
        $perbedaan = app(PendaftaranService::class)->perbedaanIsian($record);
        $dataLama = fn (string $kolom): ?string => array_key_exists($kolom, $perbedaan)
            ? 'Data lama: '.IsianPeserta::tampilkan($kolom, $perbedaan[$kolom][0])
            : null;
        $masterAktif = fn (string $model, string $kolom): Closure => fn (Get $get) => $model::query()
            ->where(fn ($q) => $q->where('is_aktif', true)->orWhere('id', $get($kolom)));

        return [
            Text::make(new HtmlString($this->tautanBerkas($record)))
                ->color('gray'),
            Text::make($perbedaan === []
                ? 'Periksa isian dengan KTP dan surat tugas. Perbaiki bila ada salah ketik.'
                : 'Peserta ini pernah terdaftar. Isian bertanda "Data lama" berbeda dari data sebelumnya.')
                ->color($perbedaan === [] ? 'gray' : 'warning'),
            Section::make('Identitas')
                ->columns(2)
                ->schema([
                    TextInput::make('nik')->label('NIK')->required()->length(16)->hint($dataLama('nik'))->hintColor('warning'),
                    TextInput::make('nama_lengkap')->label('Nama lengkap')->required()->maxLength(255)->hint($dataLama('nama_lengkap'))->hintColor('warning'),
                    Select::make('jenis_kelamin')->label('Jenis kelamin')->options(JenisKelamin::class)->required()->hint($dataLama('jenis_kelamin'))->hintColor('warning'),
                    TextInput::make('tempat_lahir')->label('Tempat lahir')->required()->maxLength(255)->hint($dataLama('tempat_lahir'))->hintColor('warning'),
                    DatePicker::make('tanggal_lahir')->label('Tanggal lahir')->required()->hint($dataLama('tanggal_lahir'))->hintColor('warning'),
                    Select::make('agama')->label('Agama')->options(array_combine(Peserta::AGAMA, Peserta::AGAMA))->required()->hint($dataLama('agama'))->hintColor('warning'),
                    TextInput::make('no_hp')->label('Nomor HP')->required()->tel()->hint($dataLama('no_hp'))->hintColor('warning'),
                    TextInput::make('email')->label('Email')->email()->hint($dataLama('email'))->hintColor('warning'),
                    Select::make('jenjang_pendidikan')->label('Pendidikan terakhir')->options(array_combine(Peserta::JENJANG_PENDIDIKAN, Peserta::JENJANG_PENDIDIKAN))->required()->hint($dataLama('jenjang_pendidikan'))->hintColor('warning'),
                    TextInput::make('jurusan_pendidikan')->label('Jurusan')->hint($dataLama('jurusan_pendidikan'))->hintColor('warning'),
                    Textarea::make('alamat_domisili')->label('Alamat domisili')->required()->rows(2)->columnSpanFull()->hint($dataLama('alamat_domisili'))->hintColor('warning'),
                ]),
            Section::make('Jabatan, desa & pajak')
                ->columns(2)
                ->schema([
                    Select::make('jabatan_id')
                        ->label('Jabatan')
                        ->options(fn (Get $get): array => $masterAktif(Jabatan::class, 'jabatan_id')($get)->orderBy('urutan')->pluck('nama_jabatan', 'id')->all())
                        ->required()
                        ->searchable()
                        ->hint($dataLama('jabatan_id'))->hintColor('warning'),
                    DatePicker::make('waktu_pelantikan')->label('Tanggal pelantikan')->required()->hint($dataLama('waktu_pelantikan'))->hintColor('warning'),
                    Select::make('desa_id')
                        ->label('Desa')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Desa::query()
                            ->where('nama', 'like', "%{$search}%")
                            ->orderBy('nama')
                            ->limit(50)
                            ->pluck('id')
                            ->mapWithKeys(fn (int $id): array => [$id => IsianPeserta::tampilkan('desa_id', $id)])
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): string => IsianPeserta::tampilkan('desa_id', $value))
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->hint($dataLama('desa_id'))->hintColor('warning'),
                    Textarea::make('alamat_kantor_desa')->label('Alamat kantor desa')->required()->rows(2)->columnSpanFull()->hint($dataLama('alamat_kantor_desa'))->hintColor('warning'),
                    TextInput::make('npwp')->label('NPWP')->hint($dataLama('npwp'))->hintColor('warning'),
                    Select::make('status_ptkp_id')
                        ->label('Status PTKP')
                        ->options(fn (Get $get): array => $masterAktif(StatusPtkp::class, 'status_ptkp_id')($get)->orderBy('id')->pluck('kode', 'id')->all())
                        ->required()
                        ->hint($dataLama('status_ptkp_id'))->hintColor('warning'),
                ]),
            Section::make('Pengganti')
                ->description('Peserta batal dari desa yang sama di pelatihan ini. Kosongkan jika peserta ini bukan pengganti.')
                ->visible(fn (Get $get): bool => $this->kandidatPengganti($record, $get('desa_id')) !== [])
                ->schema([
                    Select::make('menggantikan_id')
                        ->label('Menggantikan')
                        ->options(fn (Get $get): array => $this->kandidatPengganti($record, $get('desa_id')))
                        ->helperText('Pengganti otomatis masuk kelas peserta yang digantikan.'),
                ]),
            Textarea::make('catatan_panitia')
                ->label('Catatan panitia')
                ->helperText('Opsional, misal data yang dirapikan.')
                ->rows(2),
        ];
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

    private function tautanBerkas(PelatihanPeserta $record): string
    {
        return collect(['ktp' => 'KTP', 'foto' => 'Pas foto', 'surat-tugas' => 'Surat tugas'])
            ->map(fn (string $label, string $jenis): string => sprintf(
                '<a href="%s" target="_blank" rel="noopener" class="underline">%s</a>',
                e(route('berkas.pendaftaran', [$record, $jenis])),
                $label,
            ))
            ->prepend('Buka berkas:')
            ->implode(' ');
    }
}
