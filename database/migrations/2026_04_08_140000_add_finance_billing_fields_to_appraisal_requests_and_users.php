<?php

use App\Enums\FinanceDocumentStatusEnum;
use App\Enums\TaxIdentityTypeEnum;
use App\Enums\WithholdingTaxTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('billing_npwp', 40)->nullable()->after('billing_address_detail');
            $table->string('billing_nik', 40)->nullable()->after('billing_npwp');
            $table->string('billing_email')->nullable()->after('billing_nik');
        });

        Schema::table('appraisal_requests', function (Blueprint $table): void {
            $table->bigInteger('billing_dpp_amount')->nullable()->after('fee_total');
            $table->decimal('billing_vat_rate_percent', 5, 2)->nullable()->after('billing_dpp_amount');
            $table->bigInteger('billing_vat_amount')->nullable()->after('billing_vat_rate_percent');
            $table->bigInteger('billing_total_amount')->nullable()->after('billing_vat_amount');
            $table->string('billing_withholding_tax_type', 40)->nullable()->after('billing_total_amount');
            $table->decimal('billing_withholding_tax_rate_percent', 5, 2)->nullable()->after('billing_withholding_tax_type');
            $table->bigInteger('billing_withholding_tax_amount')->nullable()->after('billing_withholding_tax_rate_percent');
            $table->bigInteger('billing_net_amount')->nullable()->after('billing_withholding_tax_amount');
            $table->string('finance_billing_name')->nullable()->after('billing_net_amount');
            $table->text('finance_billing_address')->nullable()->after('finance_billing_name');
            $table->string('finance_tax_identity_type', 20)->nullable()->after('finance_billing_address');
            $table->string('finance_tax_identity_number', 40)->nullable()->after('finance_tax_identity_type');
            $table->string('finance_billing_email')->nullable()->after('finance_tax_identity_number');
            $table->string('billing_invoice_number', 120)->nullable()->after('finance_billing_email');
            $table->date('billing_invoice_date')->nullable()->after('billing_invoice_number');
            $table->string('billing_invoice_file_path', 500)->nullable()->after('billing_invoice_date');
            $table->string('tax_invoice_number', 120)->nullable()->after('billing_invoice_file_path');
            $table->date('tax_invoice_date')->nullable()->after('tax_invoice_number');
            $table->string('tax_invoice_file_path', 500)->nullable()->after('tax_invoice_date');
            $table->string('withholding_receipt_number', 120)->nullable()->after('tax_invoice_file_path');
            $table->date('withholding_receipt_date')->nullable()->after('withholding_receipt_number');
            $table->string('withholding_receipt_file_path', 500)->nullable()->after('withholding_receipt_date');
            $table->string('finance_document_status', 40)->default(FinanceDocumentStatusEnum::Draft->value)->after('withholding_receipt_file_path');

            $table->index('finance_document_status', 'ar_finance_status_idx');
            $table->index('billing_invoice_number', 'ar_invoice_number_idx');
            $table->index('tax_invoice_number', 'ar_tax_invoice_number_idx');
            $table->index('withholding_receipt_number', 'ar_withholding_number_idx');
        });

        $users = DB::table('users')
            ->select([
                'id',
                'name',
                'email',
                'address',
                'billing_address',
                'billing_recipient_name',
                'billing_province_id',
                'billing_regency_id',
                'billing_district_id',
                'billing_village_id',
                'billing_postal_code',
                'billing_address_detail',
            ])
            ->get()
            ->keyBy('id');

        $provinceNames = DB::table('provinces')->pluck('name', 'id');
        $regencyNames = DB::table('regencies')->pluck('name', 'id');
        $districtNames = DB::table('districts')->pluck('name', 'id');
        $villageNames = DB::table('villages')->pluck('name', 'id');

        DB::table('appraisal_requests')
            ->orderBy('id')
            ->select(['id', 'user_id', 'fee_total'])
            ->chunkById(100, function ($rows) use ($users, $provinceNames, $regencyNames, $districtNames, $villageNames): void {
                foreach ($rows as $row) {
                    $grossAmount = (int) ($row->fee_total ?? 0);
                    $vatRate = 11.0;
                    $dppAmount = $grossAmount > 0 ? (int) round(($grossAmount * 100) / (100 + $vatRate)) : 0;
                    $vatAmount = max(0, $grossAmount - $dppAmount);
                    $withholdingAmount = (int) round($dppAmount * 0.02);
                    $netAmount = max(0, $grossAmount - $withholdingAmount);
                    $user = $users->get($row->user_id);

                    $addressParts = array_filter([
                        filled($user?->billing_address_detail) ? trim((string) $user->billing_address_detail) : null,
                        $user?->billing_province_id ? $provinceNames->get($user->billing_province_id) : null,
                        $user?->billing_regency_id ? $regencyNames->get($user->billing_regency_id) : null,
                        $user?->billing_district_id ? $districtNames->get($user->billing_district_id) : null,
                        $user?->billing_village_id ? $villageNames->get($user->billing_village_id) : null,
                        filled($user?->billing_postal_code) ? trim((string) $user->billing_postal_code) : null,
                    ]);

                    $financeBillingAddress = ! empty($addressParts)
                        ? implode(', ', $addressParts)
                        : (filled($user?->billing_address)
                            ? trim((string) $user->billing_address)
                            : (filled($user?->address) ? trim((string) $user->address) : null));

                    DB::table('appraisal_requests')
                        ->where('id', $row->id)
                        ->update([
                            'billing_dpp_amount' => $grossAmount > 0 ? $dppAmount : null,
                            'billing_vat_rate_percent' => $grossAmount > 0 ? $vatRate : null,
                            'billing_vat_amount' => $grossAmount > 0 ? $vatAmount : null,
                            'billing_total_amount' => $grossAmount > 0 ? $grossAmount : null,
                            'billing_withholding_tax_type' => $grossAmount > 0 ? WithholdingTaxTypeEnum::PPh23->value : null,
                            'billing_withholding_tax_rate_percent' => $grossAmount > 0 ? 2.0 : null,
                            'billing_withholding_tax_amount' => $grossAmount > 0 ? $withholdingAmount : null,
                            'billing_net_amount' => $grossAmount > 0 ? $netAmount : null,
                            'finance_billing_name' => $user?->billing_recipient_name ?: $user?->name,
                            'finance_billing_address' => $financeBillingAddress,
                            'finance_billing_email' => $user?->email,
                            'finance_document_status' => FinanceDocumentStatusEnum::Draft->value,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table): void {
            $table->dropIndex('ar_finance_status_idx');
            $table->dropIndex('ar_invoice_number_idx');
            $table->dropIndex('ar_tax_invoice_number_idx');
            $table->dropIndex('ar_withholding_number_idx');

            $table->dropColumn([
                'billing_dpp_amount',
                'billing_vat_rate_percent',
                'billing_vat_amount',
                'billing_total_amount',
                'billing_withholding_tax_type',
                'billing_withholding_tax_rate_percent',
                'billing_withholding_tax_amount',
                'billing_net_amount',
                'finance_billing_name',
                'finance_billing_address',
                'finance_tax_identity_type',
                'finance_tax_identity_number',
                'finance_billing_email',
                'billing_invoice_number',
                'billing_invoice_date',
                'billing_invoice_file_path',
                'tax_invoice_number',
                'tax_invoice_date',
                'tax_invoice_file_path',
                'withholding_receipt_number',
                'withholding_receipt_date',
                'withholding_receipt_file_path',
                'finance_document_status',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_npwp',
                'billing_nik',
                'billing_email',
            ]);
        });
    }
};
