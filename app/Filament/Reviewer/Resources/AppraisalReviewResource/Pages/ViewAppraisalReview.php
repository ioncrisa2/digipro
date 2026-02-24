<?php

namespace App\Filament\Reviewer\Resources\AppraisalReviewResource\Pages;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Reviewer\Resources\AppraisalComparableResource;
use App\Filament\Reviewer\Resources\AppraisalReviewResource;
use App\Models\AppraisalRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAppraisalReview extends ViewRecord
{
    protected static string $resource = AppraisalReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openComparables')
                ->label('Kelola Data Pembanding')
                ->icon('heroicon-o-scale')
                ->url(fn (AppraisalRequest $record): string => AppraisalComparableResource::getUrl('index', [
                    'tableSearch' => $record->request_number,
                ])),

            Action::make('startReview')
                ->label('Mulai Review')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (AppraisalRequest $record): bool => $this->statusValue($record) === AppraisalStatusEnum::ContractSigned->value)
                ->action(function (AppraisalRequest $record): void {
                    $record->update([
                        'status' => AppraisalStatusEnum::ValuationOnProgress,
                    ]);

                    Notification::make()
                        ->title('Review dimulai')
                        ->body('Status request berubah menjadi Proses Valuasi Berjalan.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('finishReview')
                ->label('Finalisasi Valuasi')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (AppraisalRequest $record): bool => $this->statusValue($record) === AppraisalStatusEnum::ValuationOnProgress->value)
                ->action(function (AppraisalRequest $record): void {
                    $record->update([
                        'status' => AppraisalStatusEnum::ValuationCompleted,
                    ]);

                    Notification::make()
                        ->title('Valuasi difinalisasi')
                        ->body('Status request berubah menjadi Proses Valuasi Selesai.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
        ];
    }

    private function statusValue(AppraisalRequest $record): string
    {
        return $record->status?->value ?? (string) $record->status;
    }
}
