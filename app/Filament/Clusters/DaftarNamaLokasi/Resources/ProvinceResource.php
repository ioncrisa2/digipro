<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Province;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Filament\Clusters\DaftarNamaLokasi;
use App\Filament\Resources\ProvinceResource\Pages;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource\Pages\EditProvince;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource\Pages\ListProvinces;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource\Pages\CreateProvince;

class ProvinceResource extends Resource
{
    protected static ?string $model = Province::class;
    protected static ?string $label = "Province List";
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = DaftarNamaLokasi::class;
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->label('Code')
                    ->required()
                    ->maxLength(2)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn($record) => filled($record))
                    ->dehydrated(fn($record) => blank($record)),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('regencies_count')->counts('regencies')->label('Regencies')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProvinces::route('/'),
            'create' => CreateProvince::route('/create'),
            'edit' => EditProvince::route('/{record}/edit'),
        ];
    }
}
