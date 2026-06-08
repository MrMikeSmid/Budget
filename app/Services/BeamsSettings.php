<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;

final class BeamsSettings
{
    private const INSTANCE_ID = 'beams_instance_id';
    private const SECRET_KEY = 'beams_secret_key';

    public function instanceId(): string
    {
        return $this->value(self::INSTANCE_ID, 'beams_instance_id');
    }

    public function secretKey(): string
    {
        return $this->value(self::SECRET_KEY, 'beams_secret_key');
    }

    public function isConfigured(): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->instanceId()) === 1
            && $this->secretKey() !== '';
    }

    public function save(string $instanceId, ?string $secretKey): void
    {
        $values = [self::INSTANCE_ID => trim($instanceId)];
        if ($secretKey !== null) {
            $values[self::SECRET_KEY] = trim($secretKey);
        }
        (new AppSetting())->setMany($values);
    }

    private function value(string $setting, string $configKey): string
    {
        $stored = (new AppSetting())->get($setting);
        return trim($stored ?? (string) config($configKey, ''));
    }
}
