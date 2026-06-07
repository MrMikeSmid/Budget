<?php

declare(strict_types=1);

return [
    'name' => 'Samen',
    'timezone' => 'Europe/Amsterdam',
    'database' => getenv('SAMEN_DATABASE') ?: dirname(__DIR__) . '/storage/app.sqlite',
    'session_name' => 'samen_session',
];
