<?php

namespace App\Services\Reviewer;

use App\Models\CostElement;
use App\Models\GuidelineSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BtbWorkbookImportService
{
    public function __construct(
        private readonly BtbWorkbookTemplateParser $parser,
    ) {
    }

    public function import(string $path, GuidelineSet $guidelineSet, ?int $year = null, string $baseRegion = 'DKI Jakarta', bool $dryRun = false): array
    {
        $year ??= (int) $guidelineSet->year;
        $parsed = $this->parser->parse($path);

        $callback = function () use ($parsed, $guidelineSet, $year, $baseRegion, $dryRun, $path): array {
            $templates = [];
            $totals = [
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
                'unchanged' => 0,
            ];

            foreach ($parsed['templates'] as $templateKey => $template) {
                $dimensions = [
                    'guideline_set_id' => $guidelineSet->id,
                    'year' => $year,
                    'base_region' => $baseRegion,
                    'building_type' => $template['building_type'],
                    'building_class' => $template['building_class'],
                    'storey_pattern' => $template['storey_pattern'],
                ];

                $incomingRows = $template['rows'];
                $incomingCodes = collect($incomingRows)->pluck('element_code')->values()->all();

                $query = $this->applyDimensions(CostElement::query(), $dimensions);

                $existingByCode = $query
                    ->get()
                    ->keyBy('element_code');

                $templateStats = [
                    'template_key' => $templateKey,
                    'sheet_name' => $template['sheet_name'],
                    'imported_rows' => count($incomingRows),
                    'sheet_summary_rows' => count($template['sheet_summary_rows'] ?? []),
                    'created' => 0,
                    'updated' => 0,
                    'deleted' => 0,
                    'unchanged' => 0,
                ];

                $staleCodes = collect($existingByCode->keys())
                    ->diff($incomingCodes)
                    ->values();

                if (! $dryRun && $staleCodes->isNotEmpty()) {
                    CostElement::query()
                        ->tap(fn (Builder $query) => $this->applyDimensions($query, $dimensions))
                        ->whereIn('element_code', $staleCodes->all())
                        ->delete();
                }

                $templateStats['deleted'] = $staleCodes->count();

                foreach ($incomingRows as $row) {
                    $record = $existingByCode->get($row['element_code']);
                    $payload = [
                        'group' => $row['group'],
                        'element_name' => $row['element_name'],
                        'unit' => $row['unit'],
                        'unit_cost' => $row['unit_cost'],
                        'spec_json' => $row['spec_json'],
                    ];

                    if (! $record) {
                        if (! $dryRun) {
                            CostElement::query()->create($dimensions + [
                                'element_code' => $row['element_code'],
                            ] + $payload);
                        }

                        $templateStats['created']++;
                        continue;
                    }

                    $record->fill($payload);

                    if ($record->isDirty()) {
                        if (! $dryRun) {
                            $record->save();
                        }

                        $templateStats['updated']++;
                        continue;
                    }

                    $templateStats['unchanged']++;
                }

                foreach (['created', 'updated', 'deleted', 'unchanged'] as $key) {
                    $totals[$key] += $templateStats[$key];
                }

                $templates[] = $templateStats;
            }

            return [
                'path' => $path,
                'guideline_set_id' => $guidelineSet->id,
                'year' => $year,
                'base_region' => $baseRegion,
                'dry_run' => $dryRun,
                'totals' => $totals,
                'templates' => $templates,
                'sheet_summary' => $parsed['sheet_summary'],
            ];
        };

        return $dryRun ? $callback() : DB::transaction($callback);
    }

    private function applyDimensions(Builder $query, array $dimensions): Builder
    {
        foreach ($dimensions as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }
}
