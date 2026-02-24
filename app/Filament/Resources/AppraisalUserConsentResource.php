<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppraisalUserConsentResource\Pages;
use App\Models\AppraisalUserConsent;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppraisalUserConsentResource extends Resource
{
    protected static ?string $model = AppraisalUserConsent::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten & Legal';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'Persetujuan Pengguna';
    protected static ?string $modelLabel = 'Persetujuan Pengguna';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'document']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('accepted_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Pengguna')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Email')->searchable()->sortable(),
                TextColumn::make('document.title')->label('Dokumen')->toggleable(),
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('version')->label('Versi')->searchable()->sortable(),
                TextColumn::make('accepted_at')->label('Disetujui')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('ip')->label('IP')->searchable()->toggleable(),
                TextColumn::make('user_agent')->label('User Agent')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hash')->label('Hash')->limit(12)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('code')
                    ->label('Kode')
                    ->options(fn () => AppraisalUserConsent::query()->distinct()->pluck('code', 'code')->filter()->toArray()),
                Tables\Filters\SelectFilter::make('version')
                    ->label('Versi')
                    ->options(fn () => AppraisalUserConsent::query()->distinct()->pluck('version', 'version')->filter()->toArray()),
                Tables\Filters\Filter::make('accepted_at')
                    ->label('Tanggal Persetujuan')
                    ->form([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('accepted_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('accepted_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['from'])) {
                            $indicators[] = 'Dari: ' . $data['from'];
                        }

                        if (! empty($data['until'])) {
                            $indicators[] = 'Sampai: ' . $data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Pengguna')->schema([
                TextEntry::make('user.name')->label('Nama'),
                TextEntry::make('user.email')->label('Email'),
            ])->columns(2),

            Section::make('Dokumen')->schema([
                TextEntry::make('document.title')->label('Judul')->placeholder('-'),
                TextEntry::make('code')->label('Kode'),
                TextEntry::make('version')->label('Versi'),
                TextEntry::make('hash')->label('Hash')->placeholder('-'),
                TextEntry::make('accepted_at')->label('Disetujui')->dateTime('d M Y H:i'),
            ])->columns(2),

            Section::make('Teknis')->schema([
                TextEntry::make('ip')->label('IP')->placeholder('-'),
                TextEntry::make('user_agent')->label('User Agent')->placeholder('-')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisalUserConsents::route('/'),
            'view' => Pages\ViewAppraisalUserConsent::route('/{record}'),
        ];
    }
}
