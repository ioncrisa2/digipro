<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use App\Models\FloorIndex;
use Filament\Tables\Table;
use App\Models\GuidelineSet;
use Illuminate\Validation\Rule;
use Filament\Resources\Resource;
use App\Filament\Clusters\RefGuidelines;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\RefGuidelines\Resources\FloorIndexResource\Pages;
use App\Filament\Clusters\RefGuidelines\Resources\FloorIndexResource\RelationManagers;

class FloorIndexResource extends Resource
{
    protected static ?string $model = FloorIndex::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $cluster = RefGuidelines::class;

    protected static ?string $navigationLabel = 'Index Lantai (IL)';

    protected static ?int $navigationSort = 3;

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
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('year', GuidelineSet::find($state)?->year);
                }),

            Forms\Components\TextInput::make('year')
                ->required()
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100),

            Forms\Components\TextInput::make('building_class')
                ->label('Building Class')
                ->required()
                ->maxLength(255)
                ->default('DEFAULT'),

            Forms\Components\TextInput::make('floor_count')
                ->label('Floor Count')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(200)
                ->rules([
                    fn (Get $get, $record) => Rule::unique('ref_floor_index', 'floor_count')
                        ->where('guideline_set_id', $get('guideline_set_id'))
                        ->where('year', $get('year'))
                        ->where('building_class', $get('building_class'))
                        ->ignore($record?->id),
                ]),

            Forms\Components\TextInput::make('il_value')
                ->label('IL Value')
                ->required()
                ->numeric()
                ->rule('min:0')
                ->helperText('Contoh: 1.0000'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
         return $table
            ->deferLoading()
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guidelineSet.name')
                    ->label('Guideline Set')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('year')->sortable(),

                Tables\Columns\TextColumn::make('building_class')
                    ->label('Class')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('floor_count')
                    ->label('Floor')
                    ->sortable(),

                Tables\Columns\TextColumn::make('il_value')
                    ->label('IL')
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
                    ->options(fn () => FloorIndex::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->toArray()),

                Tables\Filters\SelectFilter::make('building_class')
                    ->label('Class')
                    ->options(fn () => FloorIndex::query()->distinct()->orderBy('building_class')->pluck('building_class', 'building_class')->toArray())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFloorIndices::route('/'),
            'create' => Pages\CreateFloorIndex::route('/create'),
            'edit' => Pages\EditFloorIndex::route('/{record}/edit'),
        ];
    }
}
