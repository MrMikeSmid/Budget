<?php

namespace App\Models;

use App\Support\Database;

/**
 * Eén gedeelde rij (id=1) met de Gemini API key en de systeemprompt voor het
 * AI-advies — net als de rest van de instellingen in deze app niet per
 * gebruiker, maar voor het hele huishouden.
 */
final class AiSettings
{
    public static function defaultPrompt(): string
    {
        return 'Je bent een persoonlijke financieel adviseur. Op basis van de onderstaande financiële gegevens van de gebruiker, geef kort en concreet advies (max 150 woorden) over uitgavenpatronen, spaarmogelijkheden en eventuele aandachtspunten. Wees vriendelijk maar direct, en vermijd algemene open deuren.';
    }

    public static function get(): array
    {
        $row = Database::connection()->query('SELECT * FROM ai_settings WHERE id = 1')->fetch();

        return $row ?: ['gemini_api_key' => null, 'system_prompt' => self::defaultPrompt()];
    }

    public static function hasApiKey(): bool
    {
        return !empty(self::get()['gemini_api_key']);
    }

    /**
     * $apiKey null of leeg laat de al opgeslagen key ongemoeid — het
     * instellingenformulier toont de key namelijk nooit terug, dus een leeg
     * veld betekent "niet gewijzigd", niet "verwijderen".
     */
    public static function save(?string $apiKey, string $systemPrompt): void
    {
        $current = self::get();
        $keyToStore = ($apiKey !== null && $apiKey !== '') ? $apiKey : ($current['gemini_api_key'] ?? null);

        $stmt = Database::connection()->prepare(
            "UPDATE ai_settings SET gemini_api_key = :key, system_prompt = :prompt, updated_at = datetime('now') WHERE id = 1"
        );
        $stmt->execute([
            'key' => $keyToStore,
            'prompt' => $systemPrompt !== '' ? $systemPrompt : self::defaultPrompt(),
        ]);
    }
}
