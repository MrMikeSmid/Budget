<?php

declare(strict_types=1);

namespace App\Models;

final class AppSetting
{
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = db()->prepare('SELECT value FROM app_settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    /** @param array<string, string> $settings */
    public function setMany(array $settings): void
    {
        $stmt = db()->prepare(<<<'SQL'
            INSERT INTO app_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
        SQL);

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }
}
