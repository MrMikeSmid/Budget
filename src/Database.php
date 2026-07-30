<?php

declare(strict_types=1);

final class Database
{
    public static function connect(): PDO
    {
        $directory = dirname(__DIR__) . '/var';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $directory . '/budget.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_date TEXT NOT NULL,
                description TEXT NOT NULL,
                category TEXT NOT NULL,
                type TEXT NOT NULL CHECK (type IN ('inkomst', 'uitgave')),
                amount_cents INTEGER NOT NULL CHECK (amount_cents >= 0),
                note TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date);
            CREATE TABLE IF NOT EXISTS budgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                month TEXT NOT NULL,
                category TEXT NOT NULL,
                amount_cents INTEGER NOT NULL CHECK (amount_cents >= 0),
                UNIQUE(month, category)
            );
        SQL);
    }
}
