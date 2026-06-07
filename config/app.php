<?php

declare(strict_types=1);

return [
    'name' => 'Samen',
    'timezone' => 'Europe/Amsterdam',
    'database' => getenv('SAMEN_DATABASE') ?: dirname(__DIR__) . '/storage/app.sqlite',
    'app_url' => rtrim((string) (getenv('SAMEN_APP_URL') ?: ''), '/'),
    'mail_from' => getenv('SAMEN_MAIL_FROM') ?: 'noreply@localhost',
    'session_name' => 'samen_session',
];
