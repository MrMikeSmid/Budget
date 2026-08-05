<?php

namespace App\Support;

use PDO;

/**
 * PDO-verbinding naar de centrale, applicatiebrede database: globale
 * gebruikers, huishoudens, lidmaatschappen, e-mailverificaties en
 * uitnodigingen. Los van de per-huishouden databases (zie Database).
 */
final class AppDatabase
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $dbPath = Config::get()['app_db_path'];
            $migrationsDir = __DIR__ . '/../../database/app_migrations';
            self::$connection = SqliteConnection::open($dbPath, $migrationsDir);
        }

        return self::$connection;
    }
}
