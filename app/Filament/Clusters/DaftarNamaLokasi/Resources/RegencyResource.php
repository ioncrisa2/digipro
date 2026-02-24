<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Regency;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Filament\Clusters\DaftarNamaLokasi;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\RegencyResource\Pages;

class RegencyResource extends Resource
{
    protected static ?string $model = Regency::class;
    protected static ?string $label = "Regency List";
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = DaftarNamaLokasi::class;
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                 Forms\Components\TextInput::make('id')
                ->label('Code')
                ->required()
                ->maxLength(4)
                ->unique(ignoreRecord: true)
                ->disabled(fn ($record) => filled($record))
                ->dehydrated(fn ($record) => blank($record)),

            Forms\Components\Select::make('province_id')
                ->relationship('province', 'name')
                ->required()
                ->searchable()
                ->preload(), // provinces kecil, aman preload

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
                Tables\Columns\TextColumn::make('province.name')->label('Province')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('districts_count')->counts('districts')->label('Total Districts')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('province_id')
                    ->label('Province')
                    ->relationship('province', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegencies::route('/'),
            'create' => Pages\CreateRegency::route('/create'),
            'edit' => Pages\EditRegency::route('/{record}/edit'),
        ];
    }
}
