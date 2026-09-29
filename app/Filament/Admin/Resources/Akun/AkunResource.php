<?php

namespace App\Filament\Admin\Resources\Akun;

use App\Enums\Peran;
use App\Filament\Admin\Resources\Akun\Pages\ManageAkun;
use App\Models\User;
use App\Services\AkunService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Akun internal BBPD (FR-AUTH-04–05). Akun tidak dihapus, hanya dinonaktifkan.
 */
class AkunResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'akun';

    protected static ?string $modelLabel = 'akun';

    protected static ?string $pluralModelLabel = 'akun';

    protected static ?string $navigationLabel = 'Akun';

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label('Username')
                    ->helperText(AkunService::PESAN_USERNAME)
                    ->required()
                    ->maxLength(50)
                    ->regex(AkunService::POLA_USERNAME)
                    ->unique(),
                TextInput::make('email')
                    ->label('Email')
                    ->helperText('Boleh dikosongkan.')
                    ->email()
                    ->maxLength(255)
                    ->unique(),
                Select::make('peran')
                    ->label('Peran')
                    ->options(Peran::class)
                    ->default(Peran::Admin)
                    ->required(),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed()
                    ->visibleOn('create'),
                TextInput::make('password_confirmation')
                    ->label('Ulangi kata sandi')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false)
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                TextColumn::make('username')->label('Username')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->placeholder('–')->searchable(),
                TextColumn::make('peran')
                    ->label('Peran')
                    ->badge()
                    ->state(fn (User $record): ?Peran => self::peran($record)),
                TextColumn::make('is_aktif')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('j M Y')->sortable(),
            ])
            ->defaultSort('username')
            ->filters([
                TernaryFilter::make('is_aktif')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (User $record): array => [
                        'username' => $record->username,
                        'email' => $record->email,
                        'peran' => self::peran($record),
                    ])
                    ->using(fn (User $record, array $data, Action $action) => self::jalankan($action, fn () => app(AkunService::class)->ubah($record, auth()->user(), $data))),
                Action::make('aturUlangKataSandi')
                    ->label('Atur ulang kata sandi')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->modalDescription('Pastikan identitas pemilik akun sebelum mengatur ulang kata sandinya.')
                    ->schema([
                        TextInput::make('password')->label('Kata sandi baru')->password()->revealable()->required()->minLength(8)->confirmed(),
                        TextInput::make('password_confirmation')->label('Ulangi kata sandi baru')->password()->revealable()->required()->dehydrated(false),
                    ])
                    ->authorize('update')
                    ->action(function (User $record, array $data, Action $action): void {
                        self::jalankan($action, fn () => app(AkunService::class)->aturUlangKataSandi($record, $data['password']));
                        Notification::make()->title('Kata sandi diperbarui')->success()->send();
                    }),
                Action::make('nonaktifkan')
                    ->label('Nonaktifkan')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Akun tidak dapat login lagi sampai diaktifkan kembali. Riwayat aktivitasnya tetap tersimpan.')
                    ->authorize('update')
                    ->visible(fn (User $record): bool => $record->is_aktif && ! $record->is(auth()->user()))
                    ->action(function (User $record, Action $action): void {
                        self::jalankan($action, fn () => app(AkunService::class)->nonaktifkan($record, auth()->user()));
                        Notification::make()->title('Akun dinonaktifkan')->success()->send();
                    }),
                Action::make('aktifkan')
                    ->label('Aktifkan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->hidden(fn (User $record): bool => $record->is_aktif)
                    ->action(function (User $record): void {
                        app(AkunService::class)->aktifkan($record);
                        Notification::make()->title('Akun diaktifkan')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAkun::route('/'),
        ];
    }

    public static function peran(User $user): ?Peran
    {
        return Peran::tryFrom((string) $user->roles->first()?->name);
    }

    /**
     * Menjalankan AkunService; aturan yang ditolak service ditampilkan sebagai
     * notifikasi dan modal tetap terbuka.
     */
    public static function jalankan(Action $action, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Perubahan akun ditolak')
                ->body(Arr::first(Arr::flatten($exception->errors())))
                ->danger()
                ->send();

            $action->halt();
        }

        return null;
    }
}
