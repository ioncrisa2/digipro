<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfficeBankAccountResource\Pages;
use App\Models\OfficeBankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfficeBankAccountResource extends Resource
{
    protected static ?string $model = OfficeBankAccount::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Rekening Kantor';
    protected static ?string $modelLabel = 'Rekening Kantor';
    protected static ?string $pluralModelLabel = 'Rekening Kantor';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Rekening')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nama Bank')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Nomor Rekening')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('account_holder')
                            ->label('Nama Pemilik')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('branch')
                            ->label('Cabang')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('currency')
                            ->label('Mata Uang')
                            ->default('IDR')
                            ->maxLength(10),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status & Urutan')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Urutan')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label('No Rekening')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_holder')
                    ->label('Nama Pemilik')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch')
                    ->label('Cabang')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Mata Uang')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Detail Rekening')
                ->schema([
                    TextEntry::make('bank_name')->label('Nama Bank'),
                    TextEntry::make('account_number')->label('Nomor Rekening'),
                    TextEntry::make('account_holder')->label('Nama Pemilik'),
                    TextEntry::make('branch')->label('Cabang')->placeholder('-'),
                    TextEntry::make('currency')->label('Mata Uang'),
                    TextEntry::make('is_active')
                        ->label('Aktif')
                        ->formatStateUsing(fn (bool $state) => $state ? 'Ya' : 'Tidak'),
                    TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                    TextEntry::make('updated_at')->label('Diubah')->dateTime('d M Y H:i'),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfficeBankAccounts::route('/'),
            'create' => Pages\CreateOfficeBankAccount::route('/create'),
            'view' => Pages\ViewOfficeBankAccount::route('/{record}'),
            'edit' => Pages\EditOfficeBankAccount::route('/{record}/edit'),
        ];
    }
}
