<?php

declare(strict_types=1);

return [
    'name' => 'Regie',
    'timezone' => 'Europe/Amsterdam',
    'database' => getenv('REGIE_DATABASE') ?: dirname(__DIR__) . '/storage/app.sqlite',
    'app_url' => rtrim((string) (getenv('REGIE_APP_URL') ?: ''), '/'),
    'session_name' => 'regie_session',
    'session_lifetime' => 30 * 24 * 60 * 60,
    'session_warning_time' => 2 * 60 * 60,
];
