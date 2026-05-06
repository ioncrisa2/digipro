<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterPeruriSignerUserRequest;
use App\Http\Requests\Admin\ReportSignerIndexRequest;
use App\Http\Requests\Admin\SetPeruriSignerSpecimenRequest;
use App\Http\Requests\Admin\StoreReportSignerRequest;
use App\Http\Requests\Admin\SubmitPeruriSignerKycRequest;
use App\Models\ReportSigner;
use App\Services\Admin\AdminReportSignerWorkspaceService;
use App\Services\Peruri\PeruriSignerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ReportSignerController extends Controller
{
    public function __construct(
        private readonly AdminReportSignerWorkspaceService $workspaceService,
        private readonly PeruriSignerOnboardingService $onboardingService,
    ) {
    }

    public function index(ReportSignerIndexRequest $request): Response
    {
        return inertia('Admin/ReportSigners/Index', $this->workspaceService
            ->indexPayload($request->filters(), $request->perPage()));
    }

    public function create(): Response
    {
        return inertia('Admin/ReportSigners/Form', $this->workspaceService->createPayload());
    }

    public function store(StoreReportSignerRequest $request): RedirectResponse
    {
        $signer = $this->workspaceService->saveReportSigner($request->validated());

        return redirect()
            ->route('admin.master-data.report-signers.edit', $signer)
            ->with('success', 'Profil penandatangan report berhasil ditambahkan.');
    }

    public function edit(ReportSigner $reportSigner): Response
    {
        return inertia('Admin/ReportSigners/Form', $this->workspaceService->editPayload($reportSigner));
    }

    public function update(StoreReportSignerRequest $request, ReportSigner $reportSigner): RedirectResponse
    {
        $this->workspaceService->saveReportSigner($request->validated(), $reportSigner);

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Profil penandatangan report berhasil diperbarui.');
    }

    public function destroy(ReportSigner $reportSigner): RedirectResponse
    {
        $this->workspaceService->deleteReportSigner($reportSigner);

        return redirect()
            ->route('admin.master-data.report-signers.index')
            ->with('success', 'Profil penandatangan report berhasil dihapus.');
    }

    public function refreshReadiness(ReportSigner $reportSigner): RedirectResponse
    {
        $readiness = $this->workspaceService->refreshReadiness($reportSigner);

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Kesiapan Peruri diperbarui: ' . data_get($readiness, 'overall.message', 'status terbaru tersimpan.'));
    }

    public function registerPeruriUser(RegisterPeruriSignerUserRequest $request, ReportSigner $reportSigner): RedirectResponse
    {
        $this->onboardingService->registerUser($reportSigner, $request->payload());

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Registrasi user Peruri berhasil dikirim.');
    }

    public function submitPeruriKyc(SubmitPeruriSignerKycRequest $request, ReportSigner $reportSigner): RedirectResponse
    {
        $this->onboardingService->submitKycVideo(
            $reportSigner,
            $request->file('kyc_video'),
            $request->payload(),
        );

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Video E-KYC berhasil dikirim ke Peruri.');
    }

    public function setPeruriSpecimen(SetPeruriSignerSpecimenRequest $request, ReportSigner $reportSigner): RedirectResponse
    {
        $this->onboardingService->setSignatureSpecimen(
            $reportSigner,
            $request->file('signature_image'),
            $request->payload(),
        );

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Specimen tanda tangan berhasil dikirim ke Peruri.');
    }

    public function registerPeruriKeyla(ReportSigner $reportSigner): RedirectResponse
    {
        $response = $this->onboardingService->registerKeyla($reportSigner);

        return redirect()
            ->route('admin.master-data.report-signers.edit', $reportSigner)
            ->with('success', 'Registrasi KEYLA berhasil diproses.')
            ->with('peruri_onboarding', [
                'action' => 'register_keyla',
                'email' => $reportSigner->email,
                'qr_image' => data_get($response, 'data.qrImage'),
            ]);
    }
}
