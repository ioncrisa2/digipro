<?php

namespace App\Services\Reviewer;

use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AdjustmentFactor;
use App\Models\BuildingEconomicLife;
use App\Models\ConstructionCostIndex;
use App\Models\CostElement;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\LandAdjustment;
use App\Models\MappiRcnStandard;
use App\Models\Province;
use App\Models\RefUsageToMappiGroup;
use App\Models\Regency;
use App\Models\ValuationSetting;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AdjustmentWorkbenchService
{
    public ?int $assetId = null;

    /**
     * @var array<string, mixed>
     */
    public array $subjectAsset = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $comparables = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $matrixColumns = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $matrixSections = [];

    /**
     * @var array<string, mixed>
     */
    public array $contextMeta = [];

    /**
     * @var array<int|string, array<string, float|int|string|null>>
     */
    public array $adjustmentInputs = [];

    /**
     * @var array<int|string, array<string, float|int|string|null>>
     */
    public array $generalInputs = [];

    /**
     * @var array<int|string, array<string, mixed>>
     */
    public array $adjustmentComputed = [];

    /**
     * @var array<string, int|float|string|null>
     */
    public array $rangeSummary = [];

    /**
     * @var array<int, array{key: string, label: string}>
     */
    public array $customAdjustmentFactors = [];

    public string $newCustomFactorLabel = '';

    private ?int $guidelineSetId = null;
    private ?int $guidelineYear = null;
    private ?float $subjectLandArea = null;
    private ?float $subjectFrontage = null;
    private ?float $subjectFrontingRoad = null;

    /**
     * @var array<string, ?RefUsageToMappiGroup>
     */
    private array $usageMappingCache = [];

    /**
     * @var array<string, float|null>
     */
    private array $floorIndexCache = [];

    /**
     * @var array<string, float|null>
     */
    private array $ikkCache = [];

    /**
     * @var array<string, int|null>
     */
    private array $economicLifeCache = [];

    /**
     * @var array<string, float|null>
     */
    private array $baseRcnCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $provinceNameCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $regencyNameCache = [];

    /**
     * @var array<string, float|null>
     */
    private array $valuationSettingNumberCache = [];

    /**
     * @var array<string, string>
     */
    private array $defaultAdjustmentFactors = [
        'adj_doc_ownership' => '5.23.1 Dokumen Kepemilikan (Sertifikat)',
        'adj_payment_term' => '5.23.2 Waktu Pembayaran (Cont: Hard Cash/Kredit)',
        'adj_sale_condition' => '5.23.3 Kondisi Penjualan / Kewajaran Transaksi',
        'adj_post_purchase_cost' => '5.23.4 Biaya Yang Dikeluarkan Setelah Pembelian',
        'adj_market_condition' => '5.23.5 Kondisi Pasar (Waktu Penjualan)',
        'adj_situation' => 'Situasi',
        'adj_road_width' => 'Lebar Jalan',
        'adj_neighborhood' => 'Lingkungan Sekitar',
        'adj_position' => 'Posisi',
        'adj_shape' => 'Bentuk',
        'adj_size' => 'Luasan',
        'adj_topography' => 'Topografi / Elevasi',
        'adj_condition' => 'Kondisi',
        'adj_frontage' => 'Lebar Depan',
        'adj_economic_character' => '5.23.8 Karakter Ekonomis Properti',
        'adj_use' => 'Peruntukan',
        'adj_kdb_klb' => '(KDB/KLB)',
        'adj_other_limitations' => 'Batas Lainnya',
        'adj_furnished' => 'Cont: Furnished or Unfurnished',
    ];

    public function mount(?int $assetId = null): void
    {
        $this->assetId = $assetId ?? (int) request()->integer('asset');

        abort_if($this->assetId <= 0, 404);

        $asset = AppraisalAsset::query()
            ->with([
                'request:id,request_number,guideline_set_id,requested_at',
                'request.guidelineSet:id,name,year',
                'comparables' => fn($query) => $query
                    ->where('is_selected', true)
                    ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
                    ->orderBy('id')
                    ->with(['landAdjustments.factor:id,code,name'])
                    ->limit(12),
            ])
            ->findOrFail($this->assetId);

        $activeGuideline = GuidelineSet::query()
            ->where('is_active', true)
            ->first(['id', 'name', 'year']);

        $hasRequestGuideline = $asset->request?->guidelineSet !== null;

        $this->guidelineSetId = $asset->request?->guideline_set_id
            ? (int) $asset->request->guideline_set_id
            : ($activeGuideline?->id ? (int) $activeGuideline->id : null);

        if ($hasRequestGuideline) {
            $this->guidelineYear = $asset->request?->guidelineSet?->year
                ? (int) $asset->request->guidelineSet->year
                : ($asset->request?->requested_at
                    ? (int) $asset->request->requested_at->format('Y')
                    : ($activeGuideline?->year ? (int) $activeGuideline->year : null));
        } else {
            $this->guidelineYear = $activeGuideline?->year
                ? (int) $activeGuideline->year
                : ($asset->request?->requested_at
                    ? (int) $asset->request->requested_at->format('Y')
                    : (int) now()->format('Y'));
        }

        $this->subjectLandArea = $this->toFloat($asset->land_area);
        $this->subjectFrontage = $this->toFloat($asset->frontage_width);
        $this->subjectFrontingRoad = $this->toFloat($asset->access_road_width);
        $this->subjectAsset = $this->buildSubjectAsset($asset);

        $this->comparables = $asset->comparables
            ->values()
            ->map(fn(AppraisalAssetComparable $item, int $index) => $this->normalizeComparable($item, $index + 1))
            ->all();

        $this->bootstrapGeneralInputs();
        $this->hydrateCustomFactorsFromModels($asset->comparables->all());

        $this->matrixColumns = collect($this->comparables)
            ->map(fn(array $item, int $index) => [
                'id' => $item['id'] ?? null,
                'title' => "Data Pembanding " . ($index + 1),
                'external_id' => $item['external_id'] ?? '-',
                'rank' => $item['rank'] ?? '-',
                'score'               => $item['score'] ?? null,
                'distance_to_subject' => $item['distance_to_subject'] ?? '-',
            ])
            ->values()
            ->all();

        $this->contextMeta = [
            'request_number' => $asset->request?->request_number ?? '-',
            'guideline' => $asset->request?->guidelineSet?->name ?? ($activeGuideline?->name ?? '-'),
            'guideline_year' => $this->guidelineYear ? (string) $this->guidelineYear : '-',
            'ppn_percent' => $this->formatUnsignedPercent($this->ppnPercent()),
        ];

        $this->bootstrapAdjustmentInputs();
        $this->recalculateAdjustmentOutputs();
        $this->matrixSections = $this->buildMatrixSections();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $adjustmentInputs
     * @param  array<int, array{key: string, label: string}>  $customAdjustmentFactors
     */
    public function syncClientState(
        array $adjustmentInputs = [],
        array $customAdjustmentFactors = [],
        array $generalInputs = [],
    ): void
    {
        if (! empty($customAdjustmentFactors)) {
            $this->customAdjustmentFactors = collect($customAdjustmentFactors)
                ->map(function (array $factor): array {
                    $key = trim((string) ($factor['key'] ?? ''));
                    $label = trim((string) ($factor['label'] ?? ''));

                    return [
                        'key' => $key,
                        'label' => $label !== '' ? $label : $key,
                    ];
                })
                ->filter(fn (array $factor): bool => $factor['key'] !== '')
                ->values()
                ->all();
        }

        foreach ($this->comparables as $comparable) {
            $comparableId = (string) ($comparable['id'] ?? '');

            if ($comparableId === '') {
                continue;
            }

            $this->adjustmentInputs[$comparableId] ??= [];

            foreach ($this->activeAdjustmentInputKeys() as $factorKey) {
                if (array_key_exists($factorKey, $this->adjustmentInputs[$comparableId])) {
                    continue;
                }

                $this->adjustmentInputs[$comparableId][$factorKey] = 0.0;
            }
        }

        foreach ($adjustmentInputs as $comparableId => $rowInputs) {
            if (! is_array($rowInputs)) {
                continue;
            }

            $this->adjustmentInputs[(string) $comparableId] ??= [];

            foreach ($rowInputs as $factorKey => $value) {
                $this->adjustmentInputs[(string) $comparableId][(string) $factorKey] = $value;
            }
        }

        $this->bootstrapGeneralInputs();

        foreach ($generalInputs as $comparableId => $rowInputs) {
            if (! is_array($rowInputs)) {
                continue;
            }

            $comparableId = (string) $comparableId;
            $this->generalInputs[$comparableId] ??= [];

            if (array_key_exists('assumed_discount', $rowInputs)) {
                $this->generalInputs[$comparableId]['assumed_discount'] = $this->normalizeAssumedDiscountPercent(
                    $rowInputs['assumed_discount']
                );
            }

            if (array_key_exists('material_quality_adj', $rowInputs)) {
                $this->generalInputs[$comparableId]['material_quality_adj'] = $this->normalizeMaterialQualityAdjustment(
                    $rowInputs['material_quality_adj']
                );
            }

            if (array_key_exists('maintenance_adj_delta', $rowInputs)) {
                $this->generalInputs[$comparableId]['maintenance_adj_delta'] = $this->normalizeMaintenanceAdjustmentPercent(
                    $rowInputs['maintenance_adj_delta']
                );
            }
        }

        $this->rebuildComparableState();

        $this->matrixSections = $this->buildMatrixSections();
        $this->recalculateAdjustmentOutputs();
    }

    /**
     * @return array<string, mixed>
     */
    public function exportReviewerPayload(): array
    {
        return [
            'asset_id' => $this->assetId,
            'subject_asset' => $this->subjectAsset,
            'comparables' => $this->comparables,
            'matrix_columns' => $this->matrixColumns,
            'matrix_sections' => $this->matrixSections,
            'context_meta' => $this->contextMeta,
            'adjustment_inputs' => $this->adjustmentInputs,
            'general_inputs' => $this->generalInputs,
            'adjustment_computed' => $this->adjustmentComputed,
            'range_summary' => $this->rangeSummary,
            'custom_adjustment_factors' => $this->customAdjustmentFactors,
        ];
    }

    private function assetTypeLabel(?string $type): string
    {
        if (! $type) {
            return '-';
        }

        return AssetTypeEnum::tryFrom($type)?->label() ?? $type;
    }

    /**
     * @return array<string, string>
     */
    private function buildSubjectAsset(AppraisalAsset $asset): array
    {
        $mapping = $this->usageMapping($asset->peruntukan);
        $buildingType = $this->resolveMappiBuildingType(
            $mapping?->buildingType(),
            $asset->peruntukan,
            [],
        );
        $buildingClass = $this->resolveMappiBuildingClass(
            $mapping?->buildingClass(),
            $asset->peruntukan,
            [],
        );
        $floorCount = $this->toInt($asset->building_floors);

        $ikk = $asset->ikk_value_used !== null
            ? (float) $asset->ikk_value_used
            : $this->ikkValue($this->normalizeRegionCode($asset->regency_id));

        return [
            'id' => (string) $asset->id,
            'request_number' => $asset->request?->request_number ?? '-',
            'type' => $this->assetTypeLabel($asset->asset_type),
            'address' => $this->text($asset->address),
            'coordinates' => $this->formatCoordinates($asset->coordinates_lat, $asset->coordinates_lng),
            'land_area' => $this->formatArea($asset->land_area),
            'building_area' => $this->formatArea($asset->building_area),
            'build_year' => $this->text($asset->build_year),
            'valuation_year' => $this->guidelineYear ? (string) $this->guidelineYear : '-',
            'peruntukan' => $this->displayOptionLabel($asset->peruntukan),
            'listing' => 'Objek Penilaian',
            'source' => '-',
            'phone' => '-',
            'location' => $this->text($asset->address),
            'shape' => $this->displayOptionLabel($asset->land_shape),
            'title_doc' => $this->displayOptionLabel($asset->title_document),
            'land_position' => $this->displayOptionLabel($asset->land_position),
            'land_condition' => $this->displayOptionLabel($asset->land_condition),
            'topography' => $this->displayOptionLabel($asset->topography),
            'frontage' => $this->formatMeter($asset->frontage_width),
            'fronting_road' => $this->formatMeter($asset->access_road_width),
            'town_planning' => $this->displayOptionLabel($asset->peruntukan),
            'plot_ratio' => '-',
            'asking_price' => '-',
            'likely_sale' => '-',
            'assumed_discount' => '-',
            'distance_to_subject' => '0 meter',
            'data_date' => $this->formatMonthYear($asset->request?->requested_at),
            'building_type' => $this->text($buildingType),
            'floor_count' => $this->text($floorCount),
            'floor_index' => $this->formatDecimal($this->floorIndexValue($buildingClass, $floorCount), 4),
            'province' => $this->text($this->provinceName($asset->province_id)),
            'regency' => $this->text($this->regencyName($asset->regency_id)),
            'ikk_reference' => $this->formatDecimal($ikk, 4),
            'rcn_standard_ref' => '-',
            'material_quality_adj' => '-',
            'rcn_x_adjustment_ref' => '-',
            'maintenance_ref' => '-',
            'building_value_sqm_ref' => '-',
            'building_value_ref' => '-',
            'residual_land_value_ref' => '-',
            'residual_land_value_sqm_ref' => '-',
            'adj_doc_ownership' => '-',
            'adj_payment_term' => '-',
            'adj_sale_condition' => '-',
            'adj_post_purchase_cost' => '-',
            'adj_market_condition' => '-',
            'adj_situation' => '-',
            'adj_road_width' => '-',
            'adj_neighborhood' => '-',
            'adj_position' => '-',
            'adj_shape' => '-',
            'adj_size' => '-',
            'adj_topography' => '-',
            'adj_condition' => '-',
            'adj_frontage' => '-',
            'adj_economic_character' => '-',
            'adj_use' => '-',
            'adj_kdb_klb' => '-',
            'adj_other_limitations' => '-',
            'adj_furnished' => '-',
            'adj_total' => '-',
            'adj_estimated_unit' => '-',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeComparable(AppraisalAssetComparable $item, int $index): array
    {
        $snapshot = is_array($item->snapshot_json) ? $item->snapshot_json : [];

        $peruntukan = $this->firstFilled([
            data_get($snapshot, 'peruntukan.slug'),
            data_get($snapshot, 'peruntukan.name'),
            data_get($snapshot, 'peruntukan'),
            $item->raw_peruntukan,
        ]);

        $regionCode = $this->resolveRegionCodeFromSnapshot($snapshot);

        $floorCount = $this->firstInt([
            data_get($snapshot, 'jumlah_lantai'),
            data_get($snapshot, 'floor_count'),
            data_get($snapshot, 'lantai'),
        ]);

        $mapping = $this->usageMapping($peruntukan);
        $buildingType = $this->resolveMappiBuildingType(
            $mapping?->buildingType(),
            is_string($peruntukan) ? $peruntukan : null,
            $snapshot,
        );
        $buildingClass = $this->resolveMappiBuildingClass(
            $mapping?->buildingClass(),
            is_string($peruntukan) ? $peruntukan : null,
            $snapshot,
        );
        $preferredStoreyPattern = $this->resolvePreferredStoreyPattern($mapping, $snapshot);

        $ikk = $this->ikkValue($regionCode);

        $valuationYear = $this->guidelineYear ?: (int) now()->format('Y');
        $builtYear = $this->firstInt([
            data_get($snapshot, 'tahun_bangun'),
            data_get($snapshot, 'building_year'),
        ]);

        $landArea = $this->firstNumber([
            $item->raw_land_area,
            data_get($snapshot, 'luas_tanah'),
        ]);
        $frontageValue = $this->firstNumber([
            data_get($snapshot, 'lebar_depan'),
        ]);
        $frontingRoadValue = $this->firstNumber([
            data_get($snapshot, 'lebar_jalan'),
        ]);
        $buildingAreaRaw = $this->toFloat($item->raw_building_area);
        $buildingAreaSnapshot = $this->firstNumber([
            data_get($snapshot, 'luas_bangunan'),
            data_get($snapshot, 'building_area'),
            data_get($snapshot, 'building.area'),
            data_get($snapshot, 'lb'),
            data_get($snapshot, 'luas_bangunan_sqm'),
            data_get($snapshot, 'luas_bangunan_terbangun'),
            data_get($snapshot, 'detail.luas_bangunan'),
        ]);

        $buildingArea = $this->resolveEffectiveBuildingArea(
            buildingAreaRaw: $buildingAreaRaw,
            buildingAreaSnapshot: $buildingAreaSnapshot,
        );

        if ($floorCount === null && $buildingArea !== null && $buildingArea > 0) {
            $floorCount = 1;
        }

        $askingPrice = $this->firstNumber([
            $item->raw_price,
            data_get($snapshot, 'harga'),
            data_get($snapshot, 'asking_price'),
        ]);
        $likelySale = $this->firstNumber([
            data_get($snapshot, 'likely_sale_price'),
            data_get($snapshot, 'harga_transaksi'),
            data_get($snapshot, 'transaction_price'),
        ]);
        $discountRaw = $this->firstNumber([
            data_get($this->generalInputs, "{$item->id}.assumed_discount"),
            $item->assumed_discount_percent,
            data_get($snapshot, 'assumed_discount'),
            data_get($snapshot, 'diskon'),
            data_get($snapshot, 'discount'),
        ]);
        $discountPercent = $this->normalizeAssumedDiscountPercent($discountRaw);

        $discountFraction = $this->normalizeDiscountFraction($discountPercent);
        if ($askingPrice !== null && $discountFraction !== null) {
            $likelySale = $askingPrice * (1 - $discountFraction);
        }

        $materialQualityAdj = $this->normalizeMaterialQualityAdjustment($this->firstNumber([
            data_get($this->generalInputs, "{$item->id}.material_quality_adj"),
            $item->material_quality_adjustment,
            data_get($snapshot, 'penyesuaian_kualitas_material_bangunan_rcn'),
            data_get($snapshot, 'penyesuaian_kualitas_material'),
            data_get($snapshot, 'material_quality_adjustment'),
        ]));
        $maintenanceAdjDeltaPercent = $this->normalizeMaintenanceAdjustmentPercent($this->firstNumber([
            data_get($this->generalInputs, "{$item->id}.maintenance_adj_delta"),
            $item->maintenance_adjustment_delta_percent,
            data_get($snapshot, 'maintenance_adjustment_delta_percent'),
            data_get($snapshot, 'adj_delta'),
            data_get($snapshot, 'penyesuaian_manual'),
        ]));

        $conditionLabel = $this->firstFilled([
            data_get($snapshot, 'kondisi_tanah.name'),
            data_get($snapshot, 'kondisi_tanah'),
            data_get($snapshot, 'kondisi_bangunan'),
            data_get($snapshot, 'building_condition'),
        ], null);

        $residualContext = $this->calculateLandResidualContext(
            askingPrice: $askingPrice,
            likelySale: $likelySale,
            landArea: $landArea,
            buildingArea: $buildingArea,
            builtYear: $builtYear,
            valuationYear: $valuationYear,
            buildingType: $buildingType,
            buildingClass: $buildingClass,
            floorCount: $floorCount,
            preferredStoreyPattern: $preferredStoreyPattern,
            materialQualityAdj: $materialQualityAdj,
            maintenanceAdjDeltaPercent: $maintenanceAdjDeltaPercent,
            ikkValue: $ikk,
            conditionLabel: is_string($conditionLabel) ? $conditionLabel : null,
            snapshot: $snapshot,
        );

        return [
            'id' => (string) $item->id,
            'external_id' => $this->text($item->external_id),
            'rank' => $this->text($item->manual_rank ?? $item->rank),
            'score' => $item->score !== null ? round((float) $item->score, 2) : null,
            'listing' => $this->text($this->firstFilled([
                data_get($snapshot, 'jenis_listing.name'),
                data_get($snapshot, 'jenis_listing'),
                'Penawaran',
            ])),
            'type' => $this->text($this->firstFilled([
                data_get($snapshot, 'jenis_objek.name'),
                data_get($snapshot, 'jenis_objek'),
                $peruntukan,
            ])),
            'source' => $this->text($this->firstFilled([
                data_get($snapshot, 'sumber'),
                data_get($snapshot, 'source'),
                data_get($snapshot, 'nama_sumber'),
            ])),
            'phone' => $this->text($this->firstFilled([
                data_get($snapshot, 'telepon'),
                data_get($snapshot, 'no_telepon'),
                data_get($snapshot, 'phone'),
            ])),
            'location' => $this->text($this->firstFilled([
                data_get($snapshot, 'alamat_data'),
                data_get($snapshot, 'alamat'),
            ])),
            'coordinates' => $this->formatCoordinates(
                $this->firstFilled([data_get($snapshot, 'latitude')], null),
                $this->firstFilled([data_get($snapshot, 'longitude')], null),
            ),
            'land_area' => $this->formatArea($this->firstFilled([$item->raw_land_area, data_get($snapshot, 'luas_tanah')], null)),
            'building_area' => $this->formatArea($buildingArea),
            'build_year' => $this->text($this->firstFilled([
                data_get($snapshot, 'tahun_bangun'),
                data_get($snapshot, 'building_year'),
            ])),
            'shape' => $this->text($this->firstFilled([
                data_get($snapshot, 'bentuk_tanah.name'),
                data_get($snapshot, 'bentuk_tanah'),
            ])),
            'title_doc' => $this->text($this->firstFilled([
                data_get($snapshot, 'dokumen_tanah.name'),
                data_get($snapshot, 'dokumen_tanah'),
            ])),
            'land_position' => $this->text($this->firstFilled([
                data_get($snapshot, 'posisi_tanah.name'),
                data_get($snapshot, 'posisi_tanah'),
            ])),
            'land_condition' => $this->text($this->firstFilled([
                data_get($snapshot, 'kondisi_tanah.name'),
                data_get($snapshot, 'kondisi_tanah'),
            ])),
            'topography' => $this->text($this->firstFilled([
                data_get($snapshot, 'topografi.name'),
                data_get($snapshot, 'topografi'),
            ])),
            'frontage' => $this->formatMeter($frontageValue),
            'fronting_road' => $this->formatMeter($frontingRoadValue),
            'town_planning' => $this->text($peruntukan),
            'plot_ratio' => $this->text($this->firstFilled([
                data_get($snapshot, 'kdb_klb'),
                data_get($snapshot, 'plot_ratio'),
                data_get($snapshot, 'site_coverage'),
            ])),
            'asking_price' => $this->formatCurrency($askingPrice),
            'likely_sale' => $this->formatCurrency($likelySale),
            'assumed_discount' => $this->formatUnsignedPercent($discountPercent),
            'assumed_discount_value' => $discountPercent,
            'material_quality_adj_value' => $materialQualityAdj,
            'maintenance_adj_delta_value' => $maintenanceAdjDeltaPercent,
            'maintenance_remaining_text' => $residualContext['maintenance_remaining_text'],
            'maintenance_final_text' => $residualContext['maintenance_final_text'],
            'maintenance_detail_text' => $residualContext['maintenance_detail_text'],
            'valuation_year' => (string) $valuationYear,
            'distance_to_subject' => $this->formatDistance($item->distance_meters),
            'data_date' => $this->formatMonthYear($this->firstFilled([
                $item->raw_data_date,
                data_get($snapshot, 'tanggal_data'),
            ], null)),
            'building_type' => $this->text($this->firstFilled([
                $buildingType,
                data_get($snapshot, 'jenis_objek.name'),
                data_get($snapshot, 'jenis_objek'),
            ])),
            'floor_count' => $this->text($floorCount),
            'floor_index' => $this->formatDecimal($this->floorIndexValue($buildingClass, $floorCount), 4),
            'province' => $this->text($this->firstFilled([
                data_get($snapshot, 'province.name'),
                $this->provinceName($this->firstFilled([data_get($snapshot, 'province.id')], null)),
            ])),
            'regency' => $this->text($this->firstFilled([
                data_get($snapshot, 'regency.name'),
                $this->regencyName($regionCode),
            ])),
            'ikk_reference' => $this->formatDecimal($ikk, 4),
            'rcn_standard_ref' => $residualContext['rcn_standard_ref'],
            'material_quality_adj' => $residualContext['material_quality_adj_ref'],
            'rcn_x_adjustment_ref' => $residualContext['rcn_adjusted_ref'],
            'maintenance_ref' => $residualContext['maintenance_ref'],
            'building_value_sqm_ref' => $residualContext['building_value_sqm_ref'],
            'building_value_ref' => $residualContext['building_value_ref'],
            'residual_land_value_ref' => $residualContext['residual_land_value_ref'],
            'residual_land_value_sqm_ref' => $residualContext['residual_land_value_sqm_ref'],
            'residual_land_value_sqm_base' => $residualContext['residual_land_value_sqm_base'],
            'suggested_adjustments' => [
                'adj_size' => $this->suggestRatioAdjustment($this->subjectLandArea, $landArea),
                'adj_frontage' => $this->suggestRatioAdjustment($this->subjectFrontage, $frontageValue),
                'adj_road_width' => $this->suggestRatioAdjustment($this->subjectFrontingRoad, $frontingRoadValue),
            ],
            'existing_adjustments' => $this->extractExistingAdjustments($item),
            'adj_doc_ownership' => '0.00%',
            'adj_payment_term' => '0.00%',
            'adj_sale_condition' => '0.00%',
            'adj_post_purchase_cost' => '0.00%',
            'adj_market_condition' => '0.00%',
            'adj_situation' => '0.00%',
            'adj_road_width' => '0.00%',
            'adj_neighborhood' => '0.00%',
            'adj_position' => '0.00%',
            'adj_shape' => '0.00%',
            'adj_size' => '0.00%',
            'adj_topography' => '0.00%',
            'adj_condition' => '0.00%',
            'adj_frontage' => '0.00%',
            'adj_economic_character' => '0.00%',
            'adj_use' => '0.00%',
            'adj_kdb_klb' => '0.00%',
            'adj_other_limitations' => '0.00%',
            'adj_furnished' => '0.00%',
            'adj_total' => '0.00%',
            'adj_estimated_unit' => $residualContext['residual_land_value_sqm_ref'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrixSections(): array
    {
        return [
            [
                'title' => 'Data Umum',
                'tone' => 'primary',
                'rows' => [
                    $this->row('Listings', 'listing'),
                    $this->row('Type / Type', 'type'),
                    $this->row('Source / Sumber', 'source'),
                    $this->row('Telephone / Telepon', 'phone'),
                    $this->row('Location / Lokasi', 'location'),
                    $this->row('Titik Koordinat', 'coordinates'),
                    $this->row('Land Area / Luas Tanah (sqm)', 'land_area'),
                    $this->row('Building Area / Luas Bangunan (sqm)', 'building_area'),
                    $this->row('Building Finished in Year / Tahun Bangun', 'build_year'),
                    $this->row('Shape / Bentuk Tanah', 'shape'),
                    $this->row('Title / Dokumen Tanah', 'title_doc'),
                    $this->row('Land Position / Posisi', 'land_position'),
                    $this->row('Land Condition / Kondisi', 'land_condition'),
                    $this->row('Topography / Topografi', 'topography'),
                    $this->row('Frontage / Lebar Depan (m)', 'frontage'),
                    $this->row('Fronting Road / Lebar Jalan (m)', 'fronting_road'),
                    $this->row('Town Planning / Peruntukan', 'town_planning'),
                    $this->row('Site Coverage / Plot Ratio', 'plot_ratio'),
                    $this->row('Asking Price / Harga Penawaran', 'asking_price'),
                    $this->row('Likely Sale or Transaction Price', 'likely_sale'),
                    $this->row('Assumed Discount / Diskon', 'assumed_discount'),
                    $this->row('Year Valuation / Tahun Penilaian', 'valuation_year'),
                    $this->row('Jarak dengan Objek Penilaian', 'distance_to_subject'),
                    $this->row('Date of Data / Tanggal Data', 'data_date'),
                ],
            ],
            [
                'title' => 'Land Residual Method - Reference Data (Read-Only)',
                'tone' => 'secondary',
                'rows' => [
                    $this->row('Jenis Bangunan', 'building_type'),
                    $this->row('Jumlah Lantai', 'floor_count'),
                    $this->row('Index Lantai (ref_floor_index)', 'floor_index'),
                    $this->row('Provinsi', 'province'),
                    $this->row('Kota/Kabupaten', 'regency'),
                    $this->row('RCN Standar MAPPI', 'rcn_standard_ref'),
                    $this->row('Penyesuaian Kualitas Material Bangunan RCN', 'material_quality_adj'),
                    $this->row('RCN x Penyesuaian', 'rcn_x_adjustment_ref'),
                    $this->row('Perawatan (Kondisi Terlihat)', 'maintenance_ref'),
                    $this->row('Building Value - Depreciation / sqm', 'building_value_sqm_ref'),
                    $this->row('Building Value', 'building_value_ref'),
                    $this->row('Residual Land Value', 'residual_land_value_ref'),
                    $this->row('Residual Land Value / sqm', 'residual_land_value_sqm_ref'),
                ],
            ],
            [
                'title' => 'Adjustment - Element of Comparison (SPI 300) - Template',
                'tone' => 'primary',
                'rows' => $this->buildAdjustmentRows(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAdjustmentRows(): array
    {
        $rows = [
            $this->row($this->defaultAdjustmentFactors['adj_doc_ownership'], 'adj_doc_ownership'),
            $this->row($this->defaultAdjustmentFactors['adj_payment_term'], 'adj_payment_term'),
            $this->row($this->defaultAdjustmentFactors['adj_sale_condition'], 'adj_sale_condition'),
            $this->row($this->defaultAdjustmentFactors['adj_post_purchase_cost'], 'adj_post_purchase_cost'),
            $this->row($this->defaultAdjustmentFactors['adj_market_condition'], 'adj_market_condition'),
            $this->rowGroup('5.23.6 Lokasi'),
            $this->row($this->defaultAdjustmentFactors['adj_situation'], 'adj_situation', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_road_width'], 'adj_road_width', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_neighborhood'], 'adj_neighborhood', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_position'], 'adj_position', ['indent' => 1]),
            $this->rowGroup('5.23.7 Karakteristik Fisik'),
            $this->row($this->defaultAdjustmentFactors['adj_shape'], 'adj_shape', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_size'], 'adj_size', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_topography'], 'adj_topography', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_condition'], 'adj_condition', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_frontage'], 'adj_frontage', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_economic_character'], 'adj_economic_character'),
            $this->rowGroup('5.23.9 Penggunaan Properti'),
            $this->row($this->defaultAdjustmentFactors['adj_use'], 'adj_use', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_kdb_klb'], 'adj_kdb_klb', ['indent' => 1]),
            $this->row($this->defaultAdjustmentFactors['adj_other_limitations'], 'adj_other_limitations', ['indent' => 1]),
            $this->rowGroup('5.23.10 Komponen Tidak Langsung Dalam Penjualan'),
            $this->row($this->defaultAdjustmentFactors['adj_furnished'], 'adj_furnished', ['indent' => 1]),
        ];

        if (! empty($this->customAdjustmentFactors)) {
            $rows[] = $this->rowGroup('Faktor Tambahan');

            foreach ($this->customAdjustmentFactors as $customFactor) {
                $rows[] = $this->row((string) ($customFactor['label'] ?? 'Faktor Custom'), (string) ($customFactor['key'] ?? ''), ['indent' => 1]);
            }
        }

        $rows[] = $this->row('Total Adjustment', 'adj_total', ['type' => 'total']);
        $rows[] = $this->row('Estimated Land / Unit Value (Rp/sqm)', 'adj_estimated_unit', ['type' => 'total']);

        return $rows;
    }

    private function bootstrapAdjustmentInputs(): void
    {
        $factorKeys = $this->activeAdjustmentInputKeys();
        $inputs = [];

        foreach ($this->comparables as $comparable) {
            $comparableId = (string) ($comparable['id'] ?? '');

            if ($comparableId === '') {
                continue;
            }

            $inputs[$comparableId] = [];
            $suggestedAdjustments = is_array($comparable['suggested_adjustments'] ?? null)
                ? $comparable['suggested_adjustments']
                : [];

            foreach ($factorKeys as $factorKey) {
                $inputs[$comparableId][$factorKey] = $this->toFloat($suggestedAdjustments[$factorKey] ?? null) ?? 0.0;
            }

            $existing = is_array($comparable['existing_adjustments'] ?? null)
                ? $comparable['existing_adjustments']
                : [];

            foreach ($existing as $factorKey => $value) {
                if (! in_array($factorKey, $factorKeys, true)) {
                    continue;
                }

                $inputs[$comparableId][$factorKey] = $this->toFloat($value) ?? 0.0;
            }
        }

        $this->adjustmentInputs = $inputs;
    }

    private function bootstrapGeneralInputs(): void
    {
        $inputs = $this->generalInputs;

        foreach ($this->comparables as $comparable) {
            $comparableId = (string) ($comparable['id'] ?? '');

            if ($comparableId === '') {
                continue;
            }

            $inputs[$comparableId] ??= [];
            $inputs[$comparableId]['assumed_discount'] = $this->normalizeAssumedDiscountPercent(
                $inputs[$comparableId]['assumed_discount'] ?? ($comparable['assumed_discount_value'] ?? null)
            );
            $inputs[$comparableId]['material_quality_adj'] = $this->normalizeMaterialQualityAdjustment(
                $inputs[$comparableId]['material_quality_adj'] ?? ($comparable['material_quality_adj_value'] ?? null)
            );
            $inputs[$comparableId]['maintenance_adj_delta'] = $this->normalizeMaintenanceAdjustmentPercent(
                $inputs[$comparableId]['maintenance_adj_delta'] ?? ($comparable['maintenance_adj_delta_value'] ?? null)
            );
        }

        $this->generalInputs = $inputs;
    }

    private function rebuildComparableState(): void
    {
        if (! $this->assetId) {
            return;
        }

        $models = AppraisalAssetComparable::query()
            ->where('appraisal_asset_id', $this->assetId)
            ->where('is_selected', true)
            ->orderByRaw('COALESCE(`manual_rank`, `rank`, 9999)')
            ->orderBy('id')
            ->with(['landAdjustments.factor:id,code,name'])
            ->limit(12)
            ->get();

        $this->comparables = $models
            ->values()
            ->map(fn (AppraisalAssetComparable $item, int $index) => $this->normalizeComparable($item, $index + 1))
            ->all();

        $this->matrixColumns = collect($this->comparables)
            ->map(fn(array $item, int $index) => [
                'id' => $item['id'] ?? null,
                'title' => "Data Pembanding " . ($index + 1),
                'external_id' => $item['external_id'] ?? '-',
                'rank' => $item['rank'] ?? '-',
                'score' => $item['score'] ?? null,
                'distance_to_subject' => $item['distance_to_subject'] ?? '-',
            ])
            ->values()
            ->all();
    }

    private function recalculateAdjustmentOutputs(): void
    {
        $computed = [];
        $factorKeys = $this->activeAdjustmentInputKeys();

        foreach ($this->comparables as $comparable) {
            $comparableId = (string) ($comparable['id'] ?? '');

            if ($comparableId === '') {
                continue;
            }

            $baseUnit = $this->toFloat($comparable['residual_land_value_sqm_base'] ?? null);
            if ($baseUnit === null) {
                $baseUnit = $this->toFloat($comparable['residual_land_value_sqm_ref'] ?? null);
            }
            $warnings = [];
            if ($baseUnit === null) {
                $warnings[] = 'Nilai dasar tanah per m2 belum tersedia.';
            } elseif ($baseUnit <= 0) {
                $warnings[] = 'Nilai dasar tanah per m2 tidak valid (<= 0).';
                $baseUnit = null;
            }

            $factorComputed = [];
            $totalPercent = 0.0;
            $totalAmount = 0.0;
            $runningUnit = $baseUnit;

            foreach ($factorKeys as $factorKey) {
                $percent = $this->normalizeAdjustmentPercent(
                    data_get($this->adjustmentInputs, "{$comparableId}.{$factorKey}")
                );
                // JANGAN overwrite adjustmentInputs di sini — akan menghapus nilai
                // yang sedang diketik user dan menyebabkan input hilang saat re-render.
                $amount = $baseUnit !== null ? ($baseUnit * ($percent / 100)) : null;
                $correctedUnit = null;

                if ($runningUnit !== null && $amount !== null) {
                    $runningUnit += $amount;
                    $correctedUnit = $runningUnit;
                }

                $factorComputed[$factorKey] = [
                    'percent' => $percent,
                    'percent_text' => $this->formatSignedPercent($percent),
                    'amount' => $amount,
                    'amount_text' => $this->formatCurrency($amount),
                    'corrected_unit' => $correctedUnit,
                    'corrected_unit_text' => $this->formatCurrency($correctedUnit),
                ];

                $totalPercent += $percent;
                if ($amount !== null) {
                    $totalAmount += $amount;
                }
            }

            $estimatedUnit = $baseUnit !== null ? ($baseUnit + $totalAmount) : null;
            $canEstimate = $estimatedUnit !== null && $estimatedUnit > 0;
            if ($estimatedUnit !== null && $estimatedUnit <= 0) {
                $warnings[] = 'Nilai akhir per m2 <= 0 setelah adjustment.';
            }

            $computed[$comparableId] = [
                'base_unit' => $baseUnit,
                'base_unit_text' => $this->formatCurrency($baseUnit),
                'factors' => $factorComputed,
                'total_percent' => $totalPercent,
                'total_percent_text' => $this->formatSignedPercent($totalPercent),
                'total_amount' => $baseUnit !== null ? $totalAmount : null,
                'total_amount_text' => $baseUnit !== null ? $this->formatCurrency($totalAmount) : '-',
                'estimated_unit' => $estimatedUnit,
                'estimated_unit_text' => $this->formatCurrency($estimatedUnit),
                'can_estimate' => $canEstimate,
                'warnings' => $warnings,
            ];
        }

        $this->adjustmentComputed = $computed;
        $this->recalculateRangeSummary();
    }

    /**
     * @return array<int, string>
     */
    private function activeAdjustmentInputKeys(): array
    {
        $customKeys = collect($this->customAdjustmentFactors)
            ->pluck('key')
            ->filter(fn($value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        return array_values(array_merge(array_keys($this->defaultAdjustmentFactors), $customKeys));
    }

    /**
     * @return array<string, float>
     */
    private function extractExistingAdjustments(AppraisalAssetComparable $item): array
    {
        $values = [];

        foreach ($item->landAdjustments as $adjustment) {
            $key = $this->resolveAdjustmentKey(
                data_get($adjustment, 'factor.code'),
                data_get($adjustment, 'factor.name'),
            );

            if ($key === null) {
                continue;
            }

            $percent = $this->toFloat($adjustment->adjustment_percent);
            if ($percent === null) {
                continue;
            }

            $values[$key] = $percent;
        }

        return $values;
    }

    private function resolveAdjustmentKey(?string $code, ?string $name): ?string
    {
        $candidates = [
            strtolower(trim((string) $code)),
            strtolower(trim((string) Str::of((string) $name)->slug('_'))),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $normalized = str_starts_with($candidate, 'adj_')
                ? $candidate
                : 'adj_' . $candidate;

            if (array_key_exists($normalized, $this->defaultAdjustmentFactors) || str_starts_with($normalized, 'adj_custom_')) {
                return $normalized;
            }

            $customSlug = str_replace('adj_', '', $normalized);
            if ($customSlug !== '') {
                return 'adj_custom_' . $customSlug;
            }
        }

        return null;
    }

    private function formatSignedPercent(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        $sign = $value > 0 ? '+' : '';

        return $sign . number_format($value, 2, ',', '.') . '%';
    }

    public function updated(string $name, mixed $value): void
    {
        if (str_starts_with($name, 'adjustmentInputs.')) {
            $this->recalculateAdjustmentOutputs();
        }
    }

    /**
     * Dedicated action untuk set nilai adjustment per faktor.
     * Dipanggil dari blade via wire:change daripada wire:model
     * agar Livewire tidak kehilangan nilai nested array saat re-render.
     */
    public function setAdjustment(string $comparableId, string $factorKey, mixed $value): void
    {
        $clamped = $this->clamp($this->toFloat($value) ?? 0.0, -100.0, 100.0);

        if (! isset($this->adjustmentInputs[$comparableId])) {
            $this->adjustmentInputs[$comparableId] = [];
        }

        $this->adjustmentInputs[$comparableId][$factorKey] = $clamped;
        $this->recalculateAdjustmentOutputs();
    }

    public function addCustomAdjustmentFactor(): void
    {
        $label = trim($this->newCustomFactorLabel);
        if (strlen($label) > 120) {
            $label = substr($label, 0, 120);
        }

        if ($label === '') {
            return;
        }

        $slug = (string) Str::of($label)->lower()->slug('_');
        if ($slug === '') {
            return;
        }

        $key = 'adj_custom_' . $slug;
        $counter = 2;

        while (in_array($key, $this->activeAdjustmentInputKeys(), true)) {
            $key = 'adj_custom_' . $slug . '_' . $counter;
            $counter++;
        }

        $this->customAdjustmentFactors[] = [
            'key' => $key,
            'label' => $label,
        ];

        foreach ($this->comparables as $comparable) {
            $comparableId = (string) ($comparable['id'] ?? '');
            if ($comparableId === '') {
                continue;
            }

            $this->adjustmentInputs[$comparableId][$key] = 0.0;
        }

        $this->newCustomFactorLabel = '';
        $this->matrixSections = $this->buildMatrixSections();
        $this->recalculateAdjustmentOutputs();
    }

    /**
     * @return array<string, mixed>
     */
    public function persistAdjustmentData(): array
    {
        if (! $this->assetId) {
            throw new \RuntimeException('Aset tidak valid.');
        }

        if (count($this->comparables) === 0) {
            throw new \RuntimeException('Belum ada pembanding dipilih.');
        }

        $this->recalculateAdjustmentOutputs();
        $saveStats = [
            'comparables_total' => 0,
            'comparables_estimable' => 0,
            'comparables_non_estimable' => [],
        ];

        try {
            DB::transaction(function () use (&$saveStats): void {
                $asset = AppraisalAsset::query()->findOrFail($this->assetId);
                $factorIds = $this->resolveFactorIds();
                $summaryValues = [];
                $unitSummaryValues = [];
                $saveStats['comparables_total'] = count($this->comparables);

                foreach ($this->comparables as $comparable) {
                    $comparableId = (int) ($comparable['id'] ?? 0);
                    if ($comparableId <= 0) {
                        continue;
                    }

                    LandAdjustment::query()
                        ->where('appraisal_asset_comparable_id', $comparableId)
                        ->delete();

                    $rows = [];
                    foreach ($this->activeAdjustmentInputKeys() as $factorKey) {
                        $factorId = $factorIds[$factorKey] ?? null;
                        if (! $factorId) {
                            continue;
                        }

                        $percent = $this->normalizeAdjustmentPercent(
                            data_get($this->adjustmentInputs, "{$comparableId}.{$factorKey}")
                        );
                        $amount = $this->toFloat(data_get($this->adjustmentComputed, "{$comparableId}.factors.{$factorKey}.amount"));

                        if (abs($percent) < 0.0001 && ($amount === null || abs($amount) < 0.5)) {
                            continue;
                        }

                        $rows[] = [
                            'appraisal_asset_comparable_id' => $comparableId,
                            'factor_id' => $factorId,
                            'subject_value' => $this->nullIfDash($this->subjectAsset[$factorKey] ?? null),
                            'comparable_value' => $this->nullIfDash($comparable[$factorKey] ?? null),
                            'adjustment_percent' => round($percent, 4),
                            'adjustment_amount' => $amount !== null ? (int) round($amount) : null,
                            'note' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($rows)) {
                        LandAdjustment::query()->insert($rows);
                    }

                    $totalPercent = $this->toFloat(data_get($this->adjustmentComputed, "{$comparableId}.total_percent")) ?? 0.0;
                    $estimatedUnit = $this->toFloat(data_get($this->adjustmentComputed, "{$comparableId}.estimated_unit"));
                    $adjustedUnitValue = $estimatedUnit !== null ? max(0, (int) round($estimatedUnit)) : null;
                    $indicationValue = ($adjustedUnitValue !== null && $this->subjectLandArea !== null && $this->subjectLandArea > 0)
                        ? max(0, (int) round($adjustedUnitValue * $this->subjectLandArea))
                        : null;
                    $canEstimate = (bool) data_get($this->adjustmentComputed, "{$comparableId}.can_estimate", false);

                    AppraisalAssetComparable::query()
                        ->whereKey($comparableId)
                        ->update([
                            'assumed_discount_percent' => $this->normalizeAssumedDiscountPercent(
                                data_get($this->generalInputs, "{$comparableId}.assumed_discount")
                            ),
                            'material_quality_adjustment' => $this->normalizeMaterialQualityAdjustment(
                                data_get($this->generalInputs, "{$comparableId}.material_quality_adj")
                            ),
                            'maintenance_adjustment_delta_percent' => $this->normalizeMaintenanceAdjustmentPercent(
                                data_get($this->generalInputs, "{$comparableId}.maintenance_adj_delta")
                            ),
                            'total_adjustment_percent' => round($totalPercent, 4),
                            'adjusted_unit_value' => $adjustedUnitValue,
                            'indication_value' => $indicationValue,
                            'total_adjustment_percent_low' => round($totalPercent, 4),
                            'total_adjustment_percent_high' => round($totalPercent, 4),
                        ]);

                    if ($indicationValue !== null && $indicationValue > 0) {
                        $summaryValues[] = $indicationValue;
                    }

                    if ($adjustedUnitValue !== null && $adjustedUnitValue > 0) {
                        $unitSummaryValues[] = $adjustedUnitValue;
                    }

                    if ($canEstimate) {
                        $saveStats['comparables_estimable']++;
                    } else {
                        $saveStats['comparables_non_estimable'][] = (string) ($comparable['external_id'] ?? $comparableId);
                    }
                }

                if (! empty($summaryValues)) {
                    $low = (int) min($summaryValues);
                    $high = (int) max($summaryValues);
                    $mid = (int) round(array_sum($summaryValues) / count($summaryValues));
                    $unitMid = ! empty($unitSummaryValues)
                        ? (int) round(array_sum($unitSummaryValues) / count($unitSummaryValues))
                        : null;
                    $buildingValue = (int) ($asset->building_value_final ?? 0);

                    $asset->update([
                        'estimated_value_low' => $low + $buildingValue,
                        'estimated_value_high' => $high + $buildingValue,
                        'market_value_final' => $mid + $buildingValue,
                        'land_value_final' => $unitMid,
                    ]);
                } else {
                    $asset->update([
                        'estimated_value_low' => null,
                        'estimated_value_high' => null,
                        'market_value_final' => null,
                        'land_value_final' => null,
                    ]);
                }
            });
        } catch (Throwable $e) {
            report($e);

            throw $e;
        }

        $body = null;
        if (($saveStats['comparables_estimable'] ?? 0) < ($saveStats['comparables_total'] ?? 0)) {
            $body = 'Sebagian pembanding belum bisa dihitung estimasinya: ' .
                $this->compactList($saveStats['comparables_non_estimable'] ?? []);
        }

        return [
            'notification_body' => $body,
            'save_stats' => $saveStats,
            'state' => $this->exportReviewerPayload(),
        ];
    }

    private function recalculateRangeSummary(): void
    {
        $unitValues = [];
        $valueValues = [];

        foreach ($this->adjustmentComputed as $computed) {
            $unit = $this->toFloat($computed['estimated_unit'] ?? null);
            if ($unit === null || $unit <= 0) {
                continue;
            }

            $unitValues[] = $unit;

            if ($this->subjectLandArea !== null && $this->subjectLandArea > 0) {
                $valueValues[] = max(0, $unit * $this->subjectLandArea);
            }
        }

        if (empty($unitValues)) {
            $this->rangeSummary = [
                'unit_low' => null,
                'unit_high' => null,
                'unit_mid' => null,
                'unit_low_text' => '-',
                'unit_high_text' => '-',
                'unit_mid_text' => '-',
                'value_low' => null,
                'value_high' => null,
                'value_mid' => null,
                'value_low_text' => '-',
                'value_high_text' => '-',
                'value_mid_text' => '-',
            ];

            return;
        }

        $unitLow = min($unitValues);
        $unitHigh = max($unitValues);
        $unitMid = array_sum($unitValues) / count($unitValues);

        $valueLow = ! empty($valueValues) ? min($valueValues) : null;
        $valueHigh = ! empty($valueValues) ? max($valueValues) : null;
        $valueMid = ! empty($valueValues) ? (array_sum($valueValues) / count($valueValues)) : null;

        $this->rangeSummary = [
            'unit_low' => $unitLow,
            'unit_high' => $unitHigh,
            'unit_mid' => $unitMid,
            'unit_low_text' => $this->formatCurrency($unitLow),
            'unit_high_text' => $this->formatCurrency($unitHigh),
            'unit_mid_text' => $this->formatCurrency($unitMid),
            'value_low' => $valueLow,
            'value_high' => $valueHigh,
            'value_mid' => $valueMid,
            'value_low_text' => $this->formatCurrency($valueLow),
            'value_high_text' => $this->formatCurrency($valueHigh),
            'value_mid_text' => $this->formatCurrency($valueMid),
        ];
    }

    private function normalizeAdjustmentPercent(mixed $value): float
    {
        $number = $this->toFloat($value) ?? 0.0;

        return $this->clamp($number, -100.0, 100.0);
    }

    private function normalizeAssumedDiscountPercent(mixed $value): float
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            $number = 5.0;
        }

        return $this->clamp($number, 0.0, 100.0);
    }

    private function normalizeMaterialQualityAdjustment(mixed $value): float
    {
        $number = $this->toFloat($value);

        if ($number === null || $number <= 0) {
            $number = 1.0;
        }

        return round($this->clamp($number, 0.01, 10.0), 4);
    }

    private function normalizeMaintenanceAdjustmentPercent(mixed $value): float
    {
        $number = $this->toFloat($value) ?? 0.0;

        return round($this->clamp($number, -100.0, 100.0), 2);
    }

    private function normalizeMaintenanceAdjustmentFraction(mixed $value): float
    {
        $number = $this->toFloat($value) ?? 0.0;

        return $this->clamp($number, -1.0, 1.0);
    }

    private function ppnPercent(): float
    {
        return $this->valuationSettingNumber(ValuationSetting::KEY_PPN_PERCENT, 11.0) ?? 11.0;
    }

    private function suggestRatioAdjustment(?float $subjectValue, ?float $comparableValue, float $cap = 20.0): ?float
    {
        if ($subjectValue === null || $comparableValue === null) {
            return null;
        }

        if ($subjectValue <= 0 || $comparableValue <= 0) {
            return null;
        }

        $ratio = (($comparableValue - $subjectValue) / $subjectValue) * 100;

        return round($this->clamp($ratio, -$cap, $cap), 2);
    }

    /**
     * @param array<int, string> $values
     */
    private function compactList(array $values, int $max = 4): string
    {
        $items = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== ''));
        if (empty($items)) {
            return '-';
        }

        if (count($items) <= $max) {
            return implode(', ', $items);
        }

        $shown = array_slice($items, 0, $max);
        $rest = count($items) - $max;

        return implode(', ', $shown) . " (+{$rest} lainnya)";
    }

    private function resolveFactorIds(): array
    {
        $result = [];

        foreach ($this->activeAdjustmentInputKeys() as $factorKey) {
            $label = $this->adjustmentFactorLabel($factorKey);
            $fallbackCode = str_replace('adj_', '', $factorKey);

            $factor = AdjustmentFactor::query()
                ->whereIn('code', [$factorKey, $fallbackCode])
                ->orderByRaw("CASE WHEN code = ? THEN 0 ELSE 1 END", [$factorKey])
                ->first();

            if (! $factor) {
                $factor = AdjustmentFactor::query()->create([
                    'code' => $factorKey,
                    'name' => $label,
                    'category' => str_starts_with($factorKey, 'adj_custom_') ? 'custom' : 'spi300',
                    'scope' => 'land',
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            } elseif ($factor->name !== $label && $label !== '') {
                $factor->update(['name' => $label]);
            }

            $result[$factorKey] = (int) $factor->id;
        }

        return $result;
    }

    private function adjustmentFactorLabel(string $factorKey): string
    {
        if (array_key_exists($factorKey, $this->defaultAdjustmentFactors)) {
            return $this->defaultAdjustmentFactors[$factorKey];
        }

        $label = collect($this->customAdjustmentFactors)
            ->firstWhere('key', $factorKey)['label'] ?? null;

        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        return (string) Str::of($factorKey)
            ->replace('adj_custom_', '')
            ->replace('adj_', '')
            ->replace('_', ' ')
            ->title();
    }

    private function nullIfDash(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '' || $text === '-') {
            return null;
        }

        return $text;
    }

    /**
     * @param array<int, AppraisalAssetComparable> $comparableModels
     */
    private function hydrateCustomFactorsFromModels(array $comparableModels): void
    {
        $existing = [];

        foreach ($comparableModels as $model) {
            foreach ($model->landAdjustments as $adjustment) {
                $key = $this->resolveAdjustmentKey(
                    data_get($adjustment, 'factor.code'),
                    data_get($adjustment, 'factor.name'),
                );

                if (! is_string($key) || ! str_starts_with($key, 'adj_custom_')) {
                    continue;
                }

                $label = trim((string) data_get($adjustment, 'factor.name'));
                if ($label === '') {
                    $label = (string) Str::of($key)
                        ->replace('adj_custom_', '')
                        ->replace('_', ' ')
                        ->title();
                }

                $existing[$key] = $label;
            }
        }

        if (empty($existing)) {
            return;
        }

        $this->customAdjustmentFactors = collect($existing)
            ->map(fn(string $label, string $key): array => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $label, string $key, array $options = []): array
    {
        return [
            'key' => $key,
            'type' => $options['type'] ?? 'data',
            'indent' => (int) ($options['indent'] ?? 0),
            'label' => $label,
            'subject' => (string) ($this->subjectAsset[$key] ?? '-'),
            'comparables' => collect($this->comparables)
                ->map(fn(array $item): string => (string) ($item[$key] ?? '-'))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowGroup(string $label): array
    {
        return [
            'key' => null,
            'type' => 'group',
            'indent' => 0,
            'label' => $label,
            'subject' => '',
            'comparables' => array_fill(0, count($this->comparables), ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateLandResidualContext(
        ?float $askingPrice,
        ?float $likelySale,
        ?float $landArea,
        ?float $buildingArea,
        ?int $builtYear,
        int $valuationYear,
        ?string $buildingType,
        ?string $buildingClass,
        ?int $floorCount,
        ?string $preferredStoreyPattern,
        float $materialQualityAdj,
        float $maintenanceAdjDeltaPercent,
        ?float $ikkValue,
        ?string $conditionLabel,
        array $snapshot,
    ): array {
        $basePrice = $likelySale ?? $askingPrice;

        if ($basePrice === null) {
            return [
                'rcn_standard_ref' => '-',
                'material_quality_adj_ref' => '-',
                'rcn_adjusted_ref' => '-',
                'maintenance_ref' => '-',
                'maintenance_remaining_text' => '-',
                'maintenance_final_text' => '-',
                'maintenance_detail_text' => '-',
                'building_value_sqm_ref' => '-',
                'building_value_ref' => '-',
                'residual_land_value_ref' => '-',
                'residual_land_value_sqm_ref' => '-',
                'residual_land_value_base' => null,
                'residual_land_value_sqm_base' => null,
            ];
        }

        $hasBuilding = $buildingArea !== null && $buildingArea > 0;

        if (! $hasBuilding) {
            $unit = ($landArea !== null && $landArea > 0)
                ? $basePrice / $landArea
                : null;

            return [
                'rcn_standard_ref' => 'N/A (tanah kosong)',
                'material_quality_adj_ref' => 'N/A',
                'rcn_adjusted_ref' => 'N/A',
                'maintenance_ref' => 'N/A',
                'maintenance_remaining_text' => 'N/A',
                'maintenance_final_text' => 'N/A',
                'maintenance_detail_text' => 'N/A',
                'building_value_sqm_ref' => 'N/A',
                'building_value_ref' => 'N/A',
                'residual_land_value_ref' => $this->formatCurrency($basePrice),
                'residual_land_value_sqm_ref' => $this->formatCurrency($unit),
                'residual_land_value_base' => $basePrice,
                'residual_land_value_sqm_base' => $unit,
            ];
        }

        $baseRcnMappi = $this->baseRcnFromReference($buildingType, $buildingClass, $floorCount, $preferredStoreyPattern) ?? $this->firstNumber([
            data_get($snapshot, 'rcn_standar_mappi'),
            data_get($snapshot, 'rcn_standard_mappi'),
            data_get($snapshot, 'rcn_mappi'),
            data_get($snapshot, 'rcn_standard'),
            data_get($snapshot, 'rcn'),
        ]);

        $adjSuggested = $this->normalizeMaintenanceAdjustmentFraction($this->firstNumber([
            data_get($snapshot, 'adj_suggested'),
            data_get($snapshot, 'penyesuaian_otomatis'),
        ]));
        $adjDelta = $maintenanceAdjDeltaPercent / 100;

        $ikkFactor = $ikkValue ?? 1.0;
        $ppnPercent = $this->ppnPercent();
        $ppnFactor = 1 + ($ppnPercent / 100);
        $floorIndexFactor = $this->floorIndexValue($buildingClass, $floorCount) ?? 1.0;

        $rcnStandard = $baseRcnMappi !== null
            ? (float) round($baseRcnMappi * $ppnFactor * $ikkFactor * $floorIndexFactor, -2)
            : null;
        $rcnAdjusted = $rcnStandard !== null ? ($rcnStandard * $materialQualityAdj) : null;

        $age = $builtYear !== null ? max(0, $valuationYear - $builtYear) : null;
        $conditionMultiplier = $this->conditionMultiplier($conditionLabel);
        $effectiveAge = $age !== null ? ($age * $conditionMultiplier) : null;

        $economicLife = $this->economicLifeValue($buildingClass, $floorCount) ?? 50;
        $remaining = ($effectiveAge !== null && $economicLife > 0)
            ? $this->clamp(1.0 - ($effectiveAge / $economicLife), 0.0, 1.0)
            : null;
        $finalFactor = $remaining !== null
            ? $this->clamp($remaining + $adjSuggested + $adjDelta, 0.0, 1.0)
            : null;

        $buildingValuePerSqm = ($finalFactor !== null && $rcnAdjusted !== null)
            ? ($finalFactor * $rcnAdjusted)
            : null;

        $buildingValue = $buildingValuePerSqm !== null
            ? ($buildingValuePerSqm * $buildingArea)
            : null;

        if ($buildingValue === null) {
            $unit = ($landArea !== null && $landArea > 0) ? $basePrice / $landArea : null;
            return [
                'rcn_standard_ref'         => '-',
                'material_quality_adj_ref' => '-',
                'rcn_adjusted_ref'         => '-',
                'maintenance_ref'          => 'RCN tidak dapat dihitung (data referensi tidak tersedia)',
                'maintenance_remaining_text' => '-',
                'maintenance_final_text' => '-',
                'maintenance_detail_text' => 'RCN tidak dapat dihitung (data referensi tidak tersedia)',
                'building_value_sqm_ref'   => '-',
                'building_value_ref'       => '-',
                'residual_land_value_ref'      => $this->formatCurrency($basePrice),
                'residual_land_value_sqm_ref'  => $this->formatCurrency($unit),
                'residual_land_value_base'     => $basePrice,
                'residual_land_value_sqm_base' => $unit,
            ];
        }

        $residualLandValue = $buildingValue !== null
            ? ($basePrice - $buildingValue)
            : null;

        $residualLandValuePerSqm = ($residualLandValue !== null && $landArea !== null && $landArea > 0)
            ? ($residualLandValue / $landArea)
            : null;

        $remainingPercent = $remaining !== null ? number_format($remaining * 100, 2, ',', '.') . '%' : '-';
        $finalPercent = $finalFactor !== null ? number_format($finalFactor * 100, 2, ',', '.') . '%' : '-';
        $ageText = $age !== null ? (string) $age : '-';
        $effectiveAgeText = $effectiveAge !== null ? number_format($effectiveAge, 2, ',', '.') : '-';
        $lifeText = $economicLife > 0 ? (string) $economicLife : '-';
        $maintenanceText = "Age {$ageText} | Effective {$effectiveAgeText} | Life {$lifeText} | Remaining {$remainingPercent} | Final {$finalPercent}";

        return [
            'rcn_standard_ref' => $this->formatCurrency($rcnStandard),
            'material_quality_adj_ref' => $this->formatDecimal($materialQualityAdj, 4),
            'rcn_adjusted_ref' => $this->formatCurrency($rcnAdjusted),
            'maintenance_ref' => $maintenanceText,
            'maintenance_remaining_text' => $remainingPercent,
            'maintenance_final_text' => $finalPercent,
            'maintenance_detail_text' => "Age {$ageText} | Effective {$effectiveAgeText} | Life {$lifeText} | Remaining {$remainingPercent}",
            'building_value_sqm_ref' => $this->formatCurrency($buildingValuePerSqm),
            'building_value_ref' => $this->formatCurrency($buildingValue),
            'residual_land_value_ref' => $this->formatCurrency($residualLandValue),
            'residual_land_value_sqm_ref' => $this->formatCurrency($residualLandValuePerSqm),
            'residual_land_value_base' => $residualLandValue,
            'residual_land_value_sqm_base' => $residualLandValuePerSqm,
        ];
    }

    private function economicLifeValue(?string $buildingClass, ?int $floorCount): ?int
    {
        if (! $this->guidelineSetId || ! $this->guidelineYear) {
            return null;
        }

        $classKey = strtolower(trim((string) $buildingClass));
        $floorKey = $floorCount ?? 0;
        $cacheKey = "{$this->guidelineSetId}|{$this->guidelineYear}|{$classKey}|{$floorKey}";

        if (array_key_exists($cacheKey, $this->economicLifeCache)) {
            return $this->economicLifeCache[$cacheKey];
        }

        $query = BuildingEconomicLife::query()
            ->where('guideline_item_id', $this->guidelineSetId)
            ->where('year', $this->guidelineYear);

        if ($classKey !== '') {
            $query->whereRaw('LOWER(building_class) = ?', [$classKey]);
        }

        if ($floorCount !== null) {
            $query->where(function ($scope) use ($floorCount) {
                $scope
                    ->where(function ($q) use ($floorCount) {
                        $q->whereNotNull('storey_min')
                            ->whereNotNull('storey_max')
                            ->where('storey_min', '<=', $floorCount)
                            ->where('storey_max', '>=', $floorCount);
                    })
                    ->orWhere(function ($q) use ($floorCount) {
                        $q->whereNotNull('storey_min')
                            ->whereNull('storey_max')
                            ->where('storey_min', '<=', $floorCount);
                    })
                    ->orWhere(function ($q) use ($floorCount) {
                        $q->whereNull('storey_min')
                            ->whereNotNull('storey_max')
                            ->where('storey_max', '>=', $floorCount);
                    })
                    ->orWhere(function ($q) {
                        $q->whereNull('storey_min')
                            ->whereNull('storey_max');
                    });
            });
        }

        $record = $query
            ->orderByDesc('storey_min')
            ->first();

        if (! $record && $classKey !== '') {
            $record = BuildingEconomicLife::query()
                ->where('guideline_item_id', $this->guidelineSetId)
                ->where('year', $this->guidelineYear)
                ->orderByDesc('storey_min')
                ->first();
        }

        $this->economicLifeCache[$cacheKey] = $record?->economic_life
            ? (int) $record->economic_life
            : null;

        return $this->economicLifeCache[$cacheKey];
    }

    private function baseRcnFromReference(?string $buildingType, ?string $buildingClass, ?int $floorCount, ?string $preferredStoreyPattern = null): ?float
    {
        $standardValue = $this->baseRcnFromStandardReference($buildingType, $buildingClass, $floorCount, $preferredStoreyPattern);

        if ($standardValue !== null) {
            return $standardValue;
        }

        if (! $this->guidelineSetId || ! $this->guidelineYear) {
            return null;
        }

        $typeKey = strtolower(trim((string) $buildingType));
        $classKey = strtolower(trim((string) $buildingClass));
        $floorKey = $floorCount ?? 0;

        $cacheKey = "{$this->guidelineSetId}|{$this->guidelineYear}|{$typeKey}|{$classKey}|{$floorKey}";

        if (array_key_exists($cacheKey, $this->baseRcnCache)) {
            return $this->baseRcnCache[$cacheKey];
        }

        $candidates = [];

        if ($typeKey !== '' && $classKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
        } elseif ($typeKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'any', 'class_value' => null];
        } elseif ($classKey !== '') {
            $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'any', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
        }

        $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'blank', 'class_value' => null];

        foreach ($candidates as $candidate) {
            $query = CostElement::query()
                ->where('guideline_set_id', $this->guidelineSetId)
                ->where('year', $this->guidelineYear);

            $this->applyCostDimensionFilter(
                $query,
                'building_type',
                $candidate['type'],
                $candidate['type_value']
            );

            $this->applyCostDimensionFilter(
                $query,
                'building_class',
                $candidate['class'],
                $candidate['class_value']
            );

            $rows = $query
                ->get(['unit_cost', 'storey_pattern'])
                ->filter(fn(CostElement $row): bool => $this->storeyPatternMatches($row->storey_pattern, $floorCount, $preferredStoreyPattern))
                ->values();

            if ($rows->isEmpty()) {
                continue;
            }

            $sum = $rows->sum(fn(CostElement $row): float => (float) $row->unit_cost);

            if ($sum > 0) {
                $this->baseRcnCache[$cacheKey] = $sum;

                return $sum;
            }
        }

        $this->baseRcnCache[$cacheKey] = null;

        return null;
    }

    private function baseRcnFromStandardReference(?string $buildingType, ?string $buildingClass, ?int $floorCount, ?string $preferredStoreyPattern = null): ?float
    {
        if (! $this->guidelineSetId || ! $this->guidelineYear) {
            return null;
        }

        $typeKey = strtolower(trim((string) $buildingType));
        $classKey = strtolower(trim((string) $buildingClass));
        $floorKey = $floorCount ?? 0;

        $cacheKey = "std|{$this->guidelineSetId}|{$this->guidelineYear}|{$typeKey}|{$classKey}|{$floorKey}";

        if (array_key_exists($cacheKey, $this->baseRcnCache)) {
            return $this->baseRcnCache[$cacheKey];
        }

        $candidates = [];

        if ($typeKey !== '' && $classKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
        } elseif ($typeKey !== '') {
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'blank', 'class_value' => null];
            $candidates[] = ['type' => 'exact', 'type_value' => $typeKey, 'class' => 'any', 'class_value' => null];
        } elseif ($classKey !== '') {
            $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
            $candidates[] = ['type' => 'any', 'type_value' => null, 'class' => 'exact', 'class_value' => $classKey];
        }

        $candidates[] = ['type' => 'blank', 'type_value' => null, 'class' => 'blank', 'class_value' => null];

        foreach ($candidates as $candidate) {
            $query = MappiRcnStandard::query()
                ->where('guideline_set_id', $this->guidelineSetId)
                ->where('year', $this->guidelineYear);

            $this->applyCostDimensionFilter(
                $query,
                'building_type',
                $candidate['type'],
                $candidate['type_value']
            );

            $this->applyCostDimensionFilter(
                $query,
                'building_class',
                $candidate['class'],
                $candidate['class_value']
            );

            $record = $query
                ->get(['rcn_value', 'storey_pattern'])
                ->first(fn(MappiRcnStandard $row): bool => $this->storeyPatternMatches($row->storey_pattern, $floorCount, $preferredStoreyPattern));

            if (! $record) {
                continue;
            }

            $value = $this->toFloat($record->rcn_value);

            if ($value !== null && $value > 0) {
                $this->baseRcnCache[$cacheKey] = $value;

                return $value;
            }
        }

        $this->baseRcnCache[$cacheKey] = null;

        return null;
    }

    private function applyCostDimensionFilter(Builder $query, string $column, string $mode, ?string $value): void
    {
        if ($mode === 'any') {
            return;
        }

        if ($mode === 'blank') {
            $query->where(function (Builder $scope) use ($column) {
                $scope->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($mode === 'exact' && $value !== null && $value !== '') {
            $query->whereRaw("LOWER({$column}) = ?", [$value]);
        }
    }

    private function storeyPatternMatches(?string $pattern, ?int $floorCount, ?string $preferredPattern = null): bool
    {
        $text = strtolower(trim((string) $pattern));
        $preferred = strtolower(trim((string) $preferredPattern));

        if ($preferred !== '' && $text !== '' && $text === $preferred) {
            return true;
        }

        if ($text === '' || $text === '-') {
            return true;
        }

        if ($floorCount === null || $floorCount <= 0) {
            return false;
        }

        if (preg_match('/>=\s*(\d+)/', $text, $match)) {
            return $floorCount >= (int) $match[1];
        }

        if (preg_match('/<=\s*(\d+)/', $text, $match)) {
            return $floorCount <= (int) $match[1];
        }

        if (preg_match('/(\d+)\s*(?:-|s\/d|sd|to)\s*(\d+)/', $text, $match)) {
            $min = (int) $match[1];
            $max = (int) $match[2];

            return $floorCount >= min($min, $max) && $floorCount <= max($min, $max);
        }

        preg_match_all('/\d+/', $text, $allNumbers);
        $numbers = collect($allNumbers[0] ?? [])->map(fn($n) => (int) $n)->filter()->values()->all();

        if (count($numbers) === 1) {
            return $floorCount === $numbers[0];
        }

        if (count($numbers) > 1) {
            return in_array($floorCount, $numbers, true);
        }

        return false;
    }

    private function conditionMultiplier(?string $condition): float
    {
        $text = strtolower(trim((string) $condition));

        if ($text === '') {
            return 1.0;
        }

        return match (true) {
            str_contains($text, 'sangat baik'),
            str_contains($text, 'excellent') => 0.80,

            str_contains($text, 'baik'),
            str_contains($text, 'terawat'),
            str_contains($text, 'matang') => 0.90,

            str_contains($text, 'buruk'),
            str_contains($text, 'rusak'),
            str_contains($text, 'kurang') => 1.15,

            default => 1.0,
        };
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function usageMapping(?string $peruntukan): ?RefUsageToMappiGroup
    {
        $key = strtolower(trim((string) $peruntukan));

        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->usageMappingCache)) {
            return $this->usageMappingCache[$key];
        }

        $mapping = RefUsageToMappiGroup::query()
            ->whereRaw('LOWER(peruntukan_enum) = ?', [$key])
            ->first();

        $this->usageMappingCache[$key] = $mapping;

        return $mapping;
    }

    private function resolveMappiBuildingType(?string $mappedType, ?string $peruntukan, array $snapshot): ?string
    {
        $explicit = $this->firstFilled([
            data_get($snapshot, 'mappi_building_type'),
            data_get($snapshot, 'btb_building_type'),
        ], null);

        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        if (is_string($mappedType) && trim($mappedType) !== '') {
            return trim($mappedType);
        }

        return null;
    }

    private function resolveMappiBuildingClass(?string $mappedClass, ?string $peruntukan, array $snapshot): ?string
    {
        $explicit = $this->firstFilled([
            data_get($snapshot, 'mappi_building_class'),
            data_get($snapshot, 'kelas_bangunan'),
            data_get($snapshot, 'building_class'),
        ], null);

        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        if (is_string($mappedClass) && trim($mappedClass) !== '') {
            return trim($mappedClass);
        }

        return null;
    }

    private function resolvePreferredStoreyPattern(?RefUsageToMappiGroup $mapping, array $snapshot): ?string
    {
        $explicit = $this->firstFilled([
            data_get($snapshot, 'mappi_storey_pattern'),
            data_get($snapshot, 'storey_pattern'),
            data_get($snapshot, 'default_storey_group'),
        ], null);

        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $mapped = $mapping?->storeyGroup();

        if (is_string($mapped) && trim($mapped) !== '') {
            return trim($mapped);
        }

        return null;
    }

    private function floorIndexValue(?string $buildingClass, ?int $floorCount): ?float
    {
        if (! $this->guidelineSetId || ! $this->guidelineYear || ! $floorCount) {
            return null;
        }

        $classKey = strtolower(trim((string) $buildingClass));
        $cacheKey = "{$this->guidelineSetId}|{$this->guidelineYear}|{$classKey}|{$floorCount}";

        if (array_key_exists($cacheKey, $this->floorIndexCache)) {
            return $this->floorIndexCache[$cacheKey];
        }

        $query = FloorIndex::query()
            ->where('guideline_set_id', $this->guidelineSetId)
            ->where('year', $this->guidelineYear)
            ->where('floor_count', $floorCount);

        $value = null;

        if ($classKey !== '') {
            $value = (clone $query)
                ->whereRaw('LOWER(building_class) = ?', [$classKey])
                ->value('il_value');
        }

        if ($value === null) {
            $value = $query->value('il_value');
        }

        $this->floorIndexCache[$cacheKey] = $value !== null ? (float) $value : null;

        return $this->floorIndexCache[$cacheKey];
    }

    private function ikkValue(?string $regionCode): ?float
    {
        $region = $this->normalizeRegionCode($regionCode);

        if (! $this->guidelineSetId || ! $this->guidelineYear || ! $region) {
            return null;
        }

        $cacheKey = "{$this->guidelineSetId}|{$this->guidelineYear}|{$region}";

        if (array_key_exists($cacheKey, $this->ikkCache)) {
            return $this->ikkCache[$cacheKey];
        }

        $value = ConstructionCostIndex::query()
            ->where('guideline_set_id', $this->guidelineSetId)
            ->where('year', $this->guidelineYear)
            ->where('region_code', $region)
            ->value('ikk_value');

        $this->ikkCache[$cacheKey] = $value !== null ? (float) $value : null;

        return $this->ikkCache[$cacheKey];
    }

    private function resolveRegionCodeFromSnapshot(array $snapshot): ?string
    {
        $directCode = $this->normalizeRegionCode($this->firstFilled([
            data_get($snapshot, 'regency.id'),
            data_get($snapshot, 'region_code'),
            data_get($snapshot, 'regency_id'),
        ], null));

        if ($directCode !== null) {
            return $directCode;
        }

        $regencyName = trim((string) $this->firstFilled([
            data_get($snapshot, 'regency.name'),
            data_get($snapshot, 'kabupaten.name'),
            data_get($snapshot, 'kabupaten'),
            data_get($snapshot, 'kota_kabupaten'),
            data_get($snapshot, 'city'),
            data_get($snapshot, 'regency'),
        ], ''));

        if ($regencyName === '') {
            return null;
        }

        $provinceName = trim((string) $this->firstFilled([
            data_get($snapshot, 'province.name'),
            data_get($snapshot, 'provinsi.name'),
            data_get($snapshot, 'provinsi'),
            data_get($snapshot, 'province'),
        ], ''));

        $query = Regency::query()->select(['id', 'province_id']);

        if ($provinceName !== '') {
            $query->whereHas('province', function (Builder $scope) use ($provinceName): void {
                $scope->whereRaw('LOWER(name) = ?', [mb_strtolower($provinceName)]);
            });
        }

        $record = (clone $query)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($regencyName)])
            ->first();

        if (! $record) {
            $record = (clone $query)
                ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($regencyName) . '%'])
                ->first();
        }

        return $this->normalizeRegionCode($record?->id);
    }

    private function provinceName(?string $provinceId): ?string
    {
        $id = trim((string) $provinceId);

        if ($id === '') {
            return null;
        }

        if (array_key_exists($id, $this->provinceNameCache)) {
            return $this->provinceNameCache[$id];
        }

        $this->provinceNameCache[$id] = Province::query()->whereKey($id)->value('name');

        return $this->provinceNameCache[$id];
    }

    private function regencyName(?string $regencyId): ?string
    {
        $id = trim((string) $regencyId);

        if ($id === '') {
            return null;
        }

        if (array_key_exists($id, $this->regencyNameCache)) {
            return $this->regencyNameCache[$id];
        }

        $this->regencyNameCache[$id] = Regency::query()->whereKey($id)->value('name');

        return $this->regencyNameCache[$id];
    }

    private function valuationSettingNumber(string $key, ?float $default = null): ?float
    {
        if (! $this->guidelineSetId || ! $this->guidelineYear) {
            return $default;
        }

        $cacheKey = "{$this->guidelineSetId}|{$this->guidelineYear}|{$key}";

        if (array_key_exists($cacheKey, $this->valuationSettingNumberCache)) {
            return $this->valuationSettingNumberCache[$cacheKey] ?? $default;
        }

        $value = ValuationSetting::query()
            ->where('guideline_set_id', $this->guidelineSetId)
            ->where('year', $this->guidelineYear)
            ->where('key', $key)
            ->value('value_number');

        $this->valuationSettingNumberCache[$cacheKey] = $value !== null ? (float) $value : null;

        return $this->valuationSettingNumberCache[$cacheKey] ?? $default;
    }

    private function normalizeRegionCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return str_pad($digits, 4, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    private function firstFilled(array $values, mixed $default = '-'): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                return $value;
            }
        }

        return $default;
    }

    private function firstInt(array $values): ?int
    {
        foreach ($values as $value) {
            $int = $this->toInt($value);
            if ($int !== null) {
                return $int;
            }
        }

        return null;
    }

    private function firstNumber(array $values): ?float
    {
        foreach ($values as $value) {
            $number = $this->toFloat($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function normalizeDiscountFraction(?float $discount): ?float
    {
        if ($discount === null) {
            return null;
        }

        if (abs($discount) > 1) {
            return $discount / 100;
        }

        return $discount;
    }

    private function formatUnsignedPercent(mixed $value, int $decimals = 2): string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return '-';
        }

        return number_format($number, $decimals, ',', '.') . '%';
    }

    private function resolveEffectiveBuildingArea(
        ?float $buildingAreaRaw,
        ?float $buildingAreaSnapshot,
    ): ?float {
        $raw = ($buildingAreaRaw !== null && $buildingAreaRaw > 0) ? $buildingAreaRaw : null;
        $snapshot = ($buildingAreaSnapshot !== null && $buildingAreaSnapshot > 0) ? $buildingAreaSnapshot : null;

        if ($raw !== null && $snapshot !== null) {
            // Kalau raw dan snapshot sama, prioritaskan raw.
            if (abs($raw - $snapshot) < 0.0001) {
                return $raw;
            }

            // Jika berbeda, sementara tetap utamakan raw karena ini hasil mapping field internal.
            return $raw;
        }

        if ($raw !== null) {
            return $raw;
        }

        if ($snapshot !== null) {
            return $snapshot;
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $clean = preg_replace('/[^0-9,.\-]/', '', $value);
            if ($clean === null || $clean === '' || $clean === '-') {
                return null;
            }

            $hasComma = str_contains($clean, ',');
            $hasDot = str_contains($clean, '.');

            if ($hasComma && $hasDot) {
                if (strrpos($clean, ',') > strrpos($clean, '.')) {
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } else {
                    $clean = str_replace(',', '', $clean);
                }
            } elseif ($hasComma && ! $hasDot) {
                $parts = explode(',', $clean);
                if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                    $clean = str_replace(',', '.', $clean);
                } else {
                    $clean = str_replace(',', '', $clean);
                }
            }

            if (is_numeric($clean)) {
                return (float) $clean;
            }
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function text(mixed $value): string
    {
        $text = $this->stringifyDisplayValue($value);

        if ($text === null || $text === '') {
            return '-';
        }

        return $text;
    }

    private function displayOptionLabel(mixed $value): string
    {
        $text = $this->stringifyDisplayValue($value);

        if ($text === null || $text === '') {
            return '-';
        }

        $normalized = strtolower($text);

        if (in_array($normalized, ['shm', 'hgb'], true)) {
            return strtoupper($normalized);
        }

        return (string) Str::of($text)
            ->replace(['_', '-'], ' ')
            ->headline();
    }

    private function stringifyDisplayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $text = trim($value);

            return $text !== '' ? $text : null;
        }

        if (is_numeric($value) || is_bool($value)) {
            $text = trim((string) $value);

            return $text !== '' ? $text : null;
        }

        if (is_array($value)) {
            foreach (['name', 'label', 'title', 'value', 'text'] as $preferredKey) {
                if (array_key_exists($preferredKey, $value)) {
                    $preferred = $this->stringifyDisplayValue($value[$preferredKey]);

                    if ($preferred !== null) {
                        return $preferred;
                    }
                }
            }

            $parts = [];

            foreach ($value as $item) {
                $text = $this->stringifyDisplayValue($item);

                if ($text !== null) {
                    $parts[] = $text;
                }
            }

            $parts = array_values(array_unique($parts));

            return $parts !== [] ? implode(', ', $parts) : null;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $this->stringifyDisplayValue($value->toArray());
            }

            return $this->stringifyDisplayValue(get_object_vars($value));
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function formatCoordinates(mixed $lat, mixed $lng): string
    {
        $latitude = $this->toFloat($lat);
        $longitude = $this->toFloat($lng);

        if ($latitude === null || $longitude === null) {
            return '-';
        }

        return number_format($latitude, 6, '.', '') . ', ' . number_format($longitude, 6, '.', '');
    }

    private function formatArea(mixed $value): string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return '-';
        }

        return number_format($number, 2, ',', '.') . ' m2';
    }

    private function formatMeter(mixed $value): string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return '-';
        }

        return number_format($number, 2, ',', '.') . ' meter';
    }

    private function formatCurrency(mixed $value): string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return '-';
        }

        return 'Rp ' . number_format($number, 0, ',', '.');
    }

    private function formatDistance(mixed $value): string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return '-';
        }

        return '+/- ' . number_format($number, 0, ',', '.') . ' meter';
    }

    private function formatPercent(mixed $value): string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return '-';
        }

        if (abs($number) <= 1) {
            $number *= 100;
        }

        return number_format($number, 2, ',', '.') . '%';
    }

    private function formatDecimal(?float $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format($value, $decimals, '.', '');
    }

    private function formatMonthYear(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($value instanceof Carbon) {
            return $value->translatedFormat('M Y');
        }

        try {
            return Carbon::parse($value)->translatedFormat('M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }
}
