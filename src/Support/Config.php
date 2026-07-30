<?php

namespace App\Support;

final class Config
{
    private static ?array $values = null;

    public static function get(): array
    {
        if (self::$values === null) {
            $configFile = __DIR__ . '/../../config/config.php';

            if (!file_exists($configFile)) {
                http_response_code(500);
                echo 'Configuratiebestand ontbreekt. Kopieer config/config.example.php naar config/config.php en pas de waarden aan.';
                exit;
            }

            self::$values = require $configFile;
        }

        return self::$values;
    }
}
