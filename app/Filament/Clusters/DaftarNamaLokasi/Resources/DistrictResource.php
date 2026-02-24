<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\District;
use App\Models\Province;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Clusters\DaftarNamaLokasi;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\DistrictResource\Pages;
use App\Filament\Clusters\DaftarNamaLokasi\Resources\DistrictResource\RelationManagers;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;
    protected static ?string $label = "District List";
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = DaftarNamaLokasi::class;
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->label('Code')
                    ->required()
                    ->maxLength(7)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn($record) => filled($record))
                    ->dehydrated(fn($record) => blank($record)),

                Forms\Components\Select::make('regency_id')
                    ->relationship('regency', 'name')
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
                Tables\Columns\TextColumn::make('regency.name')->label('Regency')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('regency.province.name')
                    ->label('Province')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('villages_count')->counts('villages')->label('Total Villages')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('regency_id')
                    ->label('Regency')
                    ->relationship('regency', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('province')
                    ->label('Province')
                    ->options(fn () => Province::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (blank($value)) return $query;

                        return $query->whereHas('regency', fn (Builder $q) => $q->where('province_id', $value));
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
            'index' => Pages\ListDistricts::route('/'),
            'create' => Pages\CreateDistrict::route('/create'),
            'edit' => Pages\EditDistrict::route('/{record}/edit'),
        ];
    }
}
