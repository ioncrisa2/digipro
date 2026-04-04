<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerAccessRequest;
use App\Services\Customer\AppraisalService;

/**
 * Renders the customer documents hub for appraisal requests.
 */
class ReportController extends Controller
{
    public function index(CustomerAccessRequest $request, AppraisalService $appraisalService)
    {
        return inertia('Reports/Index', $appraisalService->buildDocumentsIndexPayload($request->user()->id));
    }

    public function show(CustomerAccessRequest $request, int $id, AppraisalService $appraisalService)
    {
        return inertia('Reports/Show', $appraisalService->buildDocumentsShowPayload($request->user()->id, $id));
    }
}
