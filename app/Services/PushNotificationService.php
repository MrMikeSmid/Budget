<?php

declare(strict_types=1);

namespace App\Services;

final class PushNotificationService
{
    private const ENDPOINT = 'https://api.onesignal.com/notifications?c=push';

    /** @var callable(string, array<string, string>, string): bool */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? [$this, 'post'];
    }

    public function isConfigured(): bool
    {
        return config('onesignal_app_id', '') !== '' && config('onesignal_api_key', '') !== '';
    }

    /** @param list<int> $userIds */
    public function send(array $userIds, string $message, string $path = '/'): bool
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn(int $id): bool => $id > 0)));
        if (!$this->isConfigured() || $userIds === []) {
            return false;
        }

        $externalIds = $this->externalIdsForUsers($userIds);
        if ($externalIds === []) {
            return false;
        }

        $payload = json_encode([
            'app_id' => config('onesignal_app_id'),
            'include_aliases' => [
                'external_id' => $externalIds,
            ],
            'target_channel' => 'push',
            'headings' => ['en' => 'Samen'],
            'contents' => ['en' => $message],
            'url' => absolute_url($path),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $headers = [
            'Authorization' => 'Key ' . config('onesignal_api_key'),
            'Content-Type' => 'application/json; charset=utf-8',
        ];

        try {
            return (bool) ($this->transport)(self::ENDPOINT, $headers, $payload);
        } catch (\Throwable $exception) {
            error_log('Samen push notification failed: ' . $exception->getMessage());
            return false;
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

    /** @param array<string, string> $headers */
    private function post(string $url, array $headers, string $payload): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", array_map(
                    static fn(string $name, string $value): string => $name . ': ' . $value,
                    array_keys($headers),
                    array_values($headers),
                )),
                'content' => $payload,
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        return $response !== false && preg_match('/\s2\d{2}\s/', $statusLine) === 1;
    }
}
