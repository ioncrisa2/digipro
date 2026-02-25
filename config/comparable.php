<?php

return [
    'base_url' => env('COMPARABLE_API_BASE_URL', 'https://pd.kjpp-hjar.co.id/api'),

    'credentials' => [
        'email' => env('COMPARABLE_API_EMAIL'),
        'password' => env('COMPARABLE_API_PASSWORD'),
        'device_name' => env('COMPARABLE_API_DEVICE_NAME', 'digipro-service'),
    ],

    'provider_key' => 'pd_kjpp_hjar',

    'default_limit' => 100,
    'default_range_km' => 10,
];
