<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AppraisalPaymentStatusNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Actions\Action as FilamentNotificationAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\ViewRecord;
use Spatie\Permission\Models\Role;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmManualTransfer')
                ->label('Konfirmasi Transfer Berhasil')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pembayaran Manual?')
                ->modalDescription('Status pembayaran akan diubah menjadi Dibayar (paid).')
                ->visible(fn (): bool => $this->record->method === 'manual' && $this->record->status !== 'paid')
                ->disabled(fn (): bool => blank($this->record->proof_file_path))
                ->tooltip(fn (): ?string => blank($this->record->proof_file_path)
                    ? 'Bukti pembayaran belum diunggah user.'
                    : null)
                ->action(function (): void {
                    /** @var Payment $payment */
                    $payment = $this->record;

                    if (blank($payment->proof_file_path)) {
                        FilamentNotification::make()
                            ->title('Bukti pembayaran belum ada')
                            ->body('Konfirmasi hanya bisa dilakukan setelah user mengunggah bukti pembayaran.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);

                    $appraisal = $payment->appraisalRequest;
                    $enteredValuationProcess = false;
                    if ($appraisal && (($appraisal->status?->value ?? $appraisal->status) === AppraisalStatusEnum::ContractSigned->value)) {
                        $appraisal->update([
                            'status' => AppraisalStatusEnum::ValuationOnProgress,
                        ]);
                        $enteredValuationProcess = true;
                    }

                    if ($enteredValuationProcess) {
                        $this->notifyAdminsPaymentConfirmed($payment);

                        $requestNumber = $appraisal?->request_number ?? ('REQ-' . ($appraisal?->id ?? '-'));
                        if ($appraisal?->user) {
                            $appraisal->user->notify(new AppraisalPaymentStatusNotification(
                                appraisalId: (int) $appraisal->id,
                                requestNumber: (string) $requestNumber,
                                status: 'verified',
                            ));
                        }
                    }

                    $payment->refresh();

                    FilamentNotification::make()
                        ->title('Pembayaran berhasil dikonfirmasi')
                        ->body('Status pembayaran sudah berubah menjadi Dibayar.')
                        ->success()
                        ->send();
                }),
            Action::make('rejectManualTransfer')
                ->label('Tolak Pembayaran')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Tolak Bukti Pembayaran?')
                ->modalDescription('Status pembayaran akan berubah menjadi Ditolak. User akan menerima notifikasi.')
                ->visible(fn (): bool => $this->record->method === 'manual' && in_array($this->record->status, ['pending', 'failed'], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Alasan penolakan')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    /** @var Payment $payment */
                    $payment = $this->record;
                    $reason = trim((string) ($data['reason'] ?? ''));

                    $metadata = is_array($payment->metadata) ? $payment->metadata : [];
                    $metadata['admin_rejected_reason'] = $reason;
                    $metadata['admin_rejected_at'] = now()->toDateTimeString();
                    $metadata['admin_rejected_by_user_id'] = auth()->id();

                    $payment->update([
                        'status' => 'rejected',
                        'paid_at' => null,
                        'metadata' => $metadata,
                    ]);

                    $appraisal = $payment->appraisalRequest;
                    if ($appraisal && (($appraisal->status?->value ?? $appraisal->status) === AppraisalStatusEnum::ValuationOnProgress->value)) {
                        $appraisal->update([
                            'status' => AppraisalStatusEnum::ContractSigned,
                        ]);
                    }

                    $requestNumber = $appraisal?->request_number ?? ('REQ-' . ($appraisal?->id ?? '-'));
                    if ($appraisal?->user) {
                        $appraisal->user->notify(new AppraisalPaymentStatusNotification(
                            appraisalId: (int) $appraisal->id,
                            requestNumber: (string) $requestNumber,
                            status: 'rejected',
                            reason: $reason,
                        ));
                    }

                    FilamentNotification::make()
                        ->title('Bukti pembayaran ditolak')
                        ->body('Status pembayaran sudah diubah menjadi Ditolak.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function notifyAdminsPaymentConfirmed(Payment $payment): void
    {
        $guardName = config('auth.defaults.guard', 'web');
        $roleCandidates = array_values(array_filter([
            config('filament-shield.super_admin.enabled', true)
                ? config('filament-shield.super_admin.name', 'super_admin')
                : null,
            'admin',
        ]));

        $existingRoleNames = Role::query()
            ->whereIn('name', $roleCandidates)
            ->where('guard_name', $guardName)
            ->pluck('name')
            ->values()
            ->all();

        if (empty($existingRoleNames)) {
            return;
        }

        $adminUsers = User::query()
            ->role($existingRoleNames, $guardName)
            ->whereKeyNot(auth()->id())
            ->get();

        if ($adminUsers->isEmpty()) {
            return;
        }

        $appraisal = $payment->appraisalRequest;
        $requestNumber = $appraisal?->request_number ?? ('REQ-' . ($appraisal?->id ?? '-'));
        $body = "{$requestNumber} pembayaran terkonfirmasi dan masuk Proses Valuasi Berjalan.";

        $targetUrl = null;
        if ($appraisal?->id) {
            try {
                $targetUrl = route('filament.admin.resources.appraisal-requests.view', ['record' => $appraisal->id]);
            } catch (\Throwable) {
                $targetUrl = null;
            }
        }

        FilamentNotification::make()
            ->title('Pembayaran terkonfirmasi')
            ->body($body)
            ->actions($targetUrl ? [
                FilamentNotificationAction::make('view')
                    ->label('Lihat Request')
                    ->url($targetUrl)
                    ->markAsRead(),
            ] : [])
            ->icon('heroicon-o-banknotes')
            ->sendToDatabase($adminUsers, true);
    }
}
