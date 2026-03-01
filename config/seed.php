<?php

return [
    'admin' => [
        'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SEED_ADMIN_PASSWORD', 'password'),
        'first_name' => env('SEED_ADMIN_FIRST_NAME', 'Admin'),
        'last_name' => env('SEED_ADMIN_LAST_NAME', 'User'),
    ],
];
