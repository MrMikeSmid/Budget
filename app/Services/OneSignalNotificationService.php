<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class OneSignalNotificationService
{
    private $transport;
    private ?string $lastError = null;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? [$this, 'request'];
    }

    public function isConfigured(): bool
    {
        return (new OneSignalSettings())->isConfigured();
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function sendSubscription(string $subscriptionId, string $message, string $path = '/'): bool
    {
        return $this->sendSubscriptions([$subscriptionId], 'Samen', $message, $path);
    }

    /** @param list<int> $userIds */
    public function sendUsers(array $userIds, string $title, string $message, string $path = '/', string $notificationType = 'general'): bool
    {
        $subscriptionIds = (new NotificationSubscriptionService())->idsForUsers($userIds, $notificationType);
        if ($subscriptionIds === []) {
            return true;
        }

        return $this->sendSubscriptions($subscriptionIds, $title, $message, $path);
    }

    /** @param list<string> $subscriptionIds */
    public function sendSubscriptions(array $subscriptionIds, string $title, string $message, string $path = '/'): bool
    {
        $this->lastError = null;
        $settings = new OneSignalSettings();
        if (!$settings->isConfigured()) {
            return $this->fail('OneSignal is nog niet volledig ingesteld.');
        }

        $subscriptionIds = array_values(array_unique(array_filter($subscriptionIds, static fn(mixed $id): bool =>
            is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id) === 1
        )));
        if ($subscriptionIds === []) {
            return $this->fail('Er zijn geen geldige OneSignal-apparaatregistraties gevonden.');
        }

        $payload = json_encode([
            'app_id' => $settings->appId(),
            'include_subscription_ids' => $subscriptionIds,
            'headings' => ['en' => mb_substr(trim($title), 0, 80) ?: 'Samen'],
            'contents' => ['en' => mb_substr(trim($message), 0, 500)],
            'url' => absolute_url($path),
            'chrome_web_icon' => absolute_url('/pwa-icon/app-192'),
            'firefox_icon' => absolute_url('/pwa-icon/app-192'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $response = ($this->transport)(
                'POST',
                'https://api.onesignal.com/notifications',
                ['Authorization: Key ' . $settings->restApiKey(), 'Content-Type: application/json; charset=utf-8'],
                $payload,
            );
        } catch (\Throwable $exception) {
            error_log('Samen OneSignal push failed: ' . $exception->getMessage());
            return $this->fail('OneSignal kon niet worden bereikt.');
        }

        $status = (int) ($response['status'] ?? 0);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if ($status >= 200 && $status < 300 && !empty($body['id'])) {
            return true;
        }

        error_log('Samen OneSignal push rejected (HTTP ' . $status . '): ' . mb_substr((string) ($response['body'] ?? ''), 0, 1000));
        return match ($status) {
            400 => $this->fail('OneSignal kon de melding of apparaatregistratie niet verwerken.'),
            401, 403 => $this->fail('OneSignal heeft de REST API Key geweigerd.'),
            404 => $this->fail('De OneSignal App ID is niet gevonden.'),
            429 => $this->fail('OneSignal ontvangt tijdelijk te veel verzoeken. Probeer het later opnieuw.'),
            default => $this->fail('OneSignal heeft de melding geweigerd (HTTP ' . $status . ').'),
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
