<?php

namespace App\Models;

use App\Enums\PurposeEnum;
use App\Models\GuidelineSet;
use App\Enums\ReportTypeEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\AppraisalStatusEnum;
use App\Traits\HasRequestNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AppraisalRequest extends Model
{
    use HasRequestNumber;

    protected $fillable = [
        'user_id',
        'guideline_set_id',
        'request_number',
        'purpose',
        'status',
        'requested_at',
        'consent_accepted_at',
        'consent_version',
        'consent_hash',
        'consent_ip',
        'consent_user_agent',
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
        'report_pdf_path',
        'report_pdf_size',
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
        'verified_at'                   => 'datetime',
        'contract_date'                 => 'date',
        'fee_has_dp'                    => 'boolean',
        'fee_total'                     => 'integer',
        'valuation_duration_days'       => 'integer',
        'offer_validity_days'           => 'integer',
        'purpose'                       => PurposeEnum::class,
        'status'                        => AppraisalStatusEnum::class,
        'contract_status'               => ContractStatusEnum::class,
        'report_type'                   => ReportTypeEnum::class,
        'report_generated_at'           => 'datetime',
        'physical_report_printed_at'    => 'datetime',
        'physical_report_shipped_at'    => 'datetime',
        'physical_report_delivered_at'  => 'datetime',
        'physical_copies_count'         => 'integer',
        'report_pdf_size'               => 'integer',
    ];

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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'appraisal_request_id', 'id');
    }

}
