<?php

declare(strict_types=1);

namespace McpEmail;

final class Config
{
    /** @var array<string, MailAccountConfig>|null */
    private static ?array $accounts = null;

    private static ?string $bearerToken = null;

    private static function boolValue(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Reads a setting from a real environment variable first (works if the
     * host passes env vars through to PHP), falling back to the values
     * array loaded from config/config.php (the reliable option on shared
     * hosting where SetEnv/panel env vars are not always wired through).
     *
     * @param array<string, mixed> $fallback
     */
    private static function read(string $key, array $fallback): ?string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        return isset($fallback[$key]) && $fallback[$key] !== '' ? (string) $fallback[$key] : null;
    }

    /** @return array<string, mixed> */
    private static function loadConfigFile(): array
    {
        $path = dirname(__DIR__) . '/config/config.php';
        if (!is_file($path)) {
            return [];
        }

        /** @psalm-suppress UnresolvableInclude */
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $raw */
    private static function accountFromArray(array $raw, string $fallbackId): MailAccountConfig
    {
        $mailProtocol = strtolower((string) ($raw['mailProtocol'] ?? 'imap'));
        $imapUser = (string) ($raw['imapUser'] ?? '');
        $imapPass = (string) ($raw['imapPass'] ?? '');
        $smtpUser = (string) ($raw['smtpUser'] ?? $imapUser);
        $smtpPass = (string) ($raw['smtpPass'] ?? $imapPass);
        $imapHost = (string) ($raw['imapHost'] ?? '');
        $smtpHost = (string) ($raw['smtpHost'] ?? '');

        if (!in_array($mailProtocol, ['imap', 'pop3'], true)) {
            throw new ConfigException("Account \"$fallbackId\" heeft een ongeldig inkomend mailprotocol (gebruik imap of pop3).");
        }
        if ($imapHost === '' || $imapUser === '' || $imapPass === '') {
            throw new ConfigException("Account \"$fallbackId\" mist verplichte inkomende mailconfiguratie (host/user/pass).");
        }
        if ($smtpHost === '') {
            throw new ConfigException("Account \"$fallbackId\" mist verplichte SMTP-configuratie (host).");
        }

        $smtpPort = (int) ($raw['smtpPort'] ?? 587);

        return new MailAccountConfig(
            id: (string) ($raw['id'] ?? $fallbackId),
            mailProtocol: $mailProtocol,
            imapHost: $imapHost,
            imapPort: (int) ($raw['imapPort'] ?? 993),
            imapSecure: (bool) ($raw['imapSecure'] ?? true),
            imapUser: $imapUser,
            imapPass: $imapPass,
            smtpHost: $smtpHost,
            smtpPort: $smtpPort,
            smtpSecure: (bool) ($raw['smtpSecure'] ?? ($smtpPort === 465)),
            smtpUser: $smtpUser,
            smtpPass: $smtpPass,
            fromAddress: (string) ($raw['fromAddress'] ?? $smtpUser),
            fromName: isset($raw['fromName']) ? (string) $raw['fromName'] : null,
            mailNoValidateCert: self::boolValue($raw['mailNoValidateCert'] ?? false),
            mailConnectIpv4: self::boolValue($raw['mailConnectIpv4'] ?? false),
            mailSocketTimeout: max(1.0, min(30.0, (float) ($raw['mailSocketTimeout'] ?? 10.0))),
        );
    }

    /** @return array<string, MailAccountConfig> */
    private static function loadAccounts(): array
    {
        if (self::$accounts !== null) {
            return self::$accounts;
        }

        $fileConfig = self::loadConfigFile();
        $diagnosticDefaults = [
            'mailNoValidateCert' => self::read('MAIL_NOVALIDATE_CERT', $fileConfig),
            'mailConnectIpv4' => self::read('MAIL_CONNECT_IPV4', $fileConfig),
            'mailSocketTimeout' => self::read('MAIL_SOCKET_TIMEOUT', $fileConfig),
        ];

        $multiJson = self::read('MAIL_ACCOUNTS_JSON', $fileConfig);
        $rawAccounts = [];

        if ($multiJson !== null) {
            $decoded = json_decode($multiJson, true);
            if (!is_array($decoded) || $decoded === []) {
                throw new ConfigException('MAIL_ACCOUNTS_JSON moet een niet-lege JSON-array van accounts zijn.');
            }
            foreach ($decoded as $index => $raw) {
                $raw += $diagnosticDefaults;
                $rawAccounts[] = self::accountFromArray($raw, (string) ($raw['id'] ?? ('account-' . ($index + 1))));
            }
        } elseif (isset($fileConfig['accounts']) && is_array($fileConfig['accounts']) && $fileConfig['accounts'] !== []) {
            foreach ($fileConfig['accounts'] as $index => $raw) {
                $raw += $diagnosticDefaults;
                $rawAccounts[] = self::accountFromArray($raw, (string) ($raw['id'] ?? ('account-' . ($index + 1))));
            }
        } else {
            $mailProtocol = self::read('MAIL_PROTOCOL', $fileConfig) ?? 'imap';
            $imapHost = self::read('IMAP_HOST', $fileConfig);
            $imapUser = self::read('IMAP_USER', $fileConfig);
            $imapPass = self::read('IMAP_PASSWORD', $fileConfig);
            $smtpHost = self::read('SMTP_HOST', $fileConfig);

            if ($imapHost !== null && $imapUser !== null && $imapPass !== null && $smtpHost !== null) {
                $imapSecureRaw = self::read('IMAP_SECURE', $fileConfig);
                $smtpSecureRaw = self::read('SMTP_SECURE', $fileConfig);
                $imapPortRaw = self::read('IMAP_PORT', $fileConfig);
                $smtpPortRaw = self::read('SMTP_PORT', $fileConfig);

                $rawAccounts[] = self::accountFromArray([
                    'id' => 'default',
                    'mailProtocol' => $mailProtocol,
                    'imapHost' => $imapHost,
                    'imapPort' => $imapPortRaw !== null ? (int) $imapPortRaw : 993,
                    'imapSecure' => $imapSecureRaw === null ? true : $imapSecureRaw !== 'false',
                    'imapUser' => $imapUser,
                    'imapPass' => $imapPass,
                    'smtpHost' => $smtpHost,
                    'smtpPort' => $smtpPortRaw !== null ? (int) $smtpPortRaw : 587,
                    'smtpSecure' => $smtpSecureRaw === null ? null : $smtpSecureRaw !== 'false',
                    'smtpUser' => self::read('SMTP_USER', $fileConfig),
                    'smtpPass' => self::read('SMTP_PASSWORD', $fileConfig),
                    'fromAddress' => self::read('SMTP_FROM_ADDRESS', $fileConfig),
                    'fromName' => self::read('SMTP_FROM_NAME', $fileConfig),
                    'mailNoValidateCert' => self::read('MAIL_NOVALIDATE_CERT', $fileConfig),
                    'mailConnectIpv4' => self::read('MAIL_CONNECT_IPV4', $fileConfig),
                    'mailSocketTimeout' => self::read('MAIL_SOCKET_TIMEOUT', $fileConfig),
                ], 'default');
            }
        }

        if ($rawAccounts === []) {
            throw new ConfigException(
                'Geen e-mailaccount geconfigureerd. Zet IMAP_HOST/IMAP_USER/IMAP_PASSWORD/SMTP_HOST ' .
                '(via config/config.php of environment variables), of MAIL_ACCOUNTS_JSON.'
            );
        }

        $byId = [];
        foreach ($rawAccounts as $account) {
            if (isset($byId[$account->id])) {
                throw new ConfigException("Dubbel account-id gevonden in configuratie: \"{$account->id}\".");
            }
            $byId[$account->id] = $account;
        }

        self::$accounts = $byId;
        return $byId;
    }

    public static function getAccount(?string $accountId = null): MailAccountConfig
    {
        $accounts = self::loadAccounts();

        if ($accountId === null) {
            return array_values($accounts)[0];
        }

        if (!isset($accounts[$accountId])) {
            $available = implode(', ', array_keys($accounts));
            throw new ConfigException("Onbekend account-id \"$accountId\". Beschikbare accounts: $available.");
        }

        return $accounts[$accountId];
    }

    public static function getBearerToken(): string
    {
        if (self::$bearerToken !== null) {
            return self::$bearerToken;
        }

        $fileConfig = self::loadConfigFile();
        $token = self::read('MCP_BEARER_TOKEN', $fileConfig);

        if ($token === null || $token === '') {
            throw new ConfigException(
                'MCP_BEARER_TOKEN is niet ingesteld. Zet deze via config/config.php of als environment variable.'
            );
        }

        self::$bearerToken = $token;
        return $token;
    }

    public static function debugMail(): bool
    {
        $fileConfig = self::loadConfigFile();
        return self::boolValue(self::read('DEBUG_MAIL', $fileConfig));
    }
}
