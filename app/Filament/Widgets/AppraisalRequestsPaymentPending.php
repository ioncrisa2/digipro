<?php

namespace App\Filament\Widgets;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Resources\AppraisalRequestResource;
use App\Models\AppraisalRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AppraisalRequestsPaymentPending extends TableWidget
{
    protected static ?string $heading = 'Menunggu Pembayaran';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return AppraisalRequest::query()
            ->where('status', AppraisalStatusEnum::ContractSigned)
            ->with('user')
            ->latest('updated_at');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('request_number')
                ->label('No. Permohonan')
                ->searchable(),

            Tables\Columns\TextColumn::make('user.name')
                ->label('Pemohon')
                ->default('-')
                ->searchable(),

            Tables\Columns\TextColumn::make('fee_total')
                ->label('Total Fee')
                ->money('idr')
                ->sortable(),

            Tables\Columns\TextColumn::make('offer_validity_days')
                ->label('Masa Berlaku')
                ->suffix(' hari')
                ->placeholder('-'),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Update')
                ->since()
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view')
                ->label('Lihat')
                ->icon('heroicon-o-eye')
                ->url(fn (AppraisalRequest $record) => AppraisalRequestResource::getUrl('view', ['record' => $record])),
        ];
    }
}
