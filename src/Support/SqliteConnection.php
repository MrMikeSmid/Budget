<?php

namespace App\Support;

use PDO;

/**
 * Gedeelde open+migreer-logica voor elk SQLite-bestand in de app: de
 * huishouden-databases (via Database) en de centrale app-database (via
 * AppDatabase) werken allebei zo, alleen het pad en de migratiemap verschillen.
 */
final class SqliteConnection
{
    public static function open(string $dbPath, string $migrationsDir): PDO
    {
        $storageDir = dirname($dbPath);
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new \RuntimeException("Kon map niet aanmaken: {$storageDir}");
        }

        // Zie Database::connect() van vroeger: storage/ wordt nooit door een
        // deploy overschreven, dus de .htaccess die directe download blokkeert
        // moet hier zelf neergezet worden, ook bij een verse installatie.
        $htaccess = $storageDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        // WAL + busy_timeout: meerdere leden van hetzelfde huishouden kunnen
        // tegelijk schrijven zonder meteen een SQLITE_BUSY-fout te krijgen.
        // Puur een optimalisatie — als de omgeving (bijv. een netwerkschijf op
        // sommige hosting) dit niet toestaat, mag dat de app niet blokkeren.
        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (\Throwable $e) {
            error_log('SqliteConnection: kon WAL/busy_timeout niet instellen, ga door zonder: ' . $e->getMessage());
        }

        self::migrate($pdo, $migrationsDir);

        return $pdo;
    }

    private static function migrate(PDO $pdo, string $migrationsDir): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL UNIQUE,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
            $stmt->execute(['filename' => $filename]);
        }
    }
}
