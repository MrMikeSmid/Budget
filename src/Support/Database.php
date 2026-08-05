<?php

namespace App\Support;

use PDO;
use RuntimeException;

/**
 * PDO-verbinding naar de database van het ACTIEVE huishouden. Elk huishouden
 * heeft zijn eigen SQLite-bestand (zie storage/households/{id}/database.sqlite);
 * index.php roept useHouseholdDb() één keer aan per request, zodra bekend is
 * welk huishouden de ingelogde gebruiker actief heeft, vóórdat enig model
 * hiervan leest. Voor globale data (users/households/invites) zie AppDatabase.
 */
final class Database
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    private static ?string $activePath = null;

    public static function useHouseholdDb(string $absolutePath): void
    {
        self::$activePath = $absolutePath;
    }

    public static function connection(): PDO
    {
        if (self::$activePath === null) {
            throw new RuntimeException(
                'Geen actief huishouden ingesteld — Database::useHouseholdDb() moet vóór elke query aangeroepen zijn.'
            );
        }

        if (!isset(self::$connections[self::$activePath])) {
            $migrationsDir = __DIR__ . '/../../database/migrations';
            self::$connections[self::$activePath] = SqliteConnection::open(self::$activePath, $migrationsDir);
        }

        return self::$connections[self::$activePath];
    }
}
