<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Services\Admin\AppraisalRequestWorkflowService;
use App\Support\Admin\AppraisalAssetFormBuilder;
use App\Support\Admin\AppraisalRequestActionResolver;
use App\Support\Admin\AppraisalRequestAdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait InteractsWithAppraisalRequests
{
    private function transformRequestTableRow(AppraisalRequest $record): array
    {
        return $this->appraisalRequestPresenter()->requestTableRow($record);
    }

    private function formatNegotiationAction(?string $action): string
    {
        return $this->appraisalRequestPresenter()->formatNegotiationAction($action);
    }

    private function negotiationActionTone(?string $action): string
    {
        return $this->appraisalRequestPresenter()->negotiationActionTone($action);
    }

    private function buildLocationMaps(AppraisalRequest $appraisalRequest): array
    {
        return $this->appraisalRequestPresenter()->buildLocationMaps($appraisalRequest);
    }

    private function transformRequestFile(object $file): array
    {
        return $this->appraisalRequestPresenter()->requestFile($file);
    }

    private function transformAsset(
        AppraisalAsset $asset,
        int $order,
        array $locationMaps,
        ?Collection $activeFiles = null
    ): array {
        return $this->appraisalRequestPresenter()->asset($asset, $order, $locationMaps, $activeFiles);
    }

    private function transformRevisionBatch(object $batch, AppraisalRequest $appraisalRequest): array
    {
        return $this->appraisalRequestPresenter()->revisionBatch($batch, $appraisalRequest);
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function buildAssetEditorProps(
        Request $request,
        AppraisalRequest $appraisalRequest,
        ?AppraisalAsset $asset = null
    ): array {
        return $this->appraisalAssetFormBuilder()->buildEditorProps($request, $appraisalRequest, $asset);
    }

    private function assetPayload(array $validated): array
    {
        return $this->appraisalAssetFormBuilder()->assetPayload($validated);
    }

    private function ensureAssetBelongsToRequest(AppraisalRequest $appraisalRequest, AppraisalAsset $asset): void
    {
        $this->appraisalAssetFormBuilder()->ensureBelongsToRequest($appraisalRequest, $asset);
    }

    private function assetFileDirectory(string $type): string
    {
        return $this->appraisalAssetFormBuilder()->assetFileDirectory($type);
    }

    private function buildAvailableActions(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): array {
        return $this->appraisalRequestActionResolver()->buildAvailableActions($appraisalRequest, $workflowService);
    }

    private function buildOfferAction(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        return $this->appraisalRequestActionResolver()->buildOfferAction($appraisalRequest, $workflowService);
    }

    private function buildApproveLatestNegotiationAction(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        return $this->appraisalRequestActionResolver()->buildApproveLatestNegotiationAction($appraisalRequest, $workflowService);
    }

    private function buildPaymentVerification(
        AppraisalRequest $appraisalRequest,
        AppraisalRequestWorkflowService $workflowService
    ): ?array {
        return $this->appraisalRequestActionResolver()->buildPaymentVerification($appraisalRequest, $workflowService);
    }

    private function negotiationActionOptions(AppraisalRequest $appraisalRequest): array
    {
        return $this->appraisalRequestPresenter()->negotiationActionOptions($appraisalRequest);
    }

    private function negotiationSummary(AppraisalRequest $appraisalRequest): array
    {
        return $this->appraisalRequestPresenter()->negotiationSummary($appraisalRequest);
    }

    private function appraisalRequestPresenter(): AppraisalRequestAdminPresenter
    {
        return app(AppraisalRequestAdminPresenter::class);
    }

    private function appraisalAssetFormBuilder(): AppraisalAssetFormBuilder
    {
        return app(AppraisalAssetFormBuilder::class);
    }

    private function appraisalRequestActionResolver(): AppraisalRequestActionResolver
    {
        return app(AppraisalRequestActionResolver::class);
    }
}
