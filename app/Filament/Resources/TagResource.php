<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten';
    protected static ?int $navigationSort = 20;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Tag')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                $slug = $get('slug');
                                if (! $slug) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true),

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
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable()
                    ->limit(30),

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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
            'view' => Pages\ViewTag::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Tag Artikel';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Tag Artikel';
    }
}
