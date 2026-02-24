<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten & Legal';
    protected static ?int $navigationSort = 50;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Informasi Testimoni')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('role')
                            ->label('Peran / Perusahaan')
                            ->maxLength(150),

                        Textarea::make('quote')
                            ->label('Testimoni')
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
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Peran')
                    ->limit(30),

                Tables\Columns\TextColumn::make('quote')
                    ->label('Testimoni')
                    ->limit(40),

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
            InfoSection::make('Testimoni')
                ->columns(2)
                ->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('role')->label('Peran')->placeholder('-'),
                    TextEntry::make('quote')->label('Testimoni')->columnSpanFull(),
                    TextEntry::make('sort_order')->label('Urutan'),
                    TextEntry::make('is_active')->label('Aktif')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
            'view' => Pages\ViewTestimonial::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Testimoni';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Testimoni';
    }
}
