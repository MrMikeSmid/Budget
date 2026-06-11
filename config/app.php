<?php

declare(strict_types=1);

return [
    'name' => 'Samen',
    'timezone' => 'Europe/Amsterdam',
    'database' => getenv('SAMEN_DATABASE') ?: dirname(__DIR__) . '/storage/app.sqlite',
    'app_url' => rtrim((string) (getenv('SAMEN_APP_URL') ?: ''), '/'),
    'mail_from' => getenv('SAMEN_MAIL_FROM') ?: 'noreply@localhost',
    'smtp_host' => trim((string) (getenv('SAMEN_SMTP_HOST') ?: '')),
    'smtp_port' => trim((string) (getenv('SAMEN_SMTP_PORT') ?: '587')),
    'smtp_encryption' => trim((string) (getenv('SAMEN_SMTP_ENCRYPTION') ?: 'starttls')),
    'smtp_username' => trim((string) (getenv('SAMEN_SMTP_USERNAME') ?: '')),
    'smtp_password' => (string) (getenv('SAMEN_SMTP_PASSWORD') ?: ''),
    'smtp_timeout' => trim((string) (getenv('SAMEN_SMTP_TIMEOUT') ?: '15')),
    'onesignal_app_id' => trim((string) (getenv('SAMEN_ONESIGNAL_APP_ID') ?: '')),
    'onesignal_rest_api_key' => trim((string) (getenv('SAMEN_ONESIGNAL_REST_API_KEY') ?: '')),
    'admin_email' => mb_strtolower(trim((string) (getenv('SAMEN_ADMIN_EMAIL') ?: ''))),
    'session_name' => 'samen_session',
    'session_lifetime' => 48 * 60 * 60,
    'session_warning_time' => 2 * 60 * 60,
];
