<?php

namespace App\Services\Admin;

class AppraisalContractNumberService
{
    public function buildFromSequence(mixed $sequence): ?string
    {
        $raw = preg_replace('/\D+/', '', (string) $sequence);

        if ($raw === '') {
            return null;
        }

        $date = now();
        $month = str_pad((string) $date->month, 2, '0', STR_PAD_LEFT);
        $year = (string) $date->year;
        $padded = str_pad($raw, 5, '0', STR_PAD_LEFT);

        return "{$padded}/AGR/DP/{$month}/{$year}";
    }

    public function deriveMetadata(mixed $sequence): array
    {
        $contractNumber = $this->buildFromSequence($sequence);

        if ($contractNumber === null) {
            return [
                'contract_number' => null,
                'contract_office_code' => null,
                'contract_month' => null,
                'contract_year' => null,
            ];
        }

        return [
            'contract_number' => $contractNumber,
            'contract_office_code' => '0',
            'contract_month' => now()->month,
            'contract_year' => now()->year,
        ];
    }
}
