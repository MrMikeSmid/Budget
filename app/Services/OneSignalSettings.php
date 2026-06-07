<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;

final class OneSignalSettings
{
    public function appId(): string
    {
        $stored = (new AppSetting())->get('onesignal_app_id');
        return trim($stored ?? (string) config('onesignal_app_id', ''));
    }

    public function apiKey(): string
    {
        $stored = (new AppSetting())->get('onesignal_api_key');
        return trim($stored ?? (string) config('onesignal_api_key', ''));
    }

    public function isConfigured(): bool
    {
        return $this->appId() !== '' && $this->apiKey() !== '';
    }

    public function save(string $appId, ?string $apiKey): void
    {
        $settings = ['onesignal_app_id' => trim($appId)];
        if ($apiKey !== null) {
            $settings['onesignal_api_key'] = trim($apiKey);
        }
        (new AppSetting())->setMany($settings);
    }
}
