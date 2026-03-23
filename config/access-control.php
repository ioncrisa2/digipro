<?php

return [
    'super_admin' => [
        'enabled' => env('ACCESS_CONTROL_SUPER_ADMIN_ENABLED', true),
        'name' => env('ACCESS_CONTROL_SUPER_ADMIN_ROLE', 'super_admin'),
    ],
];
