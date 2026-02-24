<?php

namespace App\Filament\Resources\AppraisalRequestResource\RelationManagers;

use App\Models\AppraisalOfferNegotiation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OfferNegotiationsRelationManager extends RelationManager
{
    protected static string $relationship = 'offerNegotiations';

    protected static ?string $title = 'Riwayat Negosiasi';

    protected static ?string $recordTitleAttribute = 'action';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('round')
                    ->label('Putaran')
                    ->formatStateUsing(fn ($state) => $state ?: '-')
                    ->badge()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('action')
                    ->label('Aksi')
                    ->formatStateUsing(fn (?string $state) => $this->actionLabel($state))
                    ->colors([
                        'warning' => ['counter_request'],
                        'success' => ['accept_offer', 'contract_sign_mock'],
                        'danger' => ['cancel_request'],
                        'info' => ['offer_sent', 'offer_revised'],
                    ]),

                Tables\Columns\TextColumn::make('offered_fee')
                    ->label('Fee Penawaran')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('expected_fee')
                    ->label('Fee Diharapkan')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('selected_fee')
                    ->label('Fee Dipilih')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->wrap()
                    ->limit(90)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        'counter_request' => 'Keberatan Fee',
                        'accept_offer' => 'Setuju Penawaran',
                        'contract_sign_mock' => 'Tanda Tangan Kontrak (Mock)',
                        'cancel_request' => 'Batalkan Permohonan',
                        'offer_sent' => 'Penawaran Dikirim',
                        'offer_revised' => 'Revisi Penawaran',
                    ])
                    ->native(false),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    private function actionLabel(?string $action): string
    {
        return match ($action) {
            'counter_request' => 'Keberatan Fee',
            'accept_offer' => 'Setuju Penawaran',
            'contract_sign_mock' => 'Tanda Tangan Kontrak (Mock)',
            'cancel_request' => 'Batalkan Permohonan',
            'offer_sent' => 'Penawaran Dikirim',
            'offer_revised' => 'Revisi Penawaran',
            default => $action ?: '-',
        };
    }
}
