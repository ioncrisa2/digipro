<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use App\Models\Province;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\GuidelineSet;
use Illuminate\Validation\Rule;
use Filament\Resources\Resource;
use App\Models\ConstructionCostIndex;
use App\Filament\Clusters\RefGuidelines;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\RefGuidelines\Resources\ConstructionCostIndexResource\Pages;
use App\Filament\Clusters\RefGuidelines\Resources\ConstructionCostIndexResource\RelationManagers;

class ConstructionCostIndexResource extends Resource
{
    protected static ?int $navigationSort = 4;

    protected static ?string $model = ConstructionCostIndex::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = RefGuidelines::class;

    public static function form(Form $form): Form
    {
         return $form->schema([
            Forms\Components\Select::make('guideline_set_id')
                ->label('Guideline Set')
                ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                ->default(fn () => GuidelineSet::query()->where('is_active', true)->value('id'))
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set) => $set('year', GuidelineSet::find($state)?->year)),

            Forms\Components\TextInput::make('year')
                ->required()
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100),

            // Pilih Regency → simpan ke region_code
            Forms\Components\Select::make('region_code')
                ->label('Nama Kabupaten / Kota')
                ->relationship('regency', 'name')
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    // auto isi region_name sesuai regency terpilih
                    $set('region_name', optional(\App\Models\Regency::find($state))->name);
                })
                ->rules([
                    fn (Get $get, $record) => Rule::unique('ref_construction_cost_index', 'region_code')
                        ->where('guideline_set_id', $get('guideline_set_id'))
                        ->where('year', $get('year'))
                        ->ignore($record?->id),
                ]),

            Forms\Components\TextInput::make('region_name')
                ->label('Region Name (auto)')
                ->disabled()
                ->dehydrated() // tetap tersimpan walau disabled
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('ikk_value')
                ->label('IKK Value')
                ->required()
                ->numeric()
                ->rule('min:0')
                ->helperText('Contoh: 1.0000 atau 0.9875'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guidelineSet.name')->label('Guideline')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('year')->sortable(),

                Tables\Columns\TextColumn::make('regency.province.name')
                    ->label('Province')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('regency.name')
                    ->label('Regency')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('region_code')->label('Code')->toggleable(),
                Tables\Columns\TextColumn::make('ikk_value')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guideline_set_id')
                    ->label('Guideline Set')
                    ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('year')
                    ->options(fn () => ConstructionCostIndex::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->toArray()),

                Tables\Filters\SelectFilter::make('province')
                    ->label('Province')
                    ->options(fn () => Province::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function ($query, array $data) {
                        $prov = $data['value'] ?? null;
                        if (blank($prov)) return $query;

                        return $query->whereHas('regency', fn ($q) => $q->where('province_id', $prov));
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
            'index' => Pages\ListConstructionCostIndices::route('/'),
            'create' => Pages\CreateConstructionCostIndex::route('/create'),
            'edit' => Pages\EditConstructionCostIndex::route('/{record}/edit'),
        ];
    }
}
