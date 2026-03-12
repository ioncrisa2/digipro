<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use App\Filament\Clusters\RefGuidelines;
use App\Filament\Clusters\RefGuidelines\Resources\MappiRcnStandardResource\Pages;
use App\Models\GuidelineSet;
use App\Models\MappiRcnStandard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class MappiRcnStandardResource extends Resource
{
    protected static ?string $model = MappiRcnStandard::class;

    protected static ?string $navigationIcon = null;

    protected static ?string $cluster = RefGuidelines::class;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Standar RCN MAPPI';

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
                ->label('Year')
                ->numeric()
                ->required()
                ->minValue(2000)
                ->maxValue(2100),

            Forms\Components\TextInput::make('reference_region')
                ->label('Reference Region')
                ->default('DKI Jakarta')
                ->disabled()
                ->dehydrated(true)
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('building_type')
                ->label('Building Type')
                ->required()
                ->maxLength(255)
                ->datalist(fn () => MappiRcnStandard::query()
                    ->whereNotNull('building_type')
                    ->where('building_type', '<>', '')
                    ->distinct()
                    ->orderBy('building_type')
                    ->pluck('building_type')
                    ->toArray()),

            Forms\Components\TextInput::make('building_class')
                ->label('Building Class')
                ->maxLength(255)
                ->placeholder('Opsional')
                ->datalist(fn () => MappiRcnStandard::query()
                    ->whereNotNull('building_class')
                    ->where('building_class', '<>', '')
                    ->distinct()
                    ->orderBy('building_class')
                    ->pluck('building_class')
                    ->toArray()),

            Forms\Components\TextInput::make('storey_pattern')
                ->label('Storey Pattern')
                ->maxLength(255)
                ->placeholder('Contoh: 1 Lantai, 2 Lantai, 1-2, 3-5, >=6')
                ->datalist(fn () => MappiRcnStandard::query()
                    ->whereNotNull('storey_pattern')
                    ->where('storey_pattern', '<>', '')
                    ->distinct()
                    ->orderBy('storey_pattern')
                    ->pluck('storey_pattern')
                    ->toArray())
                ->rules([
                    fn (Forms\Get $get, $record) => Rule::unique('ref_mappi_rcn_standards', 'storey_pattern')
                        ->where('guideline_set_id', $get('guideline_set_id'))
                        ->where('year', $get('year'))
                        ->where('reference_region', $get('reference_region'))
                        ->where('building_type', $get('building_type'))
                        ->where('building_class', $get('building_class'))
                        ->ignore($record?->id),
                ]),

            Forms\Components\TextInput::make('rcn_value')
                ->label('RCN Value')
                ->numeric()
                ->required()
                ->minValue(0)
                ->helperText('Nilai final Total Biaya Pembangunan Baru (A + B) untuk DKI Jakarta.')
                ->dehydrateStateUsing(fn ($state) => (int) round((float) $state)),

            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guidelineSet.name')
                    ->label('Guideline')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_region')
                    ->label('Reference')
                    ->sortable(),
                Tables\Columns\TextColumn::make('building_type')
                    ->label('Building Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('building_class')
                    ->label('Building Class')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('storey_pattern')
                    ->label('Storey Pattern')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rcn_value')
                    ->label('RCN Value')
                    ->money('IDR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guideline_set_id')
                    ->label('Guideline Set')
                    ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('year')
                    ->options(fn () => MappiRcnStandard::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->toArray()),
                Tables\Filters\SelectFilter::make('building_type')
                    ->label('Building Type')
                    ->options(fn () => MappiRcnStandard::query()->distinct()->orderBy('building_type')->pluck('building_type', 'building_type')->filter()->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('building_class')
                    ->label('Building Class')
                    ->options(fn () => MappiRcnStandard::query()->distinct()->orderBy('building_class')->pluck('building_class', 'building_class')->filter()->toArray())
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
            'index' => Pages\ListMappiRcnStandards::route('/'),
            'create' => Pages\CreateMappiRcnStandard::route('/create'),
            'edit' => Pages\EditMappiRcnStandard::route('/{record}/edit'),
        ];
    }
}
