<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrivacyPolicyResource\Pages;
use App\Models\PrivacyPolicy;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Str;

class PrivacyPolicyResource extends Resource
{
    protected static ?string $model = PrivacyPolicy::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten & Legal';
    protected static ?int $navigationSort = 30;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Informasi Dokumen')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('company')
                            ->label('Penyedia Layanan')
                            ->maxLength(255),

                        TextInput::make('version')
                            ->label('Versi')
                            ->maxLength(50),

                        DatePicker::make('effective_since')
                            ->label('Berlaku Sejak'),

                        DateTimePicker::make('published_at')
                            ->label('Tanggal Publikasi')
                            ->seconds(false),

                        Toggle::make('is_active')
                            ->label('Aktifkan Dokumen')
                            ->inline(false),
                    ]),

                Section::make('Isi Kebijakan')
                    ->schema([
                        TiptapEditor::make('content_html')
                            ->label('Konten')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('version')
                    ->label('Versi')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('effective_since')
                    ->label('Berlaku Sejak')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publikasi')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Action::make('duplicate')
                    ->label('Duplikasi')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplikasi Kebijakan Privasi')
                    ->modalDescription('Salinan akan dibuat dan bisa Anda edit sebelum dipublikasikan.')
                    ->action(function (PrivacyPolicy $record) {
                        $copy = $record->replicate([
                            'is_active',
                            'published_at',
                            'created_at',
                            'updated_at',
                        ]);
                        $copy->title = Str::of($record->title)->append(' (Copy)')->toString();
                        $copy->version = $record->version ? Str::of($record->version)->append('-draft')->toString() : null;
                        $copy->is_active = false;
                        $copy->published_at = null;
                        $copy->save();

                        return redirect(static::getUrl('edit', ['record' => $copy]));
                    }),
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
            InfoSection::make('Informasi Dokumen')
                ->columns(2)
                ->schema([
                    TextEntry::make('title')->label('Judul'),
                    TextEntry::make('company')->label('Penyedia Layanan')->placeholder('-'),
                    TextEntry::make('version')->label('Versi')->placeholder('-'),
                    TextEntry::make('effective_since')->label('Berlaku Sejak')->date('d M Y')->placeholder('-'),
                    TextEntry::make('published_at')->label('Tanggal Publikasi')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('is_active')->label('Aktif')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                ]),

            InfoSection::make('Isi Kebijakan')
                ->schema([
                    TextEntry::make('content_html')
                        ->label('Konten')
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrivacyPolicies::route('/'),
            'create' => Pages\CreatePrivacyPolicy::route('/create'),
            'edit' => Pages\EditPrivacyPolicy::route('/{record}/edit'),
            'view' => Pages\ViewPrivacyPolicy::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Kebijakan Privasi';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Kebijakan Privasi';
    }
}
