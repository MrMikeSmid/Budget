<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;

final class SmtpSettings
{
    public function host(): string
    {
        return trim($this->setting('smtp_host', 'smtp_host'));
    }

    public function port(): int
    {
        $port = (int) $this->setting('smtp_port', 'smtp_port', '587');
        return $port >= 1 && $port <= 65535 ? $port : 587;
    }

    public function encryption(): string
    {
        $encryption = strtolower(trim($this->setting('smtp_encryption', 'smtp_encryption', 'starttls')));
        return in_array($encryption, ['starttls', 'tls', 'none'], true) ? $encryption : 'starttls';
    }

    public function username(): string
    {
        return trim($this->setting('smtp_username', 'smtp_username'));
    }

    public function password(): string
    {
        return $this->setting('smtp_password', 'smtp_password');
    }

    public function timeout(): int
    {
        $timeout = (int) $this->setting('smtp_timeout', 'smtp_timeout', '15');
        return $timeout >= 5 && $timeout <= 60 ? $timeout : 15;
    }

    public function isConfigured(): bool
    {
        return $this->host() !== '' && $this->port() > 0;
    }

    public function save(
        string $host,
        int $port,
        string $encryption,
        string $username,
        ?string $password,
        int $timeout,
        bool $clearPassword = false
    ): void {
        $settings = [
            'smtp_host' => trim($host),
            'smtp_port' => (string) $port,
            'smtp_encryption' => $encryption,
            'smtp_username' => trim($username),
            'smtp_timeout' => (string) $timeout,
        ];

        if ($password !== null && $password !== '') {
            $settings['smtp_password'] = $password;
        } elseif ($clearPassword) {
            $settings['smtp_password'] = '';
        }

        (new AppSetting())->setMany($settings);
    }

    private function setting(string $databaseKey, string $configKey, string $default = ''): string
    {
        $configured = (string) config($configKey, $default);
        return (new AppSetting())->get($databaseKey, $configured) ?? $configured;
    }
}
