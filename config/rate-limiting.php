<?php

return [
    'register' => [
        'per_ip_per_minute' => 10,
        'per_email_per_minute' => 3,
    ],

    'forgot_password' => [
        'per_ip_per_minute' => 10,
        'per_email_per_minute' => 2,
    ],

    'public_availability' => [
        'per_session_per_minute' => 120,
        'per_ip_per_minute' => 1200,
    ],

    'public_availability_max_days' => 60,
];
