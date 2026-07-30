<?php

namespace App\Support;

final class Config
{
    private static ?array $values = null;

    public static function get(): array
    {
        if (self::$values === null) {
            $defaults = [
                'db_path' => __DIR__ . '/../../storage/database.sqlite',
                'session_name' => 'budgetapp_session',
                'debug' => false,
            ];

            $configFile = __DIR__ . '/../../config/config.php';
            $overrides = file_exists($configFile) ? require $configFile : [];

            self::$values = array_merge($defaults, $overrides);
        }

        return self::$values;
    }
}
