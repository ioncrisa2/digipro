<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\CostElement;
use App\Models\GuidelineSet;
use Filament\Resources\Resource;
use App\Filament\Clusters\RefGuidelines;
use App\Filament\Clusters\RefGuidelines\Resources\CostElementResource\Pages;


class CostElementResource extends Resource
{
    protected static ?string $model = CostElement::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = RefGuidelines::class;
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('guideline_set_id')
                ->label('Guideline Set')
                ->options(fn() => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                ->default(fn() => GuidelineSet::query()->where('is_active', true)->value('id'))
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn($state, callable $set) => $set('year', GuidelineSet::find($state)?->year)),


            Forms\Components\TextInput::make('base_region')
                ->label('Base Region')
                ->default('DKI Jakarta')
                ->disabled()
                ->dehydrated(true)
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('year')
                ->required()
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->default(fn() => GuidelineSet::query()->where('is_active', true)->value('year') ?? now()->year),

            Forms\Components\TextInput::make('group')
                ->label('Group') ->required()
                ->maxLength(255)
                ->datalist(fn() => CostElement::query()
                    ->whereNotNull('group')
                    ->where('group', '<>', '')
                    ->distinct()
                    ->orderBy('group')
                    ->limit(300)
                    ->pluck('group')
                    ->toArray()),

            Forms\Components\TextInput::make('element_code')
                ->label('Element Code')
                ->required()
                ->maxLength(255)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                    $code = trim((string) $state);

                    if ($code === '') {
                        return;
                    }

                    $last = CostElement::query()
                        ->where('element_code', $code)
                        ->orderByDesc('id')
                        ->first(['element_name']);

                    if ($last?->element_name) {
                        $set('element_name', $last->element_name);
                    }
                })
                ->datalist(fn() => CostElement::query()
                    ->whereNotNull('element_code')
                    ->where('element_code', '<>', '')
                    ->distinct()
                    ->orderBy('element_code')
                    ->limit(500)
                    ->pluck('element_code')
                    ->toArray())
                ->rules([
                    fn(Get $get, $record) => \Illuminate\Validation\Rule::unique('ref_cost_elements', 'element_code')
                        ->where('guideline_set_id', $get('guideline_set_id'))
                        ->where('year', $get('year'))
                        ->where('base_region', $get('base_region'))
                        ->where('building_type', $get('building_type'))
                        ->where('building_class', $get('building_class'))
                        ->where('storey_pattern', $get('storey_pattern'))
                        ->ignore($record?->id),
                ]),

            Forms\Components\TextInput::make('element_name')
                ->label('Element Name')
                ->required()
                ->maxLength(255)
                ->datalist(fn() => CostElement::query()
                    ->whereNotNull('element_name')
                    ->where('element_name', '<>', '')
                    ->distinct()
                    ->orderBy('element_name')
                    ->limit(500)
                    ->pluck('element_name')
                    ->toArray()),

            Forms\Components\TextInput::make('building_type')
                ->label('Building Type')
                ->maxLength(255)
                ->placeholder('Opsional')
                ->datalist(fn() => CostElement::query()
                    ->whereNotNull('building_type')
                    ->where('building_type', '<>', '')
                    ->distinct()
                    ->orderBy('building_type')
                    ->limit(200)
                    ->pluck('building_type')
                    ->toArray()),

            Forms\Components\TextInput::make('building_class')
                ->label('Building Class')
                ->maxLength(255)
                ->placeholder('Opsional')
                ->datalist(fn() => CostElement::query()
                    ->whereNotNull('building_class')
                    ->where('building_class', '<>', '')
                    ->distinct()
                    ->orderBy('building_class')
                    ->limit(200)
                    ->pluck('building_class')
                    ->toArray()),

            Forms\Components\TextInput::make('storey_pattern')
                ->label('Storey Pattern')
                ->maxLength(255)
                ->placeholder('Contoh: 1 Lantai, 2 Lantai, 1-2, 3-5, >=6 (opsional)')
                ->datalist(fn() => CostElement::query()
                    ->whereNotNull('storey_pattern')
                    ->where('storey_pattern', '<>', '')
                    ->distinct()
                    ->orderBy('storey_pattern')
                    ->limit(200)
                    ->pluck('storey_pattern')
                    ->toArray()),

            Forms\Components\TextInput::make('unit')
                ->required()
                ->default('m2')
                ->maxLength(50),

            Forms\Components\TextInput::make('unit_cost')
                ->label('Unit Cost (IDR)')
                ->required()
                ->numeric()
                ->minValue(0)
                ->helperText('Biaya per unit, contoh per m2.')
                ->dehydrateStateUsing(fn($state) => (int) $state),

            Forms\Components\KeyValue::make('spec_json')
                ->label('Spec JSON')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->addActionLabel('Tambah Spec')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('year', 'desc')
            ->columns([

                Tables\Columns\TextColumn::make('year')->sortable(),

                Tables\Columns\TextColumn::make('group')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('element_name')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('unit')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->money('IDR', true)
                    ->sortable()
                    ->visible(fn($record) => $record ? $record->value_type === 'cost' : true),


                Tables\Columns\TextColumn::make('building_type')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('building_class')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('storey_pattern')->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guideline_set_id')
                    ->label('Guideline Set')
                    ->options(fn() => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('year')
                    ->options(fn() => CostElement::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->toArray()),

                Tables\Filters\SelectFilter::make('base_region')
                    ->options(fn() => CostElement::query()->distinct()->orderBy('base_region')->pluck('base_region', 'base_region')->filter()->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('group')
                    ->label('Group')
                    ->options(fn() => CostElement::query()->distinct()->orderBy('group')->pluck('group', 'group')->filter()->toArray())
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
            'index' => Pages\ListCostElements::route('/'),
            'create' => Pages\CreateCostElement::route('/create'),
            'edit' => Pages\EditCostElement::route('/{record}/edit'),
        ];
    }
}
