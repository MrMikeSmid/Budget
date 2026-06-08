<?php

declare(strict_types=1);

return [
    'name' => 'Samen',
    'timezone' => 'Europe/Amsterdam',
    'database' => getenv('SAMEN_DATABASE') ?: dirname(__DIR__) . '/storage/app.sqlite',
    'app_url' => rtrim((string) (getenv('SAMEN_APP_URL') ?: ''), '/'),
    'mail_from' => getenv('SAMEN_MAIL_FROM') ?: 'noreply@localhost',
    'firebase_project_id' => trim((string) (getenv('SAMEN_FIREBASE_PROJECT_ID') ?: '')),
    'firebase_api_key' => trim((string) (getenv('SAMEN_FIREBASE_API_KEY') ?: '')),
    'firebase_messaging_sender_id' => trim((string) (getenv('SAMEN_FIREBASE_MESSAGING_SENDER_ID') ?: '')),
    'firebase_app_id' => trim((string) (getenv('SAMEN_FIREBASE_APP_ID') ?: '')),
    'firebase_vapid_public_key' => trim((string) (getenv('SAMEN_FIREBASE_VAPID_PUBLIC_KEY') ?: '')),
    'firebase_service_account_json' => trim((string) (getenv('SAMEN_FIREBASE_SERVICE_ACCOUNT_JSON') ?: '')),
    'admin_email' => mb_strtolower(trim((string) (getenv('SAMEN_ADMIN_EMAIL') ?: ''))),
    'session_name' => 'samen_session',
];
