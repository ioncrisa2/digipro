<?php

namespace App\Services\Customer\Payloads;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Support\EnumPresenter;

class AppraisalPayloadFormatter
{
    use EnumPresenter;

    public function reportTypeValue(mixed $value): ?string
    {
        return $this->enumValue($value);
    }

    public function statusValue(mixed $value): ?string
    {
        return $this->enumValue($value);
    }

    public function enumLabel(string $enumClass, mixed $value): ?string
    {
        return $this->asEnum($enumClass, $value)?->label();
    }

    public function enumBackedValue(string $enumClass, mixed $value): ?string
    {
        return $this->asEnum($enumClass, $value)?->value ?? $this->enumValue($value);
    }

    public function headlineOrDashValue(?string $value): string
    {
        return $this->headlineOrDash($value);
    }

    public function assetTypeLegacyLabel(?string $type): string
    {
        return match ($type) {
            'house' => 'Rumah Tinggal',
            'land' => 'Tanah Kosong',
            'shophouse' => 'Ruko / Rukan',
            'warehouse' => 'Gudang / Pabrik',
            default => $this->headlineOrDash($type),
        };
    }

    public function assetTypeLabelForContract(?string $type): string
    {
        $enum = $this->asEnum(AssetTypeEnum::class, $type);

        if ($enum) {
            return $enum->label() ?? $this->assetTypeLegacyLabel($type);
        }

        return $this->assetTypeLegacyLabel($type);
    }

    public function isPhotoFileType(?string $type): bool
    {
        $type = strtolower((string) $type);

        return str_starts_with($type, 'photo_') || $type === 'photos';
    }

    public function contractDocumentTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'agreement_pdf' => 'Agreement DigiPro',
            'contract_pdf' => 'Kontrak',
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
            'doc_certs' => 'Sertifikat',
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB/PBG',
            'disclaimer_pdf' => 'Disclaimer DigiPro',
            'doc_old_report' => 'Laporan Lama',
            'invoice_pdf' => 'Invoice Pembayaran',
            'npwp' => 'NPWP',
            'representative' => 'Surat Kuasa',
            'representative_letter_pdf' => 'Surat Representatif DigiPro',
            'permission' => 'Surat Izin',
            default => $this->headlineOrDash((string) $type),
        };
    }

    public function customerRequestFileTypes(): array
    {
        return ['npwp', 'representative', 'permission', 'other_request_document'];
    }

    public function legalFinalRequestFileTypes(): array
    {
        return ['agreement_pdf', 'disclaimer_pdf', 'representative_letter_pdf'];
    }

    public function isContractAccessibleStatus(string $status): bool
    {
        return in_array($status, [
            AppraisalStatusEnum::WaitingSignature->value,
            AppraisalStatusEnum::ContractSigned->value,
            AppraisalStatusEnum::ValuationOnProgress->value,
            AppraisalStatusEnum::ValuationCompleted->value,
            AppraisalStatusEnum::PreviewReady->value,
            AppraisalStatusEnum::ReportPreparation->value,
            AppraisalStatusEnum::ReportReady->value,
            AppraisalStatusEnum::Completed->value,
        ], true);
    }

    public function timelineDateTimeString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            $ts = strtotime($value);

            return $ts ? date('Y-m-d H:i:s', $ts) : trim($value);
        }

        return null;
    }

    public function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
