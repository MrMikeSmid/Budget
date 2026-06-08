<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PushNotificationService
{
    private $transport;
    private ?string $lastError = null;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? [$this, 'request'];
    }

    public function isConfigured(): bool
    {
        return (new BeamsSettings())->isConfigured();
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function sendToken(string $interest, string $message, string $path = '/'): bool
    {
        return $this->sendInterests([$interest], 'Samen', $message, $path);
    }

    /** @param list<int> $userIds */
    public function sendUsers(array $userIds, string $title, string $message, string $path = '/'): bool
    {
        $interests = (new PushSubscriptionService())->tokensForUsers($userIds);
        if ($interests === []) {
            return true;
        }

        return $this->sendInterests($interests, $title, $message, $path);
    }

    /** @param list<string> $interests */
    public function sendInterests(array $interests, string $title, string $message, string $path = '/'): bool
    {
        $this->lastError = null;
        $settings = new BeamsSettings();
        if (!$settings->isConfigured()) {
            return $this->fail('Pusher Beams is nog niet volledig ingesteld.');
        }

        $interests = array_values(array_unique(array_filter($interests, fn(mixed $interest): bool =>
            is_string($interest) && preg_match('/^[A-Za-z0-9_\-=@,.;]{1,164}$/', $interest) === 1
        )));
        if ($interests === []) {
            return $this->fail('Er zijn geen geldige Beams-apparaatregistraties gevonden.');
        }

        $instanceId = $settings->instanceId();
        $payload = json_encode([
            'interests' => $interests,
            'web' => ['notification' => [
                'title' => mb_substr(trim($title), 0, 80) ?: 'Samen',
                'body' => mb_substr(trim($message), 0, 500),
                'deep_link' => absolute_url($path),
                'icon' => absolute_url('/pwa-icon/app-192'),
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $response = ($this->transport)(
                'POST',
                'https://' . rawurlencode($instanceId) . '.pushnotifications.pusher.com/publish_api/v1/instances/' . rawurlencode($instanceId) . '/publishes/interests',
                ['Authorization: Bearer ' . $settings->secretKey(), 'Content-Type: application/json'],
                $payload,
            );
        } catch (\Throwable $exception) {
            error_log('Samen Pusher Beams push failed: ' . $exception->getMessage());
            return $this->fail('Pusher Beams kon niet worden bereikt.');
        }

        $status = (int) ($response['status'] ?? 0);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if ($status >= 200 && $status < 300 && !empty($body['publishId'])) {
            return true;
        }

        error_log('Samen Pusher Beams push rejected (HTTP ' . $status . '): ' . mb_substr((string) ($response['body'] ?? ''), 0, 1000));
        return match ($status) {
            401, 403 => $this->fail('Pusher Beams heeft de Secret Key geweigerd.'),
            402 => $this->fail('De limiet van het Pusher Beams-abonnement is bereikt.'),
            404 => $this->fail('De Pusher Beams Instance ID is niet gevonden.'),
            422 => $this->fail('Pusher Beams kon deze apparaatregistratie niet verwerken.'),
            default => $this->fail('Pusher Beams heeft de melding geweigerd (HTTP ' . $status . ').'),
        };
    }

    private function request(string $method, string $url, array $headers, ?string $payload): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $body = curl_exec($curl);
            if ($body === false) {
                throw new RuntimeException(curl_error($curl));
            }
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            return ['status' => $status, 'body' => $body];
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $payload ?? '',
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $body = file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        return ['status' => (int) ($matches[1] ?? 0), 'body' => $body === false ? '' : $body];
    }

    private function fail(string $message): false
    {
        $this->lastError = $message;
        return false;
    }
}
