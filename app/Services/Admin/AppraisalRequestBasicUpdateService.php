<?php

namespace App\Services\Admin;

use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Services\Finance\AppraisalBillingService;

class AppraisalRequestBasicUpdateService
{
    public function __construct(
        private readonly AppraisalContractNumberService $contractNumberService,
        private readonly AppraisalBillingService $billingService,
    ) {
    }

    public function update(AppraisalRequest $appraisalRequest, array $validated): void
    {
        $contractMeta = $this->contractNumberService->deriveMetadata($validated['contract_sequence'] ?? null);
        $contractDate = $this->blankToNull($validated['contract_date'] ?? null);
        $contractStatus = array_key_exists('contract_status', $validated)
            ? ($this->blankToNull($validated['contract_status']) ?? ContractStatusEnum::None->value)
            : ($appraisalRequest->contract_status?->value ?? $appraisalRequest->contract_status ?? ContractStatusEnum::None->value);

        if (($validated['contract_sequence'] ?? null) && $contractDate === null) {
            $contractDate = now()->toDateString();
        }

        $billingAttributes = $this->resolveBillingAttributes($appraisalRequest, $validated);

        $appraisalRequest->update([
            ...$billingAttributes,
            'client_name' => $this->blankToNull($validated['client_name'] ?? null),
            'report_type' => $this->blankToNull($validated['report_type'] ?? null),
            'contract_sequence' => $this->blankToNull($validated['contract_sequence'] ?? null),
            'contract_number' => $contractMeta['contract_number'],
            'contract_office_code' => $contractMeta['contract_office_code'],
            'contract_month' => $contractMeta['contract_month'],
            'contract_year' => $contractMeta['contract_year'],
            'contract_date' => $contractDate,
            'contract_status' => $contractStatus,
            'valuation_duration_days' => $this->blankToNull($validated['valuation_duration_days'] ?? null),
            'offer_validity_days' => $this->blankToNull($validated['offer_validity_days'] ?? null),
            'fee_has_dp' => false,
            'fee_dp_percent' => null,
            'user_request_note' => $this->blankToNull($validated['user_request_note'] ?? null),
            'notes' => $this->blankToNull($validated['notes'] ?? null),
        ]);
    }

    private function resolveBillingAttributes(AppraisalRequest $appraisalRequest, array $validated): array
    {
        if (array_key_exists('billing_dpp_amount', $validated) && $validated['billing_dpp_amount'] !== null) {
            return $this->billingService->appraisalAttributesFromDpp(
                (int) $validated['billing_dpp_amount'],
                $appraisalRequest->user,
            );
        }

        if (array_key_exists('fee_total', $validated) && $validated['fee_total'] !== null) {
            return $this->billingService->appraisalAttributesFromDpp(
                (int) $this->billingService->deriveFromGross((int) $validated['fee_total'])['billing_dpp_amount'],
                $appraisalRequest->user,
            );
        }

        return [];
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
