<?php

declare(strict_types=1);

namespace McpEmail\Mail;

final class ImapConnectionException extends \RuntimeException
{
    /** @param array<string, mixed> $diagnostics */
    public function __construct(
        string $message,
        private readonly string $errorType = 'unknown_imap_error',
        private readonly array $diagnostics = [],
    ) {
        parent::__construct($message);
    }

    public function errorType(): string
    {
        return $this->errorType;
    }

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
