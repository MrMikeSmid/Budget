<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Mail\ImapConnectionException;

final class Support
{
    /** @return array{content: array<int, array{type: string, text: string}>} */
    public static function jsonResult(mixed $payload): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];
    }

    /** @return array{content: array<int, array{type: string, text: string}>, isError: true} */
    public static function errorResult(string $message): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
            'isError' => true,
        ];
    }

    /** Returns a structured, credential-free MCP error payload. */
    public static function mailConnectionError(ImapConnectionException $exception): array
    {
        $payload = [
            'success' => false,
            'phase' => Config::debugMail()
                ? (string) ($exception->diagnostics()['phase'] ?? 'unknown')
                : 'mail_connection',
            'error_type' => $exception->errorType(),
            'message' => Config::debugMail()
                ? $exception->getMessage()
                : 'De verbinding met de mailserver is mislukt. Raadpleeg de serverlog.',
            'diagnostics' => Config::debugMail() ? $exception->diagnostics() : (object) [],
        ];
        $result = self::jsonResult($payload);
        $result['isError'] = true;
        return $result;
    }

    public static function overviewToSummary(object $overview): array
    {
        return [
            'id' => (int) ($overview->uid ?? 0),
            'from' => self::decodeHeader($overview->from ?? ''),
            'to' => self::decodeHeader($overview->to ?? ''),
            'subject' => self::decodeHeader($overview->subject ?? '') ?: '(geen onderwerp)',
            'date' => isset($overview->date) ? self::toIso8601($overview->date) : null,
            'unread' => empty($overview->seen),
            'size' => isset($overview->size) ? (int) $overview->size : null,
        ];
    }

    public static function decodeHeader(string $value): string
    {
        $decoded = @iconv_mime_decode($value, 0, 'UTF-8');
        return $decoded === false ? $value : $decoded;
    }

    public static function toIso8601(string $imapDate): ?string
    {
        $timestamp = strtotime($imapDate);
        return $timestamp === false ? null : gmdate('c', $timestamp);
    }

    /**
     * Builds an IMAP SEARCH criteria string from named parts, quoting each
     * value safely (IMAP quoted strings escape backslash and double quote).
     */
    public static function buildSearchCriteria(array $parts): string
    {
        $tokens = [];
        foreach ($parts as $keyword => $value) {
            if ($value instanceof \DateTimeImmutable) {
                $tokens[] = $keyword . ' "' . $value->format('d-M-Y') . '"';
            } else {
                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
                $tokens[] = $keyword . ' "' . $escaped . '"';
            }
        }
        return $tokens === [] ? 'ALL' : implode(' ', $tokens);
    }
}
