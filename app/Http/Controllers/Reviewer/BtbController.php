<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\BtbWorksheetRequest;
use App\Models\AppraisalAsset;
use App\Services\Reviewer\BtbPayloadBuilder;
use App\Services\Reviewer\BtbValuationPersistenceService;
use App\Services\Reviewer\ReviewerWorkspaceService;
use Illuminate\Http\JsonResponse;

class BtbController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
        private readonly BtbPayloadBuilder $btbPayloadBuilder,
        private readonly BtbValuationPersistenceService $btbPersistence,
    ) {
    }

    public function preview(BtbWorksheetRequest $request, AppraisalAsset $asset): JsonResponse
    {
        $this->ensureBtbAsset($asset);
        $data = $request->validated();

        return response()->json([
            'message' => 'Preview BTB diperbarui.',
            'btb' => $this->btbPayloadBuilder->build($asset, $data['btb_input'] ?? []),
        ]);
    }

    public function save(BtbWorksheetRequest $request, AppraisalAsset $asset): JsonResponse
    {
        $this->ensureBtbAsset($asset);
        $data = $request->validated();
        $payload = $this->btbPayloadBuilder->build($asset, $data['btb_input'] ?? []);

        if (! data_get($payload, 'state')) {
            return response()->json([
                'message' => 'Worksheet BTB belum memiliki hasil perhitungan yang bisa disimpan.',
            ], 422);
        }

        $result = $this->btbPersistence->persist($asset, (array) $payload['state']);

        return response()->json([
            'message' => 'Worksheet BTB berhasil disimpan.',
            'result' => [
                'btb' => $this->btbPayloadBuilder->build($asset->fresh(['request.guidelineSet', 'ikkRef', 'buildingValuation']), $data['btb_input'] ?? []),
                'asset_values' => [
                    'building_value_final' => $result['asset']->building_value_final,
                    'estimated_value_low' => $result['asset']->estimated_value_low,
                    'estimated_value_high' => $result['asset']->estimated_value_high,
                    'market_value_final' => $result['asset']->market_value_final,
                ],
            ],
        ]);
    }

    private function ensureBtbAsset(AppraisalAsset $asset): void
    {
        abort_unless($this->workspace->assetHasBtb($asset), 404);
    }
}
