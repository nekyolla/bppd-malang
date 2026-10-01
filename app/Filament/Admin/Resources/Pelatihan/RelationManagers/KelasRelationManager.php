<?php

namespace App\Filament\Admin\Resources\Pelatihan\RelationManagers;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Actions\BagiAcakKelasAction;
use App\Models\KelasPelatihan;
use App\Services\KelasService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

/**
 * Kelas dalam satu pelatihan (FR-KLS-01). Simpan dan hapus lewat KelasService.
 */
class KelasRelationManager extends RelationManager
{
    protected static string $relationship = 'kelas';

    protected static ?string $title = 'Kelas';

    protected static ?string $modelLabel = 'kelas';

    protected static ?string $pluralModelLabel = 'kelas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kelas')
                    ->label('Nama kelas')
                    ->helperText('Misal A, B, C.')
                    ->required()
                    ->maxLength(KelasService::MAKS_NAMA)
                    ->default(fn (): ?string => app(KelasService::class)->namaBerikutnya($this->getOwnerRecord()))
                    ->unique(modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('pelatihan_id', $this->getOwnerRecord()->getKey())),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_kelas')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'pendaftaran as jumlah_peserta' => fn (Builder $q) => $q->where('status', '!=', StatusPendaftaran::Batal),
            ]))
            ->columns([
                TextColumn::make('nama_kelas')->label('Kelas')->sortable(),
                TextColumn::make('jumlah_peserta')->label('Peserta')->suffix(' orang')->sortable(),
            ])
            ->defaultSort('nama_kelas')
            ->headerActions([
                BagiAcakKelasAction::make(),
                CreateAction::make()
                    ->label('Buat kelas')
                    ->modalHeading('Buat kelas')
                    ->modalWidth('md')
                    ->createAnother(false)
                    ->visible(fn (): bool => $this->belumSelesai())
                    ->using(fn (array $data, Action $action): KelasPelatihan => $this->jalankan($action, fn () => app(KelasService::class)
                        ->simpan($this->getOwnerRecord(), $data['nama_kelas']))),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('md')
                    ->visible(fn (): bool => $this->belumSelesai())
                    ->using(fn (KelasPelatihan $record, array $data, Action $action): KelasPelatihan => $this->jalankan($action, fn () => app(KelasService::class)
                        ->simpan($this->getOwnerRecord(), $data['nama_kelas'], $record))),
                DeleteAction::make()
                    ->modalDescription('Hanya kelas yang belum berisi peserta dan belum memiliki presensi yang dapat dihapus.')
                    ->visible(fn (): bool => $this->belumSelesai())
                    ->using(function (KelasPelatihan $record, Action $action): bool {
                        $this->jalankan($action, fn () => app(KelasService::class)->hapus($record));

                        return true;
                    }),
            ])
            ->toolbarActions([])
            ->paginated(false)
            ->emptyStateHeading('Belum ada kelas')
            ->emptyStateDescription('Buat kelas terlebih dahulu sebelum membagi peserta.');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function belumSelesai(): bool
    {
        return $this->getOwnerRecord()->status !== StatusPelatihan::Selesai;
    }

    /**
     * Aturan yang ditolak KelasService ditampilkan sebagai notifikasi dan modal tetap terbuka.
     */
    private function jalankan(Action $action, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Kelas tidak dapat disimpan')
                ->body(Arr::first(Arr::flatten($exception->errors())))
                ->danger()
                ->send();

            $action->halt();
        }
    }
}
