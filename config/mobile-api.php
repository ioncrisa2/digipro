<?php

return [
    'token_expiration_minutes' => max(1, (int) env('MOBILE_API_TOKEN_EXPIRATION_MINUTES', 43_200)),

    'email_verification_expiration_minutes' => max(1, (int) env('MOBILE_API_EMAIL_VERIFICATION_EXPIRATION_MINUTES', 60)),

    'two_factor' => [
        'challenge_ttl_minutes' => max(1, (int) env('MOBILE_API_TWO_FACTOR_CHALLENGE_TTL_MINUTES', 5)),
        'max_attempts' => max(1, (int) env('MOBILE_API_TWO_FACTOR_MAX_ATTEMPTS', 5)),
    ],
];
