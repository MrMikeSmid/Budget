<?php

declare(strict_types=1);

namespace App\Services;

final class OneSignalSubscriptionService
{
    private const API_URL = 'https://api.onesignal.com';

    /** @var callable(string, string, array<string, string>, ?string): array{status: int, body: string} */
    private $transport;
    private ?string $lastError = null;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? [$this, 'request'];
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param list<array{id: int|string, name: string, email: string, push_external_id: string}> $users
     * @return list<array{user_id: int, name: string, email: string, external_id: string, subscription_id: string, type: string, enabled: bool, device: string, last_active: ?int}>
     */
    public function forUsers(array $users): array
    {
        $this->lastError = null;
        if (!(new OneSignalSettings())->isConfigured()) {
            $this->lastError = 'OneSignal is nog niet volledig ingesteld.';
            return [];
        }

        $subscriptions = [];
        foreach ($users as $user) {
            $externalId = (string) ($user['push_external_id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $profile = $this->fetchUser($externalId);
            if ($profile === null) {
                continue;
            }

            foreach (($profile['subscriptions'] ?? []) as $subscription) {
                if (!is_array($subscription) || !$this->isPushSubscription((string) ($subscription['type'] ?? ''))) {
                    continue;
                }
                $subscriptions[] = [
                    'user_id' => (int) $user['id'],
                    'name' => (string) $user['name'],
                    'email' => (string) $user['email'],
                    'external_id' => $externalId,
                    'subscription_id' => (string) ($subscription['id'] ?? ''),
                    'type' => (string) ($subscription['type'] ?? 'Push'),
                    'enabled' => (bool) ($subscription['enabled'] ?? false),
                    'device' => trim(implode(' ', array_filter([
                        (string) ($subscription['device_model'] ?? ''),
                        (string) ($subscription['device_os'] ?? ''),
                    ]))) ?: 'Onbekend apparaat',
                    'last_active' => isset($profile['properties']['last_active']) ? (int) $profile['properties']['last_active'] : null,
                ];
            }
        }

        return $subscriptions;
    }

    public function deleteForExternalId(string $externalId, string $subscriptionId): bool
    {
        $profile = $this->fetchUser($externalId);
        if ($profile === null) {
            return $this->fail('Dit pushabonnement hoort niet bij je account.');
        }

        foreach (($profile['subscriptions'] ?? []) as $subscription) {
            if (is_array($subscription) && hash_equals((string) ($subscription['id'] ?? ''), $subscriptionId)) {
                return $this->delete($subscriptionId);
            }
        }

        return $this->fail('Dit pushabonnement hoort niet bij je account.');
    }

    public function delete(string $subscriptionId): bool
    {
        $this->lastError = null;
        if (preg_match('/^[0-9a-f-]{36}$/i', $subscriptionId) !== 1) {
            return $this->fail('Het abonnement-ID is ongeldig.');
        }

        $settings = new OneSignalSettings();
        if (!$settings->isConfigured()) {
            return $this->fail('OneSignal is nog niet volledig ingesteld.');
        }

        try {
            $response = ($this->transport)(
                'DELETE',
                self::API_URL . '/apps/' . rawurlencode($settings->appId()) . '/subscriptions/' . rawurlencode($subscriptionId),
                $this->headers($settings),
                null,
            );
        } catch (\Throwable $exception) {
            error_log('Samen OneSignal subscription delete failed: ' . $exception->getMessage());
            return $this->fail('OneSignal kon niet worden bereikt.');
        }

        if ($response['status'] === 202 || $response['status'] === 204) {
            return true;
        }
        if ($response['status'] === 404) {
            return $this->fail('Dit abonnement bestaat niet meer in OneSignal.');
        }
        if ($response['status'] === 401 || $response['status'] === 403) {
            return $this->fail('OneSignal heeft de API key geweigerd.');
        }
        return $this->fail('OneSignal kon het abonnement niet verwijderen (HTTP ' . $response['status'] . ').');
    }

    /** @return array<string, mixed>|null */
    private function fetchUser(string $externalId): ?array
    {
        $settings = new OneSignalSettings();
        try {
            $response = ($this->transport)(
                'GET',
                self::API_URL . '/apps/' . rawurlencode($settings->appId()) . '/users/by/external_id/' . rawurlencode($externalId),
                $this->headers($settings),
                null,
            );
        } catch (\Throwable $exception) {
            error_log('Samen OneSignal user lookup failed: ' . $exception->getMessage());
            $this->lastError = 'Niet alle abonnementen konden bij OneSignal worden opgehaald.';
            return null;
        }

        if ($response['status'] === 404) {
            return null;
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $this->lastError = $response['status'] === 401 || $response['status'] === 403
                ? 'OneSignal heeft de API key geweigerd.'
                : 'Niet alle abonnementen konden bij OneSignal worden opgehaald.';
            return null;
        }

        try {
            $decoded = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            $this->lastError = 'OneSignal gaf een ongeldig antwoord.';
            return null;
        }
    }

    private function isPushSubscription(string $type): bool
    {
        return $type !== '' && !in_array(mb_strtolower($type), ['email', 'sms'], true);
    }

    /** @return array<string, string> */
    private function headers(OneSignalSettings $settings): array
    {
        return [
            'Authorization' => 'Key ' . $settings->apiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
        ];
    }

    /** @param array<string, string> $headers
     *  @return array{status: int, body: string}
     */
    private function request(string $method, string $url, array $headers, ?string $payload): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => array_map(
                    static fn(string $name, string $value): string => $name . ': ' . $value,
                    array_keys($headers),
                    array_values($headers),
                ),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            if ($payload !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            }
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($body === false) {
                $error = curl_error($curl);
                curl_close($curl);
                throw new \RuntimeException($error);
            }
            curl_close($curl);
            return ['status' => $status, 'body' => $body];
        }

        $options = [
            'method' => $method,
            'header' => implode("\r\n", array_map(
                static fn(string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                array_values($headers),
            )),
            'timeout' => 10,
            'ignore_errors' => true,
        ];
        if ($payload !== null) {
            $options['content'] = $payload;
        }
        $context = stream_context_create(['http' => $options]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($body === false) {
            throw new \RuntimeException('OneSignal gaf geen antwoord.');
        }
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        return ['status' => (int) ($matches[1] ?? 0), 'body' => $body];
    }

    private function fail(string $message): false
    {
        $this->lastError = $message;
        return false;
    }
}
