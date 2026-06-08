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
        return (new FirebaseSettings())->isConfigured();
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function sendToken(string $token, string $message, string $path = '/'): bool
    {
        $this->lastError = null;
        $settings = new FirebaseSettings();
        if (!$settings->isServerConfigured()) {
            return $this->fail('Firebase is server-side nog niet volledig ingesteld.');
        }
        if (trim($token) === '') {
            return $this->fail('Dit apparaat heeft geen geldig Firebase-token.');
        }

        try {
            $accessToken = $this->accessToken($settings->serviceAccount());
            $payload = json_encode([
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => 'Samen',
                        'body' => mb_substr(trim($message), 0, 500),
                    ],
                    'webpush' => [
                        'fcm_options' => ['link' => absolute_url($path)],
                        'notification' => ['icon' => absolute_url('/pwa-icon/app-192')],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $response = ($this->transport)(
                'POST',
                'https://fcm.googleapis.com/v1/projects/' . rawurlencode($settings->projectId()) . '/messages:send',
                ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
                $payload,
            );
        } catch (\Throwable $exception) {
            error_log('Samen Firebase push failed: ' . $exception->getMessage());
            return $this->fail('Firebase kon niet worden bereikt of geverifieerd.');
        }

        $status = (int) ($response['status'] ?? 0);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if ($status >= 200 && $status < 300 && !empty($body['name'])) {
            return true;
        }

        error_log('Samen Firebase push rejected (HTTP ' . $status . '): ' . mb_substr((string) ($response['body'] ?? ''), 0, 1000));
        if ($status === 401 || $status === 403) {
            return $this->fail('Firebase heeft het serviceaccount geweigerd.');
        }
        if ($status === 404) {
            return $this->fail('Het Firebase-project of apparaat-token is niet gevonden.');
        }
        return $this->fail('Firebase heeft de testmelding geweigerd (HTTP ' . $status . ').');
    }

    private function accessToken(array $account): string
    {
        $issuedAt = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $account['client_email'] ?? '',
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $claims;
        $signature = '';
        if (!openssl_sign($unsigned, $signature, (string) ($account['private_key'] ?? ''), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Het serviceaccount kon de OAuth-aanvraag niet ondertekenen.');
        }

        $response = ($this->transport)(
            'POST',
            'https://oauth2.googleapis.com/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . $this->base64Url($signature),
            ]),
        );
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if ((int) ($response['status'] ?? 0) !== 200 || empty($body['access_token'])) {
            throw new RuntimeException('Google OAuth gaf geen toegangstoken terug.');
        }
        return (string) $body['access_token'];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
                CURLOPT_TIMEOUT => 20,
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
            'timeout' => 20,
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
