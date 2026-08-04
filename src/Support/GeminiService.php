<?php

namespace App\Support;

use App\Models\AiSettings;

/**
 * Roept de Gemini API aan om een financieel advies te genereren. Draait
 * bewust volledig server-side: de API key verlaat de server nooit, de
 * browser krijgt alleen de resulterende adviestekst te zien.
 */
final class GeminiService
{
    private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const TIMEOUT_SECONDS = 20;

    /**
     * @return array{ok: bool, text?: string, error?: string}
     */
    public static function advise(string $financialSummary): array
    {
        $settings = AiSettings::get();
        $apiKey = $settings['gemini_api_key'] ?? null;

        if (empty($apiKey)) {
            return ['ok' => false, 'error' => 'Vul eerst je Gemini API key in bij Instellingen.'];
        }

        $model = !empty($settings['gemini_model']) ? $settings['gemini_model'] : AiSettings::DEFAULT_MODEL;
        $systemPrompt = $settings['system_prompt'] !== '' ? $settings['system_prompt'] : AiSettings::defaultPrompt();

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['parts' => [['text' => $financialSummary]]],
            ],
        ];

        $ch = curl_init(self::ENDPOINT_BASE . rawurlencode($model) . ':generateContent');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            // x-goog-api-key i.p.v. ?key=... in de URL: zelfde effect, maar
            // de sleutel komt dan niet in een URL terecht die per ongeluk in
            // (proxy-/error-)logs zou kunnen belanden.
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            return ['ok' => false, 'error' => 'Kon geen verbinding maken met Gemini: ' . $curlError];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return ['ok' => false, 'error' => self::errorMessageFor($statusCode, $response, $model)];
        }

        $data = json_decode((string) $response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Gemini gaf geen bruikbaar antwoord terug.'];
        }

        return ['ok' => true, 'text' => trim($text)];
    }

    /**
     * Google stuurt in de responsebody meestal een concrete reden mee
     * ("error.message") — die tonen we erbij, zodat een fout hier direct te
     * begrijpen is i.p.v. alleen een kale HTTP-status.
     */
    private static function errorMessageFor(int $statusCode, ?string $response, string $model): string
    {
        $data = json_decode((string) $response, true);
        $detail = $data['error']['message'] ?? null;

        if ($statusCode === 429) {
            return 'Gemini geeft aan dat de gebruikslimiet is bereikt (429). Probeer het straks nog eens.'
                . ($detail ? ' (' . $detail . ')' : '');
        }

        if ($statusCode === 404) {
            return "Gemini vindt het model \"{$model}\" niet (HTTP 404) — waarschijnlijk is de modelnaam verouderd. Pas 'm aan bij Instellingen → AI-advies."
                . ($detail ? ' (' . $detail . ')' : '');
        }

        if ($statusCode === 400 || $statusCode === 401 || $statusCode === 403) {
            return 'Gemini wees het verzoek af (HTTP ' . $statusCode . ') — controleer of de API key bij Instellingen nog klopt.'
                . ($detail ? ' (' . $detail . ')' : '');
        }

        return 'Gemini gaf een onverwachte fout terug (HTTP ' . $statusCode . ').' . ($detail ? ' (' . $detail . ')' : '');
    }
}
