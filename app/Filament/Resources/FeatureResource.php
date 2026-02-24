<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeatureResource\Pages;
use App\Models\Feature;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten & Legal';
    protected static ?int $navigationSort = 60;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Informasi Fitur')
                    ->columns(2)
                    ->schema([
                        Select::make('icon')
                            ->label('Icon')
                            ->options([
                                'TrendingUp' => 'TrendingUp',
                                'Zap' => 'Zap',
                                'ShieldCheck' => 'ShieldCheck',
                                'Smartphone' => 'Smartphone',
                                'CheckCircle2' => 'CheckCircle2',
                                'Star' => 'Star',
                            ])
                            ->searchable()
                            ->placeholder('Pilih icon'),

                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->label('Urutan')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->inline(false)
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Icon')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Fitur')
                ->schema([
                    TextEntry::make('title')->label('Judul'),
                    TextEntry::make('icon')->label('Icon')->placeholder('-'),
                    TextEntry::make('description')->label('Deskripsi')->columnSpanFull(),
                    TextEntry::make('sort_order')->label('Urutan'),
                    TextEntry::make('is_active')->label('Aktif')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit' => Pages\EditFeature::route('/{record}/edit'),
            'view' => Pages\ViewFeature::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Fitur';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Fitur';
    }
}
