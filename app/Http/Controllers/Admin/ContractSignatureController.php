<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SignPeruriContractRequest;
use App\Models\AppraisalRequest;
use App\Services\Admin\AdminContractSignatureWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ContractSignatureController extends Controller
{
    public function __construct(
        private readonly AdminContractSignatureWorkspaceService $workspaceService,
    ) {
    }

    public function index(): Response
    {
        return inertia('Admin/Signatures/PendingContracts', $this->workspaceService->indexPayload());
    }

    public function sign(SignPeruriContractRequest $request, AppraisalRequest $appraisalRequest): RedirectResponse
    {
        try {
            $this->workspaceService->signContract(
                $appraisalRequest,
                (int) $request->user()->id,
                (string) $request->validated('keyla_token'),
            );

            return back()->with('success', 'Kontrak berhasil ditandatangani dan PDF final tersimpan.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal memproses tanda tangan kontrak. Silakan coba lagi.');
        }
    }
}

