<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;

final class OneSignalSettings
{
    private const APP_ID = 'onesignal_app_id';
    private const REST_API_KEY = 'onesignal_rest_api_key';

    public function appId(): string
    {
        return $this->value(self::APP_ID, 'onesignal_app_id');
    }

    public function restApiKey(): string
    {
        return $this->value(self::REST_API_KEY, 'onesignal_rest_api_key');
    }

    public function isConfigured(): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->appId()) === 1
            && $this->restApiKey() !== '';
    }

    public function save(string $appId, ?string $restApiKey): void
    {
        $values = [self::APP_ID => trim($appId)];
        if ($restApiKey !== null) {
            $values[self::REST_API_KEY] = trim($restApiKey);
        }
        (new AppSetting())->setMany($values);
    }

    private function value(string $setting, string $configKey): string
    {
        $stored = (new AppSetting())->get($setting);
        return trim($stored ?? (string) config($configKey, ''));
    }
}
