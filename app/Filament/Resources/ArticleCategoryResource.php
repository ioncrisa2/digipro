<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleCategoryResource\Pages;
use App\Models\ArticleCategory;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleCategoryResource extends Resource
{
    protected static ?string $model = ArticleCategory::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten';
    protected static ?int $navigationSort = 15;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Kategori')
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

                        TextInput::make('sort_order')
                            ->label('Urutan')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->inline(false)
                            ->default(true),

                        Toggle::make('show_in_nav')
                            ->label('Tampilkan di Navbar')
                            ->inline(false)
                            ->default(false)
                            ->helperText('Kategori akan muncul di navigasi blog publik.'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\IconColumn::make('show_in_nav')
                    ->label('Navbar')
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
            'index' => Pages\ListArticleCategories::route('/'),
            'create' => Pages\CreateArticleCategory::route('/create'),
            'edit' => Pages\EditArticleCategory::route('/{record}/edit'),
            'view' => Pages\ViewArticleCategory::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Kategori Artikel';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Kategori Artikel';
    }
}
