<?php

namespace App\Filament\Reviewer\Resources;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Reviewer\Resources\AppraisalComparableResource;
use App\Filament\Reviewer\Resources\AppraisalReviewResource\Pages;
use App\Filament\Reviewer\Resources\AppraisalReviewResource\RelationManagers\AssetsRelationManager;
use App\Filament\Resources\AppraisalRequestResource\RelationManagers\AssetDocumentsRelationManager;
use App\Filament\Resources\AppraisalRequestResource\RelationManagers\AssetPhotosRelationManager;
use App\Models\AppraisalRequest;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppraisalReviewResource extends Resource
{
    protected static ?string $model = AppraisalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('user')
                ->withCount('assets'))
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('No. Permohonan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client_name')
                    ->label('Klien')
                    ->formatStateUsing(fn ($state, AppraisalRequest $record) => $state ?: ($record->user?->name ?? '-'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Aset')
                    ->numeric()
                    ->alignCenter(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => self::formatStatusLabel($state))
                    ->colors([
                        'warning' => AppraisalStatusEnum::ContractSigned->value,
                        'info' => AppraisalStatusEnum::ValuationOnProgress->value,
                        'success' => AppraisalStatusEnum::ValuationCompleted->value,
                    ]),

                Tables\Columns\TextColumn::make('contract_number')
                    ->label('No. Kontrak')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Tgl Permohonan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        AppraisalStatusEnum::ContractSigned->value => AppraisalStatusEnum::ContractSigned->label(),
                        AppraisalStatusEnum::ValuationOnProgress->value => AppraisalStatusEnum::ValuationOnProgress->label(),
                        AppraisalStatusEnum::ValuationCompleted->value => AppraisalStatusEnum::ValuationCompleted->label(),
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('comparables')
                    ->label('Data Pembanding')
                    ->icon('heroicon-o-scale')
                    ->url(fn (AppraisalRequest $record): string => AppraisalComparableResource::getUrl('index', [
                        'tableSearch' => $record->request_number,
                    ])),

                Tables\Actions\Action::make('startReview')
                    ->label('Mulai Review')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record): bool => self::statusValue($record) === AppraisalStatusEnum::ContractSigned->value)
                    ->action(function (AppraisalRequest $record): void {
                        $record->update([
                            'status' => AppraisalStatusEnum::ValuationOnProgress,
                        ]);

                        Notification::make()
                            ->title('Review dimulai')
                            ->body('Status request berubah menjadi Proses Valuasi Berjalan.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('finishReview')
                    ->label('Finalisasi Valuasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record): bool => self::statusValue($record) === AppraisalStatusEnum::ValuationOnProgress->value)
                    ->action(function (AppraisalRequest $record): void {
                        $record->update([
                            'status' => AppraisalStatusEnum::ValuationCompleted,
                        ]);

                        Notification::make()
                            ->title('Valuasi difinalisasi')
                            ->body('Status request berubah menjadi Proses Valuasi Selesai.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Ringkasan Request')
                ->columns(3)
                ->schema([
                    TextEntry::make('request_number')->label('No. Permohonan'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->formatStateUsing(fn ($state) => self::formatStatusLabel($state)),
                    TextEntry::make('requested_at')->label('Tanggal Permohonan')->dateTime('d M Y H:i'),
                    TextEntry::make('client_name')
                        ->label('Klien')
                        ->formatStateUsing(fn ($state, AppraisalRequest $record) => $state ?: ($record->user?->name ?? '-')),
                    TextEntry::make('contract_number')->label('No. Kontrak')->placeholder('-'),
                    TextEntry::make('fee_total')->label('Fee')->money('idr')->placeholder('-'),
                ]),

            InfoSection::make('Informasi Review')
                ->columns(2)
                ->schema([
                    TextEntry::make('notes')
                        ->label('Catatan Internal')
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('assets_count')
                        ->label('Total Aset')
                        ->state(fn (AppraisalRequest $record) => (int) $record->assets()->count()),
                    TextEntry::make('latest_payment_status')
                        ->label('Status Pembayaran Terakhir')
                        ->state(fn (AppraisalRequest $record) => self::paymentStatusLabel(
                            $record->payments()->latest('id')->value('status')
                        )),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            AssetsRelationManager::class,
            AssetDocumentsRelationManager::class,
            AssetPhotosRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [
                AppraisalStatusEnum::ContractSigned->value,
                AppraisalStatusEnum::ValuationOnProgress->value,
                AppraisalStatusEnum::ValuationCompleted->value,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisalReviews::route('/'),
            'view' => Pages\ViewAppraisalReview::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Antrian Review';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Antrian Review';
    }

    private static function statusValue(AppraisalRequest $record): string
    {
        return $record->status?->value ?? (string) $record->status;
    }

    private static function formatStatusLabel(mixed $state): string
    {
        $value = $state instanceof AppraisalStatusEnum ? $state->value : (string) $state;

        return AppraisalStatusEnum::tryFrom($value)?->label() ?? ($value !== '' ? $value : '-');
    }

    private static function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'rejected' => 'Ditolak',
            'refunded' => 'Refund',
            'pending' => 'Menunggu Verifikasi',
            default => '-',
        };
    }
}
