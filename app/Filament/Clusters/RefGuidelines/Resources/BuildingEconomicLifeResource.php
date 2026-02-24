<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\GuidelineSet;
use Filament\Resources\Resource;
use App\Models\BuildingEconomicLife;
use App\Filament\Clusters\RefGuidelines;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\RefGuidelines\Resources\BuildingEconomicLifeResource\Pages;
use App\Filament\Clusters\RefGuidelines\Resources\BuildingEconomicLifeResource\RelationManagers;

class BuildingEconomicLifeResource extends Resource
{
    protected static ?string $model = BuildingEconomicLife::class;
    protected static ?string $cluster = RefGuidelines::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationLabel = 'Building Economic Life';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('guideline_item_id')
                ->label('Guideline Set')
                ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('year', GuidelineSet::find($state)?->year);
                }),

            Forms\Components\TextInput::make('year')
                ->required()
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100),

            Forms\Components\TextInput::make('category')->required()->maxLength(255),
            Forms\Components\TextInput::make('sub_category')->maxLength(255),
            Forms\Components\TextInput::make('building_type')->maxLength(255),
            Forms\Components\TextInput::make('building_class')->maxLength(255),

            Forms\Components\TextInput::make('storey_min')->numeric(),
            Forms\Components\TextInput::make('storey_max')->numeric(),

            Forms\Components\TextInput::make('economic_life')
                ->required()
                ->numeric()
                ->minValue(1),
        ])->columns(2);
    }

   public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guideline_item_id')->label('Guideline Set ID')->sortable(),
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\TextColumn::make('category')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sub_category')->toggleable(),
                Tables\Columns\TextColumn::make('building_class')->toggleable(),
                Tables\Columns\TextColumn::make('economic_life')->label('Economic Life')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guideline_item_id')
                    ->label('Guideline Set')
                    ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('year')
                    ->options(fn () => BuildingEconomicLife::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuildingEconomicLives::route('/'),
            'create' => Pages\CreateBuildingEconomicLife::route('/create'),
            'edit' => Pages\EditBuildingEconomicLife::route('/{record}/edit'),
        ];
    }
}
