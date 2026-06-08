<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use JsonException;

final class FirebaseSettings
{
    private const KEYS = [
        'project_id' => 'firebase_project_id',
        'api_key' => 'firebase_api_key',
        'messaging_sender_id' => 'firebase_messaging_sender_id',
        'app_id' => 'firebase_app_id',
        'vapid_public_key' => 'firebase_vapid_public_key',
        'service_account_json' => 'firebase_service_account_json',
    ];

    public function projectId(): string { return $this->value('project_id', 'firebase_project_id'); }
    public function apiKey(): string { return $this->value('api_key', 'firebase_api_key'); }
    public function messagingSenderId(): string { return $this->value('messaging_sender_id', 'firebase_messaging_sender_id'); }
    public function appId(): string { return $this->value('app_id', 'firebase_app_id'); }
    public function vapidPublicKey(): string { return $this->value('vapid_public_key', 'firebase_vapid_public_key'); }
    public function serviceAccountJson(): string { return $this->value('service_account_json', 'firebase_service_account_json'); }

    public function publicConfig(): array
    {
        return [
            'apiKey' => $this->apiKey(),
            'projectId' => $this->projectId(),
            'messagingSenderId' => $this->messagingSenderId(),
            'appId' => $this->appId(),
        ];
    }

    public function isClientConfigured(): bool
    {
        return !in_array('', [...array_values($this->publicConfig()), $this->vapidPublicKey()], true);
    }

    public function isServerConfigured(): bool
    {
        $account = $this->serviceAccount();
        return $this->projectId() !== '' && !empty($account['client_email']) && !empty($account['private_key']);
    }

    public function isConfigured(): bool
    {
        return $this->isClientConfigured() && $this->isServerConfigured();
    }

    public function serviceAccount(): array
    {
        try {
            $decoded = json_decode($this->serviceAccountJson(), true, 32, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    public function save(array $values, ?string $serviceAccountJson): void
    {
        $settings = [];
        foreach (['project_id', 'api_key', 'messaging_sender_id', 'app_id', 'vapid_public_key'] as $key) {
            $settings[self::KEYS[$key]] = trim((string) ($values[$key] ?? ''));
        }
        if ($serviceAccountJson !== null) {
            $settings[self::KEYS['service_account_json']] = trim($serviceAccountJson);
        }
        (new AppSetting())->setMany($settings);
    }

    private function value(string $setting, string $configKey): string
    {
        $stored = (new AppSetting())->get(self::KEYS[$setting]);
        return trim($stored ?? (string) config($configKey, ''));
    }
}
