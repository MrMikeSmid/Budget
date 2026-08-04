<?php

namespace App\Models;

use App\Support\Database;

/**
 * Laatst gegenereerde AI-advies per periode, met tijdstempel — voorkomt dat
 * elke dashboardweergave een nieuwe (betaalde) Gemini-aanroep kost.
 */
final class AiAdviceCache
{
    public static function get(int $periodId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ai_advice_cache WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function save(int $periodId, string $text): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO ai_advice_cache (period_id, advice_text, generated_at)
             VALUES (:period_id, :text, datetime('now'))
             ON CONFLICT(period_id) DO UPDATE SET advice_text = excluded.advice_text, generated_at = excluded.generated_at"
        );
        $stmt->execute(['period_id' => $periodId, 'text' => $text]);
    }
}
