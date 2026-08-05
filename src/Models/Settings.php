<?php

namespace App\Models;

use App\Support\AppDatabase;
use App\Support\Config;
use PDO;

/**
 * Applicatiebrede instellingen die via het admin-paneel ingesteld worden
 * (i.p.v. config/config.php op de server) — momenteel SMTP-gegevens en de
 * absolute basis-URL voor mails. Waardes in deze tabel hebben voorrang op
 * config.php, dat op zijn beurt weer de ingebouwde standaardwaarden dekt.
 */
final class Settings
{
    public static function all(): array
    {
        return AppDatabase::connection()
            ->query('SELECT key, value FROM settings')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function get(string $key): ?string
    {
        $stmt = AppDatabase::connection()->prepare('SELECT value FROM settings WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function set(string $key, ?string $value): void
    {
        $pdo = AppDatabase::connection();

        if ($value === null || $value === '') {
            $stmt = $pdo->prepare('DELETE FROM settings WHERE key = :key');
            $stmt->execute(['key' => $key]);
            return;
        }

        $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)');
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    public static function mailConfig(): array
    {
        $base = Config::get()['mail'];
        $stored = self::all();

        return [
            'host' => $stored['mail_host'] ?? $base['host'],
            'port' => isset($stored['mail_port']) ? (int) $stored['mail_port'] : $base['port'],
            'encryption' => $stored['mail_encryption'] ?? $base['encryption'],
            'username' => $stored['mail_username'] ?? $base['username'],
            'password' => $stored['mail_password'] ?? $base['password'],
            'from_address' => $stored['mail_from_address'] ?? $base['from_address'],
            'from_name' => $stored['mail_from_name'] ?? $base['from_name'],
        ];
    }

    public static function appUrl(): ?string
    {
        $stored = self::get('app_url');

        return $stored !== null && $stored !== '' ? $stored : Config::get()['app_url'];
    }
}
