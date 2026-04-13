<?php

namespace App\Services\Finance;

use App\Enums\FinanceDocumentStatusEnum;
use App\Enums\TaxIdentityTypeEnum;
use App\Enums\WithholdingTaxTypeEnum;
use App\Models\AppraisalRequest;
use App\Models\District;
use App\Models\Payment;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Storage;

class AppraisalBillingService
{
    public const DEFAULT_VAT_RATE_PERCENT = 11.0;

    public const DEFAULT_WITHHOLDING_RATE_PERCENT = 2.0;

    public function calculateFromDpp(
        int $dppAmount,
        ?float $vatRatePercent = null,
        ?string $withholdingType = null,
        ?float $withholdingRatePercent = null,
    ): array {
        $vatRate = $vatRatePercent ?? self::DEFAULT_VAT_RATE_PERCENT;
        $withholding = $withholdingType ?? WithholdingTaxTypeEnum::PPh23->value;
        $withholdingRate = $withholdingRatePercent ?? self::DEFAULT_WITHHOLDING_RATE_PERCENT;

        $vatAmount = (int) round($dppAmount * ($vatRate / 100));
        $totalAmount = $dppAmount + $vatAmount;
        $withholdingAmount = $withholding === WithholdingTaxTypeEnum::PPh23->value
            ? (int) round($dppAmount * ($withholdingRate / 100))
            : 0;
        $netAmount = max(0, $totalAmount - $withholdingAmount);

        return [
            'billing_dpp_amount' => $dppAmount,
            'billing_vat_rate_percent' => $vatRate,
            'billing_vat_amount' => $vatAmount,
            'billing_total_amount' => $totalAmount,
            'billing_withholding_tax_type' => $withholding,
            'billing_withholding_tax_rate_percent' => $withholdingRate,
            'billing_withholding_tax_amount' => $withholdingAmount,
            'billing_net_amount' => $netAmount,
        ];
    }

    public function deriveFromGross(
        int $grossAmount,
        ?float $vatRatePercent = null,
        ?string $withholdingType = null,
        ?float $withholdingRatePercent = null,
    ): array {
        $vatRate = $vatRatePercent ?? self::DEFAULT_VAT_RATE_PERCENT;
        $grossDivisor = 100 + $vatRate;
        $dppAmount = (int) round(($grossAmount * 100) / $grossDivisor);
        $vatAmount = max(0, $grossAmount - $dppAmount);
        $withholding = $withholdingType ?? WithholdingTaxTypeEnum::PPh23->value;
        $withholdingRate = $withholdingRatePercent ?? self::DEFAULT_WITHHOLDING_RATE_PERCENT;
        $withholdingAmount = $withholding === WithholdingTaxTypeEnum::PPh23->value
            ? (int) round($dppAmount * ($withholdingRate / 100))
            : 0;

        return [
            'billing_dpp_amount' => $dppAmount,
            'billing_vat_rate_percent' => $vatRate,
            'billing_vat_amount' => $vatAmount,
            'billing_total_amount' => $grossAmount,
            'billing_withholding_tax_type' => $withholding,
            'billing_withholding_tax_rate_percent' => $withholdingRate,
            'billing_withholding_tax_amount' => $withholdingAmount,
            'billing_net_amount' => max(0, $grossAmount - $withholdingAmount),
        ];
    }

    public function appraisalAttributesFromDpp(int $dppAmount, ?User $user = null, array $overrides = []): array
    {
        $amounts = $this->calculateFromDpp(
            $dppAmount,
            $overrides['billing_vat_rate_percent'] ?? null,
            $overrides['billing_withholding_tax_type'] ?? null,
            $overrides['billing_withholding_tax_rate_percent'] ?? null,
        );

        $snapshot = $this->snapshotFromUser($user);
        $payload = array_merge($amounts, $snapshot, [
            'finance_document_status' => FinanceDocumentStatusEnum::Draft->value,
            'fee_total' => $amounts['billing_total_amount'],
        ]);

        return array_merge($payload, array_filter(
            $overrides,
            static fn (mixed $value): bool => $value !== null
        ));
    }

    public function snapshotFromUser(?User $user): array
    {
        if (! $user) {
            return [
                'finance_billing_name' => null,
                'finance_billing_address' => null,
                'finance_tax_identity_type' => null,
                'finance_tax_identity_number' => null,
                'finance_billing_email' => null,
            ];
        }

        $taxIdentityType = null;
        $taxIdentityNumber = null;

        if (filled($user->billing_npwp)) {
            $taxIdentityType = TaxIdentityTypeEnum::NPWP->value;
            $taxIdentityNumber = (string) $user->billing_npwp;
        } elseif (filled($user->billing_nik)) {
            $taxIdentityType = TaxIdentityTypeEnum::NIK->value;
            $taxIdentityNumber = (string) $user->billing_nik;
        }

        return [
            'finance_billing_name' => $user->billing_recipient_name ?: $user->name,
            'finance_billing_address' => $this->resolveBillingAddress($user),
            'finance_tax_identity_type' => $taxIdentityType,
            'finance_tax_identity_number' => $taxIdentityNumber,
            'finance_billing_email' => $user->billing_email ?: $user->email,
        ];
    }

    public function summary(AppraisalRequest $record, ?Payment $payment = null): array
    {
        $amounts = $this->resolveAmounts($record);
        $paymentRecord = $payment ?? $record->payments->sortByDesc('id')->first();

        return array_merge($amounts, [
            'nilai_jasa_dpp' => $amounts['billing_dpp_amount'],
            'tarif_ppn_persen' => $amounts['billing_vat_rate_percent'],
            'nilai_ppn' => $amounts['billing_vat_amount'],
            'total_tagihan' => $amounts['billing_total_amount'],
            'jenis_pph_dipotong' => $amounts['billing_withholding_tax_type'],
            'jenis_pph_dipotong_label' => $this->withholdingTaxTypeLabel($amounts['billing_withholding_tax_type']),
            'tarif_pph_persen' => $amounts['billing_withholding_tax_rate_percent'],
            'nilai_pph_dipotong' => $amounts['billing_withholding_tax_amount'],
            'total_transfer_customer' => $amounts['billing_net_amount'],
            'nama_tagihan' => $record->finance_billing_name,
            'alamat_tagihan' => $record->finance_billing_address,
            'jenis_identitas_pajak' => $record->finance_tax_identity_type,
            'jenis_identitas_pajak_label' => $this->taxIdentityTypeLabel($record->finance_tax_identity_type),
            'nomor_identitas_pajak' => $record->finance_tax_identity_number,
            'email_tagihan' => $record->finance_billing_email,
            'nomor_invoice' => $this->invoiceNumber($record, $paymentRecord),
            'tanggal_invoice' => optional($record->billing_invoice_date)->toDateString(),
            'nomor_faktur_pajak' => $record->tax_invoice_number,
            'tanggal_faktur_pajak' => optional($record->tax_invoice_date)->toDateString(),
            'nomor_bukti_potong' => $record->withholding_receipt_number,
            'tanggal_bukti_potong' => optional($record->withholding_receipt_date)->toDateString(),
            'status_dokumen_keuangan' => $record->finance_document_status?->value ?? $record->finance_document_status ?? FinanceDocumentStatusEnum::Draft->value,
            'status_dokumen_keuangan_label' => $this->financeDocumentStatusLabel($record->finance_document_status?->value ?? $record->finance_document_status),
            'dokumen_invoice_url' => $this->publicDocumentUrl($record->billing_invoice_file_path),
            'dokumen_faktur_pajak_url' => $this->publicDocumentUrl($record->tax_invoice_file_path),
            'dokumen_bukti_potong_url' => $this->publicDocumentUrl($record->withholding_receipt_file_path),
        ]);
    }

    public function financeDocumentStatusOptions(): array
    {
        return array_map(
            static fn (FinanceDocumentStatusEnum $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            FinanceDocumentStatusEnum::cases()
        );
    }

    public function withholdingTaxTypeOptions(): array
    {
        return array_map(
            static fn (WithholdingTaxTypeEnum $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            WithholdingTaxTypeEnum::cases()
        );
    }

    public function taxIdentityTypeOptions(): array
    {
        return array_map(
            static fn (TaxIdentityTypeEnum $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            TaxIdentityTypeEnum::cases()
        );
    }

    public function financeDocumentStatusLabel(mixed $status): string
    {
        return FinanceDocumentStatusEnum::tryFrom((string) $this->enumValue($status))?->label()
            ?? FinanceDocumentStatusEnum::Draft->label();
    }

    public function withholdingTaxTypeLabel(mixed $type): string
    {
        return WithholdingTaxTypeEnum::tryFrom((string) $this->enumValue($type))?->label()
            ?? WithholdingTaxTypeEnum::PPh23->label();
    }

    public function taxIdentityTypeLabel(mixed $type): ?string
    {
        $value = $this->enumValue($type);

        return $value === null ? null : TaxIdentityTypeEnum::tryFrom((string) $value)?->label();
    }

    public function invoiceNumber(AppraisalRequest $record, ?Payment $payment = null): string
    {
        if (filled($record->billing_invoice_number)) {
            return (string) $record->billing_invoice_number;
        }

        $metadataInvoice = data_get($payment?->metadata, 'invoice_number');
        if (filled($metadataInvoice)) {
            return (string) $metadataInvoice;
        }

        $sequence = str_pad((string) ($payment?->id ?? $record->id), 5, '0', STR_PAD_LEFT);

        return 'INV-' . now()->format('Y') . '-' . $sequence;
    }

    private function resolveAmounts(AppraisalRequest $record): array
    {
        if ($record->billing_dpp_amount !== null && $record->billing_total_amount !== null) {
            return [
                'billing_dpp_amount' => (int) $record->billing_dpp_amount,
                'billing_vat_rate_percent' => (float) ($record->billing_vat_rate_percent ?? self::DEFAULT_VAT_RATE_PERCENT),
                'billing_vat_amount' => (int) ($record->billing_vat_amount ?? 0),
                'billing_total_amount' => (int) ($record->billing_total_amount ?? 0),
                'billing_withholding_tax_type' => $record->billing_withholding_tax_type?->value ?? $record->billing_withholding_tax_type ?? WithholdingTaxTypeEnum::PPh23->value,
                'billing_withholding_tax_rate_percent' => (float) ($record->billing_withholding_tax_rate_percent ?? self::DEFAULT_WITHHOLDING_RATE_PERCENT),
                'billing_withholding_tax_amount' => (int) ($record->billing_withholding_tax_amount ?? 0),
                'billing_net_amount' => (int) ($record->billing_net_amount ?? 0),
            ];
        }

        return $this->deriveFromGross(
            (int) ($record->fee_total ?? 0),
            self::DEFAULT_VAT_RATE_PERCENT,
            WithholdingTaxTypeEnum::PPh23->value,
            self::DEFAULT_WITHHOLDING_RATE_PERCENT,
        );
    }

    private function resolveBillingAddress(User $user): ?string
    {
        $parts = array_filter([
            $this->stringOrNull($user->billing_address_detail),
            $this->locationName(Province::class, $user->billing_province_id),
            $this->locationName(Regency::class, $user->billing_regency_id),
            $this->locationName(District::class, $user->billing_district_id),
            $this->locationName(Village::class, $user->billing_village_id),
            $this->stringOrNull($user->billing_postal_code),
        ]);

        if (empty($parts)) {
            return $this->stringOrNull($user->billing_address)
                ?? $this->stringOrNull($user->address);
        }

        return implode(', ', $parts);
    }

    private function publicDocumentUrl(?string $path): ?string
    {
        if (! filled($path) || ! Storage::disk('public')->exists((string) $path)) {
            return null;
        }

        return Storage::disk('public')->url((string) $path);
    }

    private function locationName(string $modelClass, ?string $id): ?string
    {
        if (! filled($id)) {
            return null;
        }

        return $modelClass::query()->whereKey($id)->value('name');
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
