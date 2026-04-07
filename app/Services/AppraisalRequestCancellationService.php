<?php

namespace App\Services;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestCancellation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalRequestCancellationService
{
    /**
     * @return array<int, string>
     */
    public function customerAllowedStatuses(): array
    {
        return [
            AppraisalStatusEnum::Submitted->value,
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::Verified->value,
            AppraisalStatusEnum::WaitingOffer->value,
            AppraisalStatusEnum::OfferSent->value,
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
        ];
    }

    public function hasOpenRequest(AppraisalRequest $record): bool
    {
        return $record->cancellationRequests()
            ->whereIn('review_status', ['pending', 'in_progress'])
            ->exists();
    }

    public function latestRequest(AppraisalRequest $record): ?AppraisalRequestCancellation
    {
        return $record->relationLoaded('latestCancellationRequest')
            ? $record->latestCancellationRequest
            : $record->latestCancellationRequest()->first();
    }

    /**
     * @return array<int, array{key:string,message:string}>
     */
    public function customerBlockers(AppraisalRequest $record, User $user): array
    {
        $blockers = [];
        $status = $this->statusValue($record);

        if (! filled($user->phone_number)) {
            $blockers[] = [
                'key' => 'missing_phone_number',
                'message' => 'Nomor telepon belum diatur. Mohon lengkapi profil terlebih dahulu.',
            ];
        }

        if (! in_array($status, $this->customerAllowedStatuses(), true)) {
            $blockers[] = [
                'key' => 'status_not_allowed',
                'message' => 'Permohonan ini tidak lagi bisa diajukan pembatalan oleh customer pada status saat ini.',
            ];
        }

        if ($status === AppraisalStatusEnum::CancellationReviewPending->value || $this->hasOpenRequest($record)) {
            $blockers[] = [
                'key' => 'open_cancellation_request',
                'message' => 'Sudah ada pengajuan pembatalan yang sedang direview admin.',
            ];
        }

        return $blockers;
    }

    public function canCustomerSubmit(AppraisalRequest $record, User $user): bool
    {
        return $this->customerBlockers($record, $user) === [];
    }

    public function submitByCustomer(AppraisalRequest $record, User $user, string $reason): AppraisalRequestCancellation
    {
        if (! $this->canCustomerSubmit($record, $user)) {
            throw new RuntimeException('Permohonan pembatalan tidak bisa diajukan pada status saat ini.');
        }

        return DB::transaction(function () use ($record, $user, $reason): AppraisalRequestCancellation {
            $cancellation = $record->cancellationRequests()->create([
                'user_id' => $user->id,
                'status_before_request' => $this->statusValue($record),
                'phone_snapshot' => (string) $user->phone_number,
                'whatsapp_snapshot' => filled($user->whatsapp_number) ? (string) $user->whatsapp_number : null,
                'reason' => trim($reason),
                'review_status' => 'pending',
            ]);

            $record->update([
                'status' => AppraisalStatusEnum::CancellationReviewPending,
            ]);

            return $cancellation->fresh(['appraisalRequest', 'user']);
        });
    }

    public function markInProgress(AppraisalRequestCancellation $cancellation, int $adminId): void
    {
        if (! in_array($cancellation->review_status, ['pending', 'in_progress'], true)) {
            throw new RuntimeException('Pengajuan pembatalan ini sudah selesai direview.');
        }

        $cancellation->update([
            'review_status' => 'in_progress',
            'contacted_at' => $cancellation->contacted_at ?? now(),
            'reviewed_by' => $adminId,
        ]);
    }

    public function approve(AppraisalRequestCancellation $cancellation, int $adminId, ?string $reviewNote = null): void
    {
        if (! in_array($cancellation->review_status, ['pending', 'in_progress'], true)) {
            throw new RuntimeException('Pengajuan pembatalan ini tidak bisa disetujui lagi.');
        }

        DB::transaction(function () use ($cancellation, $adminId, $reviewNote): void {
            $record = $cancellation->appraisalRequest()->lockForUpdate()->firstOrFail();

            $record->update([
                'status' => AppraisalStatusEnum::Cancelled,
                'contract_status' => $this->shouldCloseContract($record)
                    ? ContractStatusEnum::Cancelled
                    : $record->contract_status,
                'cancelled_by' => $adminId,
                'cancelled_at' => now(),
                'cancellation_reason' => filled($reviewNote) ? trim((string) $reviewNote) : $cancellation->reason,
            ]);

            $cancellation->update([
                'review_status' => 'approved',
                'review_note' => filled($reviewNote) ? trim((string) $reviewNote) : null,
                'contacted_at' => $cancellation->contacted_at ?? now(),
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);
        });
    }

    public function reject(AppraisalRequestCancellation $cancellation, int $adminId, string $reviewNote): void
    {
        if (! in_array($cancellation->review_status, ['pending', 'in_progress'], true)) {
            throw new RuntimeException('Pengajuan pembatalan ini tidak bisa ditolak lagi.');
        }

        DB::transaction(function () use ($cancellation, $adminId, $reviewNote): void {
            $record = $cancellation->appraisalRequest()->lockForUpdate()->firstOrFail();

            $record->update([
                'status' => $cancellation->status_before_request,
            ]);

            $cancellation->update([
                'review_status' => 'rejected',
                'review_note' => trim($reviewNote),
                'contacted_at' => $cancellation->contacted_at ?? now(),
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);
        });
    }

    private function shouldCloseContract(AppraisalRequest $record): bool
    {
        return $record->contract_status !== null
            && ($record->contract_status?->value ?? $record->contract_status) !== ContractStatusEnum::None->value;
    }

    private function statusValue(AppraisalRequest $record): string
    {
        return $record->status?->value ?? (string) $record->status;
    }
}
