<?php

require_once __DIR__ . '/env.php';

return [
    'host'       => env('MAIL_HOST', 'smtp.gmail.com'),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME'),
    'password'   => env('MAIL_PASSWORD'),
    'from_email' => env('MAIL_FROM_EMAIL', env('MAIL_USERNAME')),
    'from_name'  => env('MAIL_FROM_NAME', 'Sistema Electronicas'),
];
