<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput as FormTextInput;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten & Legal';
    protected static ?int $navigationSort = 40;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Informasi FAQ')
                    ->columns(2)
                    ->schema([
                        TextInput::make('question')
                            ->label('Pertanyaan')
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('answer')
                            ->label('Jawaban')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),

                        FormTextInput::make('sort_order')
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
                Tables\Columns\TextColumn::make('question')
                    ->label('Pertanyaan')
                    ->searchable()
                    ->limit(60),

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
            InfoSection::make('FAQ')
                ->schema([
                    TextEntry::make('question')->label('Pertanyaan'),
                    TextEntry::make('answer')->label('Jawaban')->columnSpanFull(),
                    TextEntry::make('sort_order')->label('Urutan'),
                    TextEntry::make('is_active')->label('Aktif')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
            'view' => Pages\ViewFaq::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'FAQ';
    }

    public static function getPluralLabel(): ?string
    {
        return 'FAQ';
    }
}
