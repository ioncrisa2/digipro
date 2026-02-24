<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use App\Filament\Clusters\RefGuidelines;
use App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource\Pages;
use App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource\RelationManagers;
use App\Models\GuidelineSet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RefGuidelineSetResource extends Resource
{
    protected static ?string $model = GuidelineSet::class;
    protected static ?string $cluster = RefGuidelines::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationLabel = 'Guideline Sets';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\TextInput::make('year')
                    ->required()
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefGuidelineSets::route('/'),
            'create' => Pages\CreateRefGuidelineSet::route('/create'),
            'edit' => Pages\EditRefGuidelineSet::route('/{record}/edit'),
        ];
    }
}
