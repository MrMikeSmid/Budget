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
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
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

        $systemPrompt = $settings['system_prompt'] !== '' ? $settings['system_prompt'] : AiSettings::defaultPrompt();

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['parts' => [['text' => $financialSummary]]],
            ],
        ];

        $ch = curl_init(self::ENDPOINT);
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

        if ($statusCode === 429) {
            return ['ok' => false, 'error' => 'Gemini geeft aan dat de gebruikslimiet is bereikt (429). Probeer het straks nog eens.'];
        }

        if ($statusCode === 400 || $statusCode === 401 || $statusCode === 403) {
            return ['ok' => false, 'error' => 'Gemini wees het verzoek af (HTTP ' . $statusCode . ') — controleer of de API key bij Instellingen nog klopt.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return ['ok' => false, 'error' => 'Gemini gaf een onverwachte fout terug (HTTP ' . $statusCode . ').'];
        }

        $data = json_decode((string) $response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Gemini gaf geen bruikbaar antwoord terug.'];
        }

        return ['ok' => true, 'text' => trim($text)];
    }
}
