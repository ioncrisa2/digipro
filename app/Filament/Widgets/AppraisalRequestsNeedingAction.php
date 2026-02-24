<?php

namespace App\Filament\Widgets;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Resources\AppraisalRequestResource;
use App\Models\AppraisalRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AppraisalRequestsNeedingAction extends TableWidget
{
    protected static ?string $heading = 'Permohonan Perlu Tindakan';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return AppraisalRequest::query()
            ->whereIn('status', [
                AppraisalStatusEnum::Submitted,
                AppraisalStatusEnum::DocsIncomplete,
                AppraisalStatusEnum::Verified,
                AppraisalStatusEnum::WaitingOffer,
            ])
            ->with('user')
            ->withCount('assets')
            ->latest('requested_at');
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

            Tables\Columns\BadgeColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state) => $this->formatStatusLabel($state))
                ->colors([
                    'info' => AppraisalStatusEnum::Submitted->value,
                    'warning' => [
                        AppraisalStatusEnum::DocsIncomplete->value,
                        AppraisalStatusEnum::WaitingOffer->value,
                    ],
                    'success' => AppraisalStatusEnum::Verified->value,
                ]),

            Tables\Columns\TextColumn::make('assets_count')
                ->label('Aset')
                ->alignCenter(),

            Tables\Columns\TextColumn::make('requested_at')
                ->label('Tanggal')
                ->dateTime('d M Y H:i')
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

    private function formatStatusLabel($state): string
    {
        if ($state instanceof AppraisalStatusEnum) {
            return $state->label();
        }

        if (is_string($state) && $state !== '') {
            return AppraisalStatusEnum::from($state)->label();
        }

        return '-';
    }
}
