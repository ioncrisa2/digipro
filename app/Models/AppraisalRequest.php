<?php

namespace App\Models;

use App\Enums\PurposeEnum;
use App\Models\GuidelineSet;
use App\Enums\ReportTypeEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\AppraisalStatusEnum;
use App\Enums\TaxIdentityTypeEnum;
use App\Enums\WithholdingTaxTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Enums\FinanceDocumentStatusEnum;
use App\Traits\HasRequestNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AppraisalRequest extends Model
{
    use HasRequestNumber;

    protected $fillable = [
        'user_id',
        'guideline_set_id',
        'request_number',
        'purpose',
        'valuation_objective',
        'status',
        'requested_at',
        'consent_accepted_at',
        'consent_version',
        'consent_hash',
        'consent_ip',
        'consent_user_agent',
        'sertifikat_on_hand_confirmed',
        'certificate_not_encumbered_confirmed',
        'certificate_statements_accepted_at',
        'certificate_statement_ip',
        'certificate_statement_user_agent',
        'verified_at',
        'notes',
        'client_name',
        'client_address',
        'client_spk_number',
        'user_request_note',
        'contract_number',
        'contract_date',
        'contract_sequence',
        'contract_office_code',
        'contract_month',
        'contract_year',
        'contract_status',
        'report_type',
        'valuation_duration_days',
        'fee_total',
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
        'fee_has_dp',
        'fee_dp_percent',
        'offer_validity_days',
        'report_format',
        'physical_copies_count',
        'report_delivery_address',
        'report_delivery_recipient_name',
        'report_delivery_recipient_phone',
        'report_generated_at',
        'report_generated_by',
        'report_reviewer_signer_id',
        'report_public_appraiser_signer_id',
        'report_signer_snapshot',
        'report_draft_generated_at',
        'report_draft_pdf_path',
        'report_draft_pdf_size',
        'report_pdf_path',
        'report_pdf_size',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'market_preview_snapshot',
        'market_preview_version',
        'market_preview_published_at',
        'market_preview_approved_at',
        'market_preview_appeal_count',
        'market_preview_appeal_reason',
        'market_preview_appeal_submitted_at',
        'physical_report_printed_at',
        'physical_report_printed_by',
        'physical_report_shipped_at',
        'physical_report_delivered_at',
        'physical_report_tracking_number',
        'physical_report_courier',
        'physical_report_notes',
    ];

    protected $casts = [
        'requested_at'                  => 'datetime',
        'consent_accepted_at'           => 'datetime',
        'sertifikat_on_hand_confirmed'  => 'boolean',
        'certificate_not_encumbered_confirmed' => 'boolean',
        'certificate_statements_accepted_at'   => 'datetime',
        'verified_at'                   => 'datetime',
        'contract_date'                 => 'date',
        'fee_has_dp'                    => 'boolean',
        'fee_total'                     => 'integer',
        'billing_dpp_amount'            => 'integer',
        'billing_vat_rate_percent'      => 'float',
        'billing_vat_amount'            => 'integer',
        'billing_total_amount'          => 'integer',
        'billing_withholding_tax_type'  => WithholdingTaxTypeEnum::class,
        'billing_withholding_tax_rate_percent' => 'float',
        'billing_withholding_tax_amount' => 'integer',
        'billing_net_amount'            => 'integer',
        'finance_tax_identity_type'     => TaxIdentityTypeEnum::class,
        'finance_document_status'       => FinanceDocumentStatusEnum::class,
        'billing_invoice_date'          => 'date',
        'tax_invoice_date'              => 'date',
        'withholding_receipt_date'      => 'date',
        'valuation_duration_days'       => 'integer',
        'offer_validity_days'           => 'integer',
        'purpose'                       => PurposeEnum::class,
        'valuation_objective'           => ValuationObjectiveEnum::class,
        'status'                        => AppraisalStatusEnum::class,
        'contract_status'               => ContractStatusEnum::class,
        'report_type'                   => ReportTypeEnum::class,
        'market_preview_snapshot'       => 'array',
        'market_preview_version'        => 'integer',
        'market_preview_published_at'   => 'datetime',
        'market_preview_approved_at'    => 'datetime',
        'market_preview_appeal_count'   => 'integer',
        'market_preview_appeal_submitted_at' => 'datetime',
        'report_generated_at'           => 'datetime',
        'report_signer_snapshot'        => 'array',
        'report_draft_generated_at'     => 'datetime',
        'cancelled_at'                  => 'datetime',
        'physical_report_printed_at'    => 'datetime',
        'physical_report_shipped_at'    => 'datetime',
        'physical_report_delivered_at'  => 'datetime',
        'physical_copies_count'         => 'integer',
        'report_draft_pdf_size'         => 'integer',
        'report_pdf_size'               => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (! $request->guideline_set_id) {
                $guidelineSetId = GuidelineSet::query()
                    ->where('is_active', true)
                    ->value('id');

                if (! $guidelineSetId) {
                    $guidelineSetId = GuidelineSet::query()
                        ->orderByDesc('year')
                        ->orderByDesc('id')
                        ->value('id');
                }

                $request->guideline_set_id = $guidelineSetId;
            }
        });

        static::updating(function (self $request): void {
            $originalGuidelineSetId = $request->getOriginal('guideline_set_id');

            if (
                $originalGuidelineSetId !== null
                && $request->isDirty('guideline_set_id')
                && (int) $request->guideline_set_id !== (int) $originalGuidelineSetId
            ) {
                // Lock guideline set for historical consistency once request is created.
                $request->guideline_set_id = (int) $originalGuidelineSetId;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AppraisalAsset::class, 'appraisal_request_id', 'id');
    }

    public function reportGeneratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_generated_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reportReviewerSigner(): BelongsTo
    {
        return $this->belongsTo(ReportSigner::class, 'report_reviewer_signer_id');
    }

    public function reportPublicAppraiserSigner(): BelongsTo
    {
        return $this->belongsTo(ReportSigner::class, 'report_public_appraiser_signer_id');
    }

    public function physicalReportPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'physical_report_printed_by');
    }

    public function getIsReportGeneratedAttribute(): bool
    {
        return !is_null($this->report_generated_at) && !empty($this->report_pdf_path);
    }

    public function getIsPhysicalDeliveredAttribute(): bool
    {
        return !is_null($this->physical_report_delivered_at);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AppraisalRequestFile::class);
    }

    public function assetFiles(): HasManyThrough
    {
        return $this->hasManyThrough(
            AppraisalAssetFile::class,
            AppraisalAsset::class,
            'appraisal_request_id',
            'appraisal_asset_id',
            'id',
            'id',
        );
    }

    public function offerNegotiations(): HasMany
    {
        return $this->hasMany(AppraisalOfferNegotiation::class);
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(AppraisalRequestCancellation::class);
    }

    public function latestCancellationRequest(): HasOne
    {
        return $this->hasOne(AppraisalRequestCancellation::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'appraisal_request_id', 'id');
    }

    public function revisionBatches(): HasMany
    {
        return $this->hasMany(AppraisalRequestRevisionBatch::class);
    }

    public function fieldChangeLogs(): HasMany
    {
        return $this->hasMany(AppraisalFieldChangeLog::class);
    }

}
