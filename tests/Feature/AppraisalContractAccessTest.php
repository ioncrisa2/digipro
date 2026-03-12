<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('allows opening the contract page after payment moves request into valuation in progress', function () {
    $user = User::factory()->create();
    $request = createSignedAppraisalRequest($user, AppraisalStatusEnum::ValuationOnProgress);

    $this->actingAs($user)
        ->get(route('appraisal.contract.page', ['id' => $request->id]))
        ->assertOk();
});

it('allows downloading the contract pdf after payment moves request into valuation in progress', function () {
    $user = User::factory()->create();
    $request = createSignedAppraisalRequest($user, AppraisalStatusEnum::ValuationOnProgress);

    $response = $this->actingAs($user)
        ->get(route('appraisal.contract.pdf', ['id' => $request->id]));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
});

function createSignedAppraisalRequest(User $user, AppraisalStatusEnum $status): AppraisalRequest
{
    return AppraisalRequest::create([
        'user_id' => $user->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => $status,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00015/AGR/DP/03/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'fee_total' => 1400000,
        'report_format' => 'digital',
    ]);
}
