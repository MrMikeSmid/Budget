<?php

declare(strict_types=1);

namespace App\Services;

final class PushNotificationService
{
    private const ENDPOINT = 'https://api.onesignal.com/notifications?c=push';

    /** @var callable(string, array<string, string>, string): (bool|array{status: int, body: string}) */
    private $transport;
    private ?string $lastError = null;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? [$this, 'post'];
    }

    public function isConfigured(): bool
    {
        return (new OneSignalSettings())->isConfigured();
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** @param list<int> $userIds */
    public function send(array $userIds, string $message, string $path = '/'): bool
    {
        $this->lastError = null;
        $userIds = array_values(array_unique(array_filter($userIds, static fn(int $id): bool => $id > 0)));
        if (!$this->isConfigured()) {
            return $this->fail('De OneSignal App ID of API key ontbreekt.');
        }
        if ($userIds === []) {
            return $this->fail('Er zijn geen ontvangers geselecteerd.');
        }

        $externalIds = $this->externalIdsForUsers($userIds);
        if ($externalIds === []) {
            return $this->fail('De geselecteerde gebruikers hebben geen push-ID.');
        }

        $oneSignal = new OneSignalSettings();
        $payload = json_encode([
            'app_id' => $oneSignal->appId(),
            'include_aliases' => [
                'external_id' => $externalIds,
            ],
            'target_channel' => 'push',
            'headings' => ['en' => 'Samen'],
            'contents' => ['en' => $message],
            'url' => absolute_url($path),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $headers = [
            'Authorization' => 'Key ' . $oneSignal->apiKey(),
            'Content-Type' => 'application/json; charset=utf-8',
        ];

        try {
            $result = ($this->transport)(self::ENDPOINT, $headers, $payload);
            if (is_bool($result)) {
                return $result || $this->fail('De verbinding met OneSignal is mislukt.');
            }
            return $this->responseWasAccepted($result['status'], $result['body']);
        } catch (\Throwable $exception) {
            error_log('Samen push notification failed: ' . $exception->getMessage());
            return $this->fail('De verbinding met OneSignal is mislukt.');
        }
    }

    /** @param list<int> $userIds
     *  @return list<string>
     */
    private function externalIdsForUsers(array $userIds): array
    {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = db()->prepare("SELECT push_external_id FROM users WHERE id IN ($placeholders)");
        $stmt->execute($userIds);
        return array_values(array_filter(array_column($stmt->fetchAll(), 'push_external_id')));
    }

    /** @param array<string, string> $headers
     *  @return array{status: int, body: string}
     */
    private function post(string $url, array $headers, string $payload): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => array_map(
                    static fn(string $name, string $value): string => $name . ': ' . $value,
                    array_keys($headers),
                    array_values($headers),
                ),
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($response === false) {
                $error = curl_error($curl);
                curl_close($curl);
                throw new \RuntimeException($error);
            }
            curl_close($curl);
            return ['status' => $status, 'body' => $response];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", array_map(
                    static fn(string $name, string $value): string => $name . ': ' . $value,
                    array_keys($headers),
                    array_values($headers),
                )),
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($response === false) {
            throw new \RuntimeException('OneSignal gaf geen antwoord.');
        }
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        return ['status' => (int) ($matches[1] ?? 0), 'body' => $response];
    }

    private function responseWasAccepted(int $status, string $body): bool
    {
        try {
            $response = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            error_log('Samen push notification failed: invalid OneSignal response (HTTP ' . $status . ').');
            return $this->fail('OneSignal gaf een ongeldig antwoord.');
        }

        if ($status >= 200 && $status < 300 && is_string($response['id'] ?? null) && $response['id'] !== '') {
            return true;
        }

        $details = $response['errors'] ?? $response['warnings'] ?? $response['message'] ?? null;
        $encodedDetails = is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $logDetails = $encodedDetails ? ': ' . mb_substr($encodedDetails, 0, 1000) : '';
        error_log('Samen push notification rejected (HTTP ' . $status . ')' . $logDetails);

        if ($status === 401 || $status === 403) {
            return $this->fail('OneSignal heeft de API key geweigerd. Controleer de App API Key.');
        }
        if ($status >= 200 && $status < 300) {
            return $this->fail('OneSignal vond geen actief pushabonnement voor deze gebruiker.');
        }
        return $this->fail('OneSignal heeft de melding geweigerd (HTTP ' . $status . ').');
    }

    private function fail(string $message): false
    {
        $this->lastError = $message;
        return false;
    }
}
