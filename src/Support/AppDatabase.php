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
            $pdo = SqliteConnection::open($dbPath, $migrationsDir);
            // Vangnet: op productie bleek de migratie ooit als "toegepast"
            // geregistreerd te zijn terwijl de users-tabel deze kolom miste
            // (vermoedelijk een race vlak na de allereerste deploy van dit
            // bestand). Self-healing i.p.v. blind op de migratie-tracking
            // vertrouwen, zonder werkende installaties te breken.
            SqliteConnection::ensureColumn($pdo, 'users', 'email_verified_at', 'TEXT');
            self::$connection = $pdo;
        }

        return self::$connection;
    }
}
