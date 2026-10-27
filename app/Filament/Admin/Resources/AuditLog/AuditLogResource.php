<?php

namespace App\Filament\Admin\Resources\AuditLog;

use App\Filament\Admin\Resources\AuditLog\Pages\ListAuditLog;
use App\Filament\Admin\Resources\AuditLog\Pages\ViewAuditLog;
use App\Models\AsramaPeserta;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\SertifikatPeserta;
use App\Models\User;
use App\Support\IsianPeserta;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Audit log untuk superadmin (FR-AUD-01–03): siapa, kapan, apa, nilai lama → baru.
 * Hanya-baca; isinya ditulis otomatis oleh trait Diaudit dan pencatatan unduhan.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'audit-log';

    protected static ?string $modelLabel = 'catatan audit';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /**
     * Nama objek yang diaudit, menurut kelas model.
     */
    public const OBJEK = [
        Pelatihan::class => 'Pelatihan',
        PelatihanPeserta::class => 'Pendaftaran',
        Peserta::class => 'Peserta',
        KelasPelatihan::class => 'Kelas',
        AsramaPeserta::class => 'Penempatan kamar',
        PenggunaanKamar::class => 'Pemakaian kamar',
        SertifikatPeserta::class => 'Sertifikat',
        User::class => 'Akun',
    ];

    public const AKSI = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'ganti' => 'Diganti',
        'unduh' => 'Diunduh',
        'atur-ulang-sandi' => 'Atur ulang kata sandi',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('j M Y, H:i:s')->sortable(),
                TextColumn::make('causer.username')->label('Pengguna')->placeholder('Sistem'),
                TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::AKSI[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'unduh' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Objek')
                    ->formatStateUsing(fn (Activity $record): string => self::objek($record)),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->state(fn (Activity $record): string => self::ringkasan($record))
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('subject_type')->label('Objek')->options(self::OBJEK),
                SelectFilter::make('event')->label('Aksi')->options(self::AKSI),
                SelectFilter::make('causer_id')
                    ->label('Pengguna')
                    ->options(fn (): array => User::query()->orderBy('username')->pluck('username', 'id')->all()),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $dari) => $q->where('created_at', '>=', Carbon::parse($dari)->startOfDay()))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $sampai) => $q->where('created_at', '<=', Carbon::parse($sampai)->endOfDay()))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada catatan audit');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ringkasan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Waktu')->dateTime('j F Y, H:i:s'),
                        TextEntry::make('causer.username')->label('Pengguna')->placeholder('Sistem'),
                        TextEntry::make('event')->label('Aksi')->formatStateUsing(fn (?string $state): string => self::AKSI[$state] ?? (string) $state),
                        TextEntry::make('subject_type')->label('Objek')->formatStateUsing(fn (Activity $record): string => self::objek($record)),
                        TextEntry::make('description')->label('Keterangan')->columnSpanFull(),
                    ]),
                Section::make('Perubahan')
                    ->visible(fn (Activity $record): bool => self::perubahan($record) !== [])
                    ->schema([
                        RepeatableEntry::make('perubahan')
                            ->hiddenLabel()
                            ->state(fn (Activity $record): array => self::perubahan($record))
                            ->columns(3)
                            ->schema([
                                TextEntry::make('kolom')->label('Kolom'),
                                TextEntry::make('lama')->label('Nilai lama'),
                                TextEntry::make('baru')->label('Nilai baru')->color('warning'),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLog::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    public static function objek(Activity $activity): string
    {
        $nama = self::OBJEK[$activity->subject_type] ?? class_basename((string) $activity->subject_type);

        return $activity->subject_id ? "{$nama} #{$activity->subject_id}" : $nama;
    }

    /**
     * Kolom yang berubah, atau keterangan untuk kejadian tanpa perubahan kolom (mis. unduhan).
     */
    public static function ringkasan(Activity $activity): string
    {
        $kolom = array_column(self::perubahan($activity), 'kolom');

        return $kolom === [] ? $activity->description : implode(', ', $kolom);
    }

    /**
     * Daftar perubahan per kolom: nilai lama → nilai baru.
     *
     * @return list<array{kolom: string, lama: string, baru: string}>
     */
    public static function perubahan(Activity $activity): array
    {
        $baru = $activity->attribute_changes?->get('attributes') ?? [];
        $lama = $activity->attribute_changes?->get('old') ?? [];

        return collect(array_unique([...array_keys($baru), ...array_keys($lama)]))
            ->map(fn (string $kolom): array => [
                'kolom' => self::namaKolom($kolom),
                'lama' => self::nilai($lama[$kolom] ?? null),
                'baru' => self::nilai($baru[$kolom] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * Nama kolom yang mudah dibaca: `kelas_pelatihan_id` → "kelas pelatihan".
     */
    private static function namaKolom(string $kolom): string
    {
        return IsianPeserta::LABEL[$kolom] ?? str_replace('_', ' ', preg_replace('/_id(?=_|$)/', '', $kolom));
    }

    private static function nilai(mixed $nilai): string
    {
        return match (true) {
            $nilai === null || $nilai === '' => '–',
            is_bool($nilai) => $nilai ? 'Ya' : 'Tidak',
            is_array($nilai) => json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $nilai,
        };
    }
}
