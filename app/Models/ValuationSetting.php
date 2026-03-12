<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationSetting extends Model
{
    public const KEY_PPN_PERCENT = 'ppn_percent';

    protected $table = 'ref_valuation_settings';

    protected $fillable = [
        'guideline_set_id',
        'year',
        'key',
        'label',
        'value_number',
        'value_text',
        'notes',
    ];

    protected $casts = [
        'value_number' => 'float',
    ];

    public static function keyOptions(): array
    {
        return [
            self::KEY_PPN_PERCENT => 'PPN (%)',
        ];
    }

    public static function labelForKey(?string $key): string
    {
        return self::keyOptions()[$key] ?? strtoupper((string) $key);
    }

    public function guidelineSet(): BelongsTo
    {
        return $this->belongsTo(GuidelineSet::class, 'guideline_set_id');
    }
}
