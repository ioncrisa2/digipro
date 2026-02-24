<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Regency;
use App\Models\Village;
use App\Models\Province;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Clusters\DaftarNamaLokasi;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\VillageResource\Pages;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\VillageResource\RelationManagers;

class VillageResource extends Resource
{
    protected static ?string $model = Village::class;
    protected static ?string $label = "Village List";
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = DaftarNamaLokasi::class;
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                ->label('Code')
                ->required()
                ->maxLength(10)
                ->unique(ignoreRecord: true)
                ->disabled(fn ($record) => filled($record))
                ->dehydrated(fn ($record) => blank($record)),

            Forms\Components\Select::make('district_id')
                ->relationship('district', 'name')
                ->required()
                ->searchable(),

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('district.name')->label('District')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('district.regency.name')
                    ->label('Regency')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('district.regency.province.name')
                    ->label('Province')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('district_id')
                    ->label('District')
                    ->relationship('district', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('regency')
                    ->label('Regency')
                    ->options(fn () => Regency::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (blank($value)) return $query;

                        return $query->whereHas('district', fn (Builder $q) => $q->where('regency_id', $value));
                    })
                    ->searchable(),

                Tables\Filters\SelectFilter::make('province')
                    ->label('Province')
                    ->options(fn () => Province::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (blank($value)) return $query;

                        return $query->whereHas('district.regency', fn (Builder $q) => $q->where('province_id', $value));
                    })
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
            'index' => Pages\ListVillages::route('/'),
            'create' => Pages\CreateVillage::route('/create'),
            'edit' => Pages\EditVillage::route('/{record}/edit'),
        ];
    }
}
