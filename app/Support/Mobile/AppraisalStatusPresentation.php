<?php

namespace App\Support\Mobile;

use App\Enums\AppraisalStatusEnum;

class AppraisalStatusPresentation
{
    public static function make(AppraisalStatusEnum|string|null $status): array
    {
        $enum = $status instanceof AppraisalStatusEnum
            ? $status
            : AppraisalStatusEnum::tryFrom((string) $status);
        $value = $enum?->value ?? (string) $status;

        return [
            'value' => $value,
            'label' => $enum?->label() ?? str($value)->headline()->toString(),
            'tone' => self::tone($value),
            'requires_action' => self::actionKey($value) !== null,
            'action_key' => self::actionKey($value),
        ];
    }

    private static function tone(string $status): string
    {
        return match ($status) {
            AppraisalStatusEnum::Draft->value => 'neutral',
            AppraisalStatusEnum::DocsIncomplete->value,
            AppraisalStatusEnum::OfferSent->value,
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::CancellationReviewPending->value => 'warning',
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value => 'success',
            AppraisalStatusEnum::Cancelled->value => 'danger',
            default => 'info',
        };
    }

    private static function actionKey(string $status): ?string
    {
        return match ($status) {
            AppraisalStatusEnum::DocsIncomplete->value => 'submit_revision',
            AppraisalStatusEnum::OfferSent->value => 'review_offer',
            AppraisalStatusEnum::WaitingSignature->value => 'sign_contract',
            AppraisalStatusEnum::ContractSigned->value => 'complete_payment',
            AppraisalStatusEnum::PreviewReady->value => 'review_preview',
            default => null,
        };
    }
}
