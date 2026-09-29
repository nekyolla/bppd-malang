<?php

namespace App\Filament\Admin\Resources\Asrama\RelationManagers;

use App\Filament\Admin\Support\DataMaster;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class KamarRelationManager extends RelationManager
{
    protected static string $relationship = 'kamar';

    protected static ?string $title = 'Kamar';

    protected static ?string $modelLabel = 'kamar';

    protected static ?string $pluralModelLabel = 'kamar';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('no_kamar')
                    ->label('Nomor kamar')
                    ->required()
                    ->maxLength(10)
                    ->unique(modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('asrama_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('kapasitas')
                    ->label('Kapasitas')
                    ->helperText('Jumlah tempat tidur. Gender kamar ditentukan otomatis dari penghuni pertama.')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(4),
            ]);
    }

    public function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->recordTitleAttribute('no_kamar')
            ->columns([
                TextColumn::make('no_kamar')->label('Nomor kamar')->searchable()->sortable(),
                TextColumn::make('kapasitas')->label('Kapasitas')->suffix(' orang')->sortable(),
            ])
            ->defaultSort('no_kamar')
            ->headerActions([
                CreateAction::make(),
            ]), 'Belum ada kamar');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
