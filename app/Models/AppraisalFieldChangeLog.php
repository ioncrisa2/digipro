<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalFieldChangeLog extends Model
{
    protected $fillable = [
        'appraisal_request_id',
        'appraisal_asset_id',
        'revision_batch_id',
        'revision_item_id',
        'changed_by',
        'change_source',
        'field_key',
        'field_label',
        'old_value',
        'new_value',
        'reason',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function appraisalRequest(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequest::class);
    }

    public function appraisalAsset(): BelongsTo
    {
        return $this->belongsTo(AppraisalAsset::class);
    }

    public function revisionBatch(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequestRevisionBatch::class, 'revision_batch_id');
    }

    public function revisionItem(): BelongsTo
    {
        return $this->belongsTo(AppraisalRequestRevisionItem::class, 'revision_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
