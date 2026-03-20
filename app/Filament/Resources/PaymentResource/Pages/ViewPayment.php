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
        return [];
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
