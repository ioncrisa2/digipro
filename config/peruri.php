<?php

return [
    'base_url' => env('PERURI_BASE_URL', ''),
    'api_version' => env('PERURI_API_VERSION', 'v1'),
    'corporate_id' => env('PERURI_CORPORATE_ID', ''),
    'client_id' => env('PERURI_CLIENT_ID', ''),
    'client_secret' => env('PERURI_CLIENT_SECRET', ''),
    'uploader_email' => env('PERURI_UPLOADER_EMAIL', ''),

    /**
     * Optional extra headers (e.g. PDS-KEY) if Peruri requires it.
     *
     * Example:
     * PERURI_EXTRA_HEADERS='{"PDS-KEY":"..."}'
     */
    'extra_headers' => json_decode((string) env('PERURI_EXTRA_HEADERS', '{}'), true) ?: [],

    /**
     * Coordinates for visible signatures.
     *
     * Values are in PDF points/pixels as expected by SIGN-IT.
     * Calibrate on sandbox then update these numbers.
     */
    'coordinates' => [
        'contract' => [
            'customer' => [
                'page' => (int) env('PERURI_COORDINATES_CONTRACT_CUSTOMER_PAGE', 1),
                'lower_left_x' => (int) env('PERURI_COORDINATES_CONTRACT_CUSTOMER_LLX', 360),
                'lower_left_y' => (int) env('PERURI_COORDINATES_CONTRACT_CUSTOMER_LLY', 120),
                'upper_right_x' => (int) env('PERURI_COORDINATES_CONTRACT_CUSTOMER_URX', 540),
                'upper_right_y' => (int) env('PERURI_COORDINATES_CONTRACT_CUSTOMER_URY', 200),
            ],
            'public_appraiser' => [
                'page' => (int) env('PERURI_COORDINATES_CONTRACT_PUBLIC_APPRAISER_PAGE', 1),
                'lower_left_x' => (int) env('PERURI_COORDINATES_CONTRACT_PUBLIC_APPRAISER_LLX', 40),
                'lower_left_y' => (int) env('PERURI_COORDINATES_CONTRACT_PUBLIC_APPRAISER_LLY', 120),
                'upper_right_x' => (int) env('PERURI_COORDINATES_CONTRACT_PUBLIC_APPRAISER_URX', 220),
                'upper_right_y' => (int) env('PERURI_COORDINATES_CONTRACT_PUBLIC_APPRAISER_URY', 200),
            ],
        ],
    ],
];

