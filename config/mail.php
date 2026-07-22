<?php

require_once __DIR__ . '/env.php';

return [
    'driver'     => env('MAIL_DRIVER', 'smtp'),
    'host'       => env('MAIL_HOST', 'smtp.gmail.com'),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME'),
    'password'   => env('MAIL_PASSWORD'),
    'from_email' => env('MAIL_FROM_EMAIL', env('MAIL_USERNAME')),
    'from_name'  => env('MAIL_FROM_NAME', 'Sistema Electronicas'),
    'timeout'    => (int) env('MAIL_TIMEOUT', 20),
    'sendgrid'   => [
        'api_key'    => env('SENDGRID_API_KEY'),
        'from_email' => env('SENDGRID_FROM_EMAIL', env('MAIL_FROM_EMAIL', env('MAIL_USERNAME'))),
        'from_name'  => env('SENDGRID_FROM_NAME', env('MAIL_FROM_NAME', 'Sistema Electronicas')),
    ],
];
