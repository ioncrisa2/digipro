<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\SignBulkPublicAppraiserContractsRequest;
use App\Http\Requests\Reviewer\SignPublicAppraiserContractRequest;
use App\Models\AppraisalRequest;
use App\Services\Reviewer\PublicAppraiserContractWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class PublicAppraiserContractController extends Controller
{
    public function __construct(
        private readonly PublicAppraiserContractWorkspaceService $workspaceService,
    ) {
    }

    public function index(): Response
    {
        abort_unless($this->workspaceService->hasAssignedSigner($this->user()), 403);

        return inertia('Reviewer/ContractSignatures/Index', $this->workspaceService->indexPayload(
            $this->user(),
        ));
    }

    public function show(AppraisalRequest $appraisalRequest): Response
    {
        abort_unless($this->workspaceService->hasAssignedSigner($this->user()), 403);

        return inertia('Reviewer/ContractSignatures/Show', $this->workspaceService->showPayload(
            $this->user(),
            $appraisalRequest,
        ));
    }

    public function sign(SignPublicAppraiserContractRequest $request, AppraisalRequest $appraisalRequest): RedirectResponse
    {
        abort_unless($this->workspaceService->hasAssignedSigner($request->user()), 403);

        try {
            $this->workspaceService->signContract(
                $request->user(),
                $appraisalRequest,
                (string) $request->validated('keyla_token'),
            );

            return redirect()
                ->route('reviewer.contract-signatures.show', $appraisalRequest)
                ->with('success', 'Kontrak berhasil ditandatangani dan PDF final tersimpan.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Gagal memproses tanda tangan kontrak. Silakan coba lagi.');
        }
    }

    public function bulkSign(SignBulkPublicAppraiserContractsRequest $request): RedirectResponse
    {
        abort_unless($this->workspaceService->hasAssignedSigner($request->user()), 403);

        try {
            $result = $this->workspaceService->bulkSignContracts(
                $request->user(),
                $request->appraisalRequestIds(),
                (string) $request->validated('keyla_token'),
            );

            return redirect()
                ->route('reviewer.contract-signatures.index')
                ->with('success', sprintf(
                    'Bulk sign selesai. %d berhasil, %d gagal.',
                    (int) ($result['success_count'] ?? 0),
                    (int) ($result['failed_count'] ?? 0),
                ))
                ->with('bulk_sign_result', $result);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Gagal memproses bulk sign kontrak. Silakan coba lagi.');
        }
    }

    private function user(): \App\Models\User
    {
        /** @var \App\Models\User $user */
        $user = request()->user();

        return $user;
    }
}
