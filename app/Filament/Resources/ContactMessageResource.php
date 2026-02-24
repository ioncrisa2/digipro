<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Komunikasi';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Pesan';

    public static function canCreate(): bool
    {
        return false; // hanya dari publik
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        'done' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('handledBy.name')->label('Handled by')->placeholder('-')->toggleable(),

                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'new' => 'New',
                    'in_progress' => 'In progress',
                    'done' => 'Done',
                    'archived' => 'Archived',
                ]),
                Tables\Filters\Filter::make('unread')
                    ->label('Unread')
                    ->query(fn ($query) => $query->whereNull('read_at')),
                Tables\Filters\SelectFilter::make('source')
                    ->options(fn () => ContactMessage::query()->distinct()->pluck('source', 'source')->filter()->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('inProgress')
                    ->label('In Progress')
                    ->icon('heroicon-o-play')
                    ->visible(fn (ContactMessage $record) => in_array($record->status, ['new', 'in_progress']))
                    ->action(function (ContactMessage $record) {
                        $record->update([
                            'status' => 'in_progress',
                            'read_at' => $record->read_at ?? now(),
                        ]);
                    }),

                Action::make('done')
                    ->label('Done')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (ContactMessage $record) => $record->status !== 'done')
                    ->action(function (ContactMessage $record) {
                        $record->update([
                            'status' => 'done',
                            'read_at' => $record->read_at ?? now(),
                            'handled_at' => now(),
                            'handled_by' => auth()->id(),
                        ]);
                    }),

                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn (ContactMessage $record) => $record->status !== 'archived')
                    ->action(fn (ContactMessage $record) => $record->update(['status' => 'archived'])),

                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Message')->schema([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('subject')->placeholder('-'),
                TextEntry::make('message')->columnSpanFull(),
            ])->columns(2),

            Section::make('Tracking')->schema([
                TextEntry::make('status'),
                TextEntry::make('read_at')->dateTime('d M Y H:i')->placeholder('-'),
                TextEntry::make('handled_at')->dateTime('d M Y H:i')->placeholder('-'),
                TextEntry::make('handledBy.name')->label('Handled by')->placeholder('-'),
                TextEntry::make('source')->placeholder('-'),
                TextEntry::make('ip_address')->label('IP')->placeholder('-'),
                TextEntry::make('user_agent')->placeholder('-')->columnSpanFull(),
                TextEntry::make('created_at')->dateTime('d M Y H:i'),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'view'  => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
