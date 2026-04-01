<?php

namespace App\Http\Controllers;

use App\Services\AppraisalService;
use Illuminate\Http\Request;

/**
 * Renders the customer documents hub for appraisal requests.
 */
class ReportController extends Controller
{
    public function index(Request $request, AppraisalService $appraisalService)
    {
        return inertia('Reports/Index', $appraisalService->buildDocumentsIndexPayload($request->user()->id));
    }

    public function show(Request $request, int $id, AppraisalService $appraisalService)
    {
        return inertia('Reports/Show', $appraisalService->buildDocumentsShowPayload($request->user()->id, $id));
    }
}
