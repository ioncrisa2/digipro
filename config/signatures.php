<?php

return [
    'contract_mode' => env('CONTRACT_SIGNATURE_MODE', 'canvas_demo'),

    'canvas_demo' => [
        'provider' => 'canvas_demo',
        'signature_disk' => env('DEMO_SIGNATURE_DISK', 'local'),
        'document_disk' => env('DEMO_SIGNATURE_DOCUMENT_DISK', 'public'),
    ],
];
