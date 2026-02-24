<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'User Terdaftar';

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $superAdminRole = (string) config('filament-shield.super_admin.name', 'super_admin');

        return $user->hasRole($superAdminRole);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->helperText('Wajib saat create. Kosongkan saat edit jika tidak ingin mengubah password.'),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Terverifikasi'),
                        Forms\Components\Select::make('roles')
                            ->label('Role')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->helperText('Gunakan role "customer" untuk user portal.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(', ')
                    ->sortable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => filled($record->email_verified_at)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('verified')
                    ->label('Email Verified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Profil')
                    ->schema([
                        TextEntry::make('name')->label('Nama'),
                        TextEntry::make('email')->label('Email'),
                        TextEntry::make('email_verified_at')
                            ->label('Email Terverifikasi')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('roles.name')
                            ->label('Role')
                            ->badge()
                            ->separator(', ')
                            ->placeholder('-'),
                        TextEntry::make('created_at')->label('Terdaftar')->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d M Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
