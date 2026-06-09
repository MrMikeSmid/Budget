<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                name TEXT NOT NULL,
                password_hash TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS todo_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                emoji TEXT NOT NULL DEFAULT '✨',
                color TEXT NOT NULL DEFAULT 'violet',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS list_members (
                list_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                invited_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (list_id, user_id),
                FOREIGN KEY (list_id) REFERENCES todo_lists(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS todo_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                completed_by INTEGER,
                title TEXT NOT NULL,
                image_filename TEXT,
                priority TEXT NOT NULL DEFAULT 'none',
                due_date TEXT,
                is_completed INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at TEXT,
                FOREIGN KEY (list_id) REFERENCES todo_lists(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS todo_item_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES todo_items(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE INDEX IF NOT EXISTS idx_items_list ON todo_items(list_id);
            CREATE INDEX IF NOT EXISTS idx_item_comments_item ON todo_item_comments(item_id);
            CREATE INDEX IF NOT EXISTS idx_members_user ON list_members(user_id);

            CREATE TABLE IF NOT EXISTS app_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );


            CREATE TABLE IF NOT EXISTS notification_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                subscription_id TEXT NOT NULL UNIQUE,
                user_agent TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE INDEX IF NOT EXISTS idx_notification_subscriptions_user ON notification_subscriptions(user_id);
        SQL);

        // Provider migration: old device identifiers and credentials cannot be reused by OneSignal.
        $this->pdo->exec('DROP TABLE IF EXISTS push_subscriptions');
        $legacyProviderPrefix = 'be' . 'ams_';
        $stmt = $this->pdo->prepare('DELETE FROM app_settings WHERE key IN (?, ?)');
        $stmt->execute([$legacyProviderPrefix . 'instance_id', $legacyProviderPrefix . 'secret_key']);

        $columns = $this->pdo->query('PRAGMA table_info(users)')->fetchAll();
        $columnNames = array_column($columns, 'name');
        if (!in_array('profile_image', $columnNames, true)) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN profile_image TEXT');
        }
        if (!in_array('last_seen_at', $columnNames, true)) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN last_seen_at TEXT');
        }
        if (!in_array('last_login_at', $columnNames, true)) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN last_login_at TEXT');
        }
        if (!in_array('is_admin', $columnNames, true)) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
        }

        $itemColumns = $this->pdo->query('PRAGMA table_info(todo_items)')->fetchAll();
        $itemColumnNames = array_column($itemColumns, 'name');
        if (!in_array('image_filename', $itemColumnNames, true)) {
            $this->pdo->exec('ALTER TABLE todo_items ADD COLUMN image_filename TEXT');
        }
        if (!in_array('priority', $itemColumnNames, true)) {
            $this->pdo->exec("ALTER TABLE todo_items ADD COLUMN priority TEXT NOT NULL DEFAULT 'none'");
        }
        if (!in_array('due_date', $itemColumnNames, true)) {
            $this->pdo->exec('ALTER TABLE todo_items ADD COLUMN due_date TEXT');
        }

        $legacyPushColumn = 'push_' . 'external_id';
        if (in_array($legacyPushColumn, $columnNames, true)) {
            $this->pdo->exec('DROP INDEX IF EXISTS idx_users_' . $legacyPushColumn);
            try {
                $this->pdo->exec('ALTER TABLE users DROP COLUMN ' . $legacyPushColumn);
            } catch (\PDOException) {
                // Older SQLite versions keep the unused legacy column; no application code reads it.
            }
        }
        $this->promoteInitialAdmin();
    }

    private function promoteInitialAdmin(): void
    {
        $adminEmail = mb_strtolower(trim((string) (getenv('SAMEN_ADMIN_EMAIL') ?: '')));
        if ($adminEmail !== '') {
            $stmt = $this->pdo->prepare('UPDATE users SET is_admin = 1 WHERE email = ? COLLATE NOCASE');
            $stmt->execute([$adminEmail]);
        }

        $hasAdmin = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn() > 0;
        if (!$hasAdmin) {
            $this->pdo->exec('UPDATE users SET is_admin = 1 WHERE id = (SELECT id FROM users ORDER BY id ASC LIMIT 1)');
        }
    }
}
