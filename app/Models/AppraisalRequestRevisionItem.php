<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalRequestRevisionItem extends Model
{
    protected $fillable = [
        'revision_batch_id',
        'appraisal_asset_id',
        'item_type',
        'requested_file_type',
        'status',
        'issue_note',
        'original_request_file_id',
        'original_asset_file_id',
        'replacement_request_file_id',
        'replacement_asset_file_id',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequestRevisionBatch::class, 'revision_batch_id');
    }

    public function appraisalAsset(): BelongsTo
    {
        return $this->belongsTo(AppraisalAsset::class);
    }

    public function originalRequestFile(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequestFile::class, 'original_request_file_id');
    }

    public function originalAssetFile(): BelongsTo
    {
        return $this->belongsTo(AppraisalAssetFile::class, 'original_asset_file_id');
    }

    public function replacementRequestFile(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequestFile::class, 'replacement_request_file_id');
    }

    public function replacementAssetFile(): BelongsTo
    {
        return $this->belongsTo(AppraisalAssetFile::class, 'replacement_asset_file_id');
    }
}
