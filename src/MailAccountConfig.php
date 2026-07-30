<?php

declare(strict_types=1);

namespace McpEmail;

final class MailAccountConfig
{
    public function __construct(
        public readonly string $id,
        public readonly string $mailProtocol,
        public readonly string $imapHost,
        public readonly int $imapPort,
        public readonly bool $imapSecure,
        public readonly string $imapUser,
        public readonly string $imapPass,
        public readonly string $smtpHost,
        public readonly int $smtpPort,
        public readonly bool $smtpSecure,
        public readonly string $smtpUser,
        public readonly string $smtpPass,
        public readonly string $fromAddress,
        public readonly ?string $fromName,
        public readonly bool $mailNoValidateCert = false,
        public readonly bool $mailConnectIpv4 = false,
        public readonly float $mailSocketTimeout = 5.0,
    ) {
    }
}
