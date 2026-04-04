<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\AdjustmentStateRequest;
use App\Models\AppraisalAsset;
use App\Services\Reviewer\ReviewerWorkspaceService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AdjustmentController extends Controller
{
    public function __construct(
        private readonly ReviewerWorkspaceService $workspace,
    ) {
    }

    public function preview(AdjustmentStateRequest $request, AppraisalAsset $asset): JsonResponse
    {
        $data = $request->validated();

        $workbench = $this->workspace->makeAdjustmentWorkbench($asset->id);
        $workbench->syncClientState(
            $data['adjustment_inputs'] ?? [],
            $data['custom_adjustment_factors'] ?? [],
            $data['general_inputs'] ?? [],
        );

        return response()->json([
            'message' => 'Preview adjustment diperbarui.',
            'state' => $workbench->exportReviewerPayload(),
        ]);
    }

    public function save(AdjustmentStateRequest $request, AppraisalAsset $asset): JsonResponse
    {
        $data = $request->validated();

        try {
            $workbench = $this->workspace->makeAdjustmentWorkbench($asset->id);
            $workbench->syncClientState(
                $data['adjustment_inputs'] ?? [],
                $data['custom_adjustment_factors'] ?? [],
                $data['general_inputs'] ?? [],
            );

            $result = $workbench->persistAdjustmentData();
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Gagal menyimpan adjustment.',
            ], 422);
        }

        return response()->json([
            'message' => 'Adjustment berhasil disimpan.',
            'result' => $result,
        ]);
    }
}
