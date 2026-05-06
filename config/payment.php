<?php

return [
    'midtrans' => [
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOL),
        'session_expiry_hours' => (int) env('MIDTRANS_SESSION_EXPIRY_HOURS', 24),
        'enabled_payments' => array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', (string) env(
                'MIDTRANS_ENABLED_PAYMENTS',
                'bca_va,bni_va,bri_va,permata_va,echannel,gopay,shopeepay,qris,indomaret,alfamart,credit_card'
            ))
        ))),
    ],
];
