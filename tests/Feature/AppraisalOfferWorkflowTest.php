<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('accepts an active offer and moves the request to waiting signature', function () {
    $user = User::factory()->create();
    $record = createCustomerOfferRequest($user);

    $this->actingAs($user)
        ->post(route('appraisal.offer.accept', ['id' => $record->id]))
        ->assertRedirect(route('appraisal.contract.page', ['id' => $record->id]));

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::WaitingSignature)
        ->and($record->contract_status)->toBe(ContractStatusEnum::WaitingSignature)
        ->and($record->offerNegotiations()->latest('id')->first()?->action)->toBe('accept_offer');
});

it('submits a customer counter offer and moves the request back to waiting offer', function () {
    $user = User::factory()->create();
    $record = createCustomerOfferRequest($user);

    $this->actingAs($user)
        ->post(route('appraisal.offer.negotiate', ['id' => $record->id]), [
            'reason' => 'Mohon penyesuaian fee.',
            'expected_fee' => 1500000,
        ])
        ->assertRedirect(route('appraisal.offer.page', ['id' => $record->id]));

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::WaitingOffer)
        ->and($record->contract_status)->toBe(ContractStatusEnum::Negotiation)
        ->and($latestNegotiation?->action)->toBe('counter_request')
        ->and($latestNegotiation?->round)->toBe(1)
        ->and($latestNegotiation?->expected_fee)->toBe(1500000);
});

it('allows selecting a final offer after three negotiation rounds', function () {
    $user = User::factory()->create();
    $record = createCustomerOfferRequest($user, feeTotal: 1800000);

    foreach ([1750000, 1700000, 1650000] as $round => $offeredFee) {
        $record->offerNegotiations()->create([
            'user_id' => $user->id,
            'action' => 'counter_request',
            'round' => $round + 1,
            'offered_fee' => $offeredFee,
            'expected_fee' => 1500000 - ($round * 50000),
            'reason' => 'Negosiasi ' . ($round + 1),
        ]);
    }

    $this->actingAs($user)
        ->post(route('appraisal.offer.select', ['id' => $record->id]), [
            'selected_fee' => 1700000,
            'reason' => 'Saya pilih opsi tengah.',
        ])
        ->assertRedirect(route('appraisal.contract.page', ['id' => $record->id]));

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->fee_total)->toBe(1700000)
        ->and($record->status)->toBe(AppraisalStatusEnum::WaitingSignature)
        ->and($record->contract_status)->toBe(ContractStatusEnum::WaitingSignature)
        ->and($latestNegotiation?->action)->toBe('accept_offer')
        ->and($latestNegotiation?->selected_fee)->toBe(1700000);
});

function createCustomerOfferRequest(User $user, int $feeTotal = 1800000): AppraisalRequest
{
    return AppraisalRequest::create([
        'user_id' => $user->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::OfferSent,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00011/AGR/DP/04/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::SentToClient,
        'fee_total' => $feeTotal,
        'report_format' => 'digital',
    ]);
}
