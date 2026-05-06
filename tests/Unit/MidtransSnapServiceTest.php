<?php

use App\Services\Payments\MidtransSnapService;
use Tests\TestCase;

uses(TestCase::class);

it('normalizes minimarket aliases into supported snap payment channels', function () {
    config()->set('payment.midtrans.enabled_payments', [
        'bca_va',
        'cstore',
        'credit_card',
        'minimarket',
        'alfamart',
    ]);

    $service = app(MidtransSnapService::class);

    expect($service->enabledPayments())->toBe([
        'bca_va',
        'indomaret',
        'alfamart',
        'credit_card',
    ]);
});

it('maps minimarket gateway details from midtrans metadata', function () {
    $service = app(MidtransSnapService::class);

    $details = $service->gatewayDetailsFromMetadata([
        'notification' => [
            'payment_type' => 'cstore',
            'store' => 'indomaret',
            'payment_code' => 'INV-INDO-9988',
            'transaction_status' => 'pending',
            'expiry_time' => '2026-04-19 10:00:00',
        ],
    ]);

    expect($details['label'])->toBe('INDOMARET')
        ->and($details['reference'])->toBe('INV-INDO-9988')
        ->and($details['payment_type'])->toBe('cstore');
});

it('maps credit card gateway details from midtrans metadata', function () {
    $service = app(MidtransSnapService::class);

    $details = $service->gatewayDetailsFromMetadata([
        'notification' => [
            'payment_type' => 'credit_card',
            'bank' => 'bni',
            'masked_card' => '481111-1114',
            'approval_code' => 'APPROVED123',
            'card_type' => 'credit',
            'transaction_status' => 'settlement',
        ],
    ]);

    expect($details['label'])->toBe('Kartu Kredit')
        ->and($details['reference'])->toBe('481111-1114')
        ->and($details['bank'])->toBe('BNI')
        ->and($details['card_type'])->toBe('credit');
});
