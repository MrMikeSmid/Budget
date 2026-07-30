<?php

namespace App\Support;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect();
        }

        return self::$connection;
    }

    private static function connect(): PDO
    {
        $config = Config::get();
        $dbPath = $config['db_path'];
        $isNew = !file_exists($dbPath);

        $storageDir = dirname($dbPath);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        // storage/ wordt door het deploy-script nooit overschreven (zo overleeft de
        // database elke deploy), dus storage/.htaccess komt daar ook nooit via git
        // terecht. Zet 'm hier zelf neer zodat de sqlite-file nooit direct
        // downloadbaar is via de browser, ook niet bij een verse installatie.
        $htaccess = $storageDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL UNIQUE,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $migrationsDir = __DIR__ . '/../../database/migrations';
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
