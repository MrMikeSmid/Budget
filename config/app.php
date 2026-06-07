<?php

declare(strict_types=1);

return [
    'name' => 'Samen',
    'timezone' => 'Europe/Amsterdam',
    'database' => getenv('SAMEN_DATABASE') ?: dirname(__DIR__) . '/storage/app.sqlite',
    'app_url' => rtrim((string) (getenv('SAMEN_APP_URL') ?: ''), '/'),
    'mail_from' => getenv('SAMEN_MAIL_FROM') ?: 'noreply@localhost',
    'onesignal_app_id' => trim((string) (getenv('SAMEN_ONESIGNAL_APP_ID') ?: '')),
    'onesignal_api_key' => trim((string) (getenv('SAMEN_ONESIGNAL_API_KEY') ?: '')),
    'admin_email' => mb_strtolower(trim((string) (getenv('SAMEN_ADMIN_EMAIL') ?: ''))),
    'session_name' => 'samen_session',
];
