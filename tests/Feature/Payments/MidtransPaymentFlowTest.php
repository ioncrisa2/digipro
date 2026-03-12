<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\AppraisalRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\MidtransSnapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payment.midtrans.merchant_id', 'G249303034');
    config()->set('payment.midtrans.client_key', 'SB-Mid-client-test');
    config()->set('payment.midtrans.server_key', 'SB-Mid-server-test');
    config()->set('payment.midtrans.is_production', false);
    config()->set('payment.midtrans.session_expiry_hours', 24);
    config()->set('payment.midtrans.enabled_payments', ['bca_va', 'gopay', 'shopeepay', 'qris']);
});

it('creates a midtrans session for a contract signed request', function () {
    $user = User::factory()->create();
    $request = createAppraisalRequestForPayment($user);

    $service = Mockery::mock(MidtransSnapService::class)->makePartial();
    $service->shouldReceive('createTransaction')
        ->once()
        ->andReturn([
            'order_id' => 'DIGIPRO-REQ-1-PAY-1-20260312120000',
            'snap_token' => 'snap-token-123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test',
            'expires_at' => Carbon::now()->addDay()->toIso8601String(),
            'payload' => ['enabled_payments' => ['bca_va', 'gopay']],
        ]);
    $this->app->instance(MidtransSnapService::class, $service);

    $response = $this
        ->actingAs($user)
        ->post(route('appraisal.payment.session', ['id' => $request->id]));

    $response
        ->assertOk()
        ->assertJsonPath('payment.status', 'pending')
        ->assertJsonPath('payment.external_payment_id', 'DIGIPRO-REQ-1-PAY-1-20260312120000')
        ->assertJsonPath('payment.checkout.snap_token', 'snap-token-123');

    $payment = Payment::query()->sole();
    expect($payment->method)->toBe('gateway');
    expect($payment->gateway)->toBe('midtrans');
    expect($payment->external_payment_id)->toBe('DIGIPRO-REQ-1-PAY-1-20260312120000');
});

it('reuses an active midtrans session instead of creating a new payment row', function () {
    $user = User::factory()->create();
    $request = createAppraisalRequestForPayment($user);

    $payment = Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-REUSE-1',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00001',
            'checkout' => [
                'snap_token' => 'snap-reuse-1',
                'redirect_url' => 'https://example.test/reuse',
                'expires_at' => Carbon::now()->addHours(12)->toIso8601String(),
                'created_at' => Carbon::now()->toIso8601String(),
            ],
            'gateway_details' => [
                'label' => 'Midtrans Snap',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('appraisal.payment.session', ['id' => $request->id]));

    $response
        ->assertOk()
        ->assertJsonPath('payment.id', $payment->id)
        ->assertJsonPath('payment.checkout.snap_token', 'snap-reuse-1');

    expect(Payment::query()->count())->toBe(1);
});

it('can replace an active midtrans session to switch payment method', function () {
    $user = User::factory()->create();
    $request = createAppraisalRequestForPayment($user);

    $existingPayment = Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-REPLACE-OLD',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00001',
            'checkout' => [
                'snap_token' => 'snap-old-token',
                'redirect_url' => 'https://example.test/old',
                'expires_at' => Carbon::now()->addHours(12)->toIso8601String(),
                'created_at' => Carbon::now()->toIso8601String(),
            ],
            'gateway_details' => [
                'label' => 'QRIS',
                'payment_type' => 'qris',
            ],
        ],
    ]);

    $service = Mockery::mock(MidtransSnapService::class)->makePartial();
    $service->shouldReceive('cancelTransaction')
        ->once()
        ->with('DIGIPRO-REPLACE-OLD')
        ->andReturn('200');
    $service->shouldReceive('createTransaction')
        ->once()
        ->andReturn([
            'order_id' => 'DIGIPRO-REPLACE-NEW',
            'snap_token' => 'snap-new-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/new',
            'expires_at' => Carbon::now()->addDay()->toIso8601String(),
            'payload' => ['enabled_payments' => ['bca_va', 'qris']],
        ]);
    $this->app->instance(MidtransSnapService::class, $service);

    $response = $this
        ->actingAs($user)
        ->post(route('appraisal.payment.session', ['id' => $request->id]), [
            'force_new_attempt' => true,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('payment.external_payment_id', 'DIGIPRO-REPLACE-NEW')
        ->assertJsonPath('payment.checkout.snap_token', 'snap-new-token');

    $existingPayment->refresh();
    $newPayment = Payment::query()
        ->where('id', '!=', $existingPayment->id)
        ->sole();

    expect($existingPayment->status)->toBe('failed');
    expect(data_get($existingPayment->metadata, 'replaced_reason'))->toBe('user_switch_payment_method');
    expect($newPayment->status)->toBe('pending');
    expect($newPayment->external_payment_id)->toBe('DIGIPRO-REPLACE-NEW');
});

it('rejects invalid midtrans webhook signatures', function () {
    $request = createAppraisalRequestForPayment(User::factory()->create());

    Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-NOTIF-INVALID',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00001',
        ],
    ]);

    $this->postJson(route('payments.midtrans.notification'), [
        'order_id' => 'DIGIPRO-NOTIF-INVALID',
        'status_code' => '200',
        'gross_amount' => '1800000.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'invalid',
    ])->assertForbidden();
});

it('marks payment paid from a valid webhook and unlocks invoice access', function () {
    $user = User::factory()->create();
    $request = createAppraisalRequestForPayment($user);

    $payment = Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-NOTIF-PAID',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00001',
            'checkout' => [
                'expires_at' => Carbon::now()->addHours(24)->toIso8601String(),
            ],
        ],
    ]);

    $grossAmount = '1800000.00';
    $signature = hash('sha512', 'DIGIPRO-NOTIF-PAID' . '200' . $grossAmount . config('payment.midtrans.server_key'));

    $this->postJson(route('payments.midtrans.notification'), [
        'order_id' => 'DIGIPRO-NOTIF-PAID',
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'trx-midtrans-001',
        'va_numbers' => [
            ['bank' => 'bca', 'va_number' => '1234567890'],
        ],
        'signature_key' => $signature,
    ])->assertOk();

    $payment->refresh();
    $request->refresh();

    expect($payment->status)->toBe('paid');
    expect($payment->paid_at)->not->toBeNull();
    expect($request->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);

    $this->actingAs($user)
        ->get(route('appraisal.invoice.page', ['id' => $request->id]))
        ->assertOk();
});

it('syncs a pending payment from midtrans status when reopening the payment page', function () {
    $user = User::factory()->create();
    $request = createAppraisalRequestForPayment($user);

    $payment = Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-SYNC-PAID',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00001',
            'checkout' => [
                'expires_at' => Carbon::now()->addHours(24)->toIso8601String(),
            ],
        ],
    ]);

    $service = Mockery::mock(MidtransSnapService::class)->makePartial();
    $service->shouldReceive('transactionStatus')
        ->once()
        ->with('DIGIPRO-SYNC-PAID')
        ->andReturn([
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-sync-001',
            'va_numbers' => [
                ['bank' => 'bni', 'va_number' => '98877665544'],
            ],
            'gross_amount' => '1800000.00',
            'status_code' => '200',
        ]);
    $this->app->instance(MidtransSnapService::class, $service);

    $this->actingAs($user)
        ->get(route('appraisal.payment.page', ['id' => $request->id]))
        ->assertRedirect(route('appraisal.invoice.page', ['id' => $request->id]));

    $payment->refresh();
    $request->refresh();

    expect($payment->status)->toBe('paid');
    expect($request->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);
});

it('keeps invoice gated while payment is still pending', function () {
    $user = User::factory()->create();
    $request = createAppraisalRequestForPayment($user);

    Payment::create([
        'appraisal_request_id' => $request->id,
        'amount' => 1800000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'DIGIPRO-INVOICE-PENDING',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-00001',
            'checkout' => [
                'expires_at' => Carbon::now()->addHours(24)->toIso8601String(),
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('appraisal.invoice.page', ['id' => $request->id]))
        ->assertRedirect(route('appraisal.payment.page', ['id' => $request->id]));
});

function createAppraisalRequestForPayment(User $user): AppraisalRequest
{
    return AppraisalRequest::create([
        'user_id' => $user->id,
        'request_number' => 'REQ-' . Str::upper(Str::random(8)),
        'purpose' => 'jual_beli',
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
        'client_name' => 'PT Test DigiPro',
        'contract_number' => '00001/AGR/DP/03/2026-' . Str::upper(Str::random(4)),
        'contract_date' => now()->toDateString(),
        'contract_status' => ContractStatusEnum::ContractSigned,
        'fee_total' => 1800000,
        'report_format' => 'digital',
    ]);
}
