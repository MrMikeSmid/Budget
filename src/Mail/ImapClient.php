<?php

declare(strict_types=1);

namespace McpEmail\Mail;

use McpEmail\MailAccountConfig;

/**
 * Thin wrapper around PHP's ext-imap for IMAP and POP3. Opens a fresh connection per call and
 * always closes it afterwards - nothing is cached or kept alive between
 * requests, so mail content is never persisted on the server.
 *
 * Note: ext-imap was marked deprecated in PHP 8.4 (still fully functional on
 * 8.1-8.3). If a future PHP upgrade removes it, this class is the only place
 * that would need to be rewritten (e.g. against a raw IMAP socket client).
 */
final class ImapClient
{
    /** @var resource|\IMAP\Connection */
    private $connection;

    private function __construct($connection)
    {
        $this->connection = $connection;
    }

    public static function connect(MailAccountConfig $account, string $folder): self
    {
        if (!extension_loaded('imap')) {
            self::fail('missing_imap_extension', 'De vereiste PHP-extensie imap is niet geladen.', [
                'phase' => 'php_environment', 'runtime' => MailConnectionDiagnostics::runtime(),
            ]);
        }
        if (!extension_loaded('openssl')) {
            self::fail('missing_openssl_extension', 'De vereiste PHP-extensie openssl is niet geladen.', [
                'phase' => 'php_environment', 'runtime' => MailConnectionDiagnostics::runtime(),
            ]);
        }

        // Always compare the three requested incoming-mail connection forms. They only open INBOX;
        // no message is read, changed or deleted during these diagnostic attempts.
        $protocol = $account->mailProtocol;
        $variants = [
            ['flags' => "/$protocol/ssl", 'ssl' => true, 'novalidate' => false],
            ['flags' => "/$protocol/ssl/novalidate-cert", 'ssl' => true, 'novalidate' => true],
            ['flags' => "/$protocol/notls", 'ssl' => false, 'novalidate' => false],
        ];
        $attempts = [];
        $selected = null;
        $selectedFlags = null;

        foreach ($variants as $variant) {
            $socket = MailConnectionDiagnostics::diagnoseMailConnection(
                $account->imapHost, $account->imapPort, $protocol, $variant['ssl'],
                $account->mailConnectIpv4, $variant['novalidate'], $account->mailSocketTimeout,
            );
            $connectHost = $account->mailConnectIpv4
                ? (string) ($socket['ipv4_address'] ?? $account->imapHost) : $account->imapHost;
            $mailbox = '{' . $connectHost . ':' . $account->imapPort . $variant['flags'] . '}INBOX';
            $attempt = [
                'mailbox_string' => $mailbox,
                'host' => $account->imapHost,
                'port' => $account->imapPort,
                'protocol' => $protocol,
                'ssl' => $variant['ssl'],
                'novalidate_cert' => $variant['novalidate'],
                'socket' => $socket,
                'imap_successful' => false,
            ];

            if ($socket['error_type'] === null) {
                // This is intentionally immediately before imap_open; credentials are absent.
                self::logDiagnostics(['event' => 'before_imap_open', ...array_diff_key($attempt, ['socket' => true])]);
                imap_errors();
                $warning = null;
                set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
                    $warning = $message;
                    return true;
                });
                try {
                    $connection = imap_open($mailbox, $account->imapUser, $account->imapPass, 0, 1);
                } finally {
                    restore_error_handler();
                }
                $lastError = imap_last_error() ?: null;
                $errors = imap_errors();
                $attempt['imap_last_error'] = $lastError;
                $attempt['imap_errors'] = is_array($errors) ? array_values($errors) : [];
                $attempt['imap_warning'] = $warning;
                $attempt['openssl_errors'] = MailConnectionDiagnostics::openSslErrors();
                if ($connection !== false) {
                    $attempt['imap_successful'] = true;
                    if ($selected === null) {
                        $selected = $connection;
                        $selectedFlags = $variant['flags'];
                    } else {
                        imap_close($connection);
                    }
                }
            }
            $attempts[] = self::redact($attempt, [$account->imapUser, $account->imapPass]);
        }

        $diagnostics = [
            'phase' => $selected === null ? 'imap_open' : 'complete',
            'runtime' => MailConnectionDiagnostics::runtime(),
            'attempts' => $attempts,
            'selected_mailbox_flags' => $selectedFlags,
        ];
        $diagnostics['most_likely_cause'] = self::mostLikelyCause($attempts);
        self::logDiagnostics(['event' => 'diagnostic_summary', ...$diagnostics]);

        if ($selected === null) {
            $type = self::diagnosticErrorType($attempts);
            self::fail($type, self::messageFor($type), $diagnostics);
        }

        if ($folder !== 'INBOX') {
            imap_close($selected);
            $host = $account->mailConnectIpv4
                ? (string) ($attempts[0]['socket']['ipv4_address'] ?? $account->imapHost) : $account->imapHost;
            $mailbox = '{' . $host . ':' . $account->imapPort . $selectedFlags . '}' . self::encodeFolder($folder);
            self::logDiagnostics(['event' => 'before_imap_open', 'mailbox_string' => $mailbox, 'host' => $account->imapHost,
                'port' => $account->imapPort, 'protocol' => $protocol, 'ssl' => str_contains((string) $selectedFlags, '/ssl'),
                'novalidate_cert' => str_contains((string) $selectedFlags, '/novalidate-cert')]);
            $selected = imap_open($mailbox, $account->imapUser, $account->imapPass, 0, 1);
            if ($selected === false) {
                self::fail('protocol_error', self::messageFor('protocol_error'), $diagnostics);
            }
        }

        return new self($selected);
    }

    /** @param array<int, array<string, mixed>> $attempts */
    private static function diagnosticErrorType(array $attempts): string
    {
        foreach (['dns_error', 'socket_unreachable', 'ssl_handshake_error'] as $type) {
            if (array_filter($attempts, static fn (array $a): bool => ($a['socket']['error_type'] ?? null) === $type)) {
                return $type;
            }
        }
        $errors = [];
        foreach ($attempts as $attempt) {
            $errors = [...$errors, (string) ($attempt['imap_warning'] ?? ''), (string) ($attempt['imap_last_error'] ?? ''), ...($attempt['imap_errors'] ?? [])];
        }
        return self::classifyImapError(implode(' | ', $errors), (string) ($attempts[0]['protocol'] ?? 'imap'));
    }

    /** @param array<int, array<string, mixed>> $attempts */
    private static function mostLikelyCause(array $attempts): string
    {
        if ($attempts === []) {
            return 'PHP/OpenSSL probleem';
        }
        if (($attempts[0]['socket']['error_type'] ?? null) === 'dns_error') {
            return 'DNS-probleem';
        }
        if (($attempts[0]['socket']['error_type'] ?? null) === 'socket_unreachable') {
            return 'firewall';
        }
        if (!empty($attempts[1]['imap_successful']) && empty($attempts[0]['imap_successful'])) {
            $cert = $attempts[1]['socket'];
            if (($cert['certificate_expired'] ?? false) === true) {
                return 'certificaat ongeldig';
            }
            if (($cert['certificate_hostname_matches'] ?? true) === false) {
                return 'certificaat host mismatch';
            }
            return 'certificaat ongeldig';
        }
        $allErrors = strtolower(json_encode($attempts, JSON_UNESCAPED_SLASHES) ?: '');
        if (preg_match('/auth|login|password|credential|access denied/', $allErrors)) {
            return 'authenticatiefout';
        }
        if (preg_match('/wrong version|unsupported protocol|no shared cipher|handshake/', $allErrors)) {
            return 'TLS mismatch';
        }
        if (!empty($attempts[2]['socket']['socket_successful']) && preg_match('/protocol|unexpected|invalid response/', $allErrors)) {
            return strtoupper((string) ($attempts[0]['protocol'] ?? 'mail')) . '-service niet actief';
        }
        if (preg_match('/openssl|crypto/', $allErrors)) {
            return 'PHP/OpenSSL probleem';
        }
        return 'onbekend probleem';
    }

    /** @param array<string, mixed> $diagnostics */
    private static function fail(string $type, string $message, array $diagnostics): never
    {
        self::logDiagnostics(['event' => 'connection_failure', ...$diagnostics]);
        throw new ImapConnectionException($message, $type, $diagnostics);
    }

    /** @param array<string, mixed> $diagnostics Credentials must never be passed to this logger. */
    private static function logDiagnostics(array $diagnostics): void
    {
        error_log('[mail-diagnostic] ' . (json_encode($diagnostics, JSON_UNESCAPED_SLASHES) ?: '{"log_error":true}'));
    }

    private static function classifyImapError(string $error, string $protocol): string
    {
        $value = strtolower($error);
        if (preg_match('/auth|login|credential|password|user(name)?|access denied|invalid account/', $value)) {
            return 'authentication_error';
        }
        if (preg_match('/certificate|tls|ssl|crypto|handshake/', $value)) {
            return 'ssl_handshake_error';
        }
        if (preg_match('/protocol|unexpected|invalid response|not (imap|pop3)|bad command/', $value)) {
            return 'protocol_error';
        }
        // ext-imap often prefixes server responses with the selected protocol.
        if ($error !== '' && str_contains($value, $protocol)) {
            return 'protocol_error';
        }
        return 'unknown_imap_error';
    }

    private static function messageFor(string $type): string
    {
        return match ($type) {
            'dns_error' => 'De hostnaam van de mailserver kan niet via DNS worden gevonden.',
            'socket_unreachable' => 'De mailserver of ingestelde poort is vanaf deze server niet bereikbaar.',
            'ssl_handshake_error' => 'De SSL/TLS-handshake met de mailserver is mislukt.',
            'authentication_error' => 'De mailserver heeft de gebruikersnaam of het wachtwoord geweigerd.',
            'protocol_error' => 'De mailserver gaf een onverwachte POP3/IMAP-protocolreactie.',
            default => 'De mailboxverbinding kon niet worden geopend.',
        };
    }

    /** @param array<string, mixed> $values @param string[] $secrets @return array<string, mixed> */
    private static function redact(array $values, array $secrets): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::redact($value, $secrets);
            } elseif (is_string($value)) {
                foreach ($secrets as $secret) {
                    if ($secret !== '') {
                        $value = str_replace($secret, '[REDACTED]', $value);
                    }
                }
                $values[$key] = $value;
            }
        }
        return $values;
    }

    public function close(): void
    {
        if ($this->connection) {
            @imap_close($this->connection);
        }
    }

    public function messageCount(): int
    {
        return self::guarded(fn () => imap_num_msg($this->connection), 'Kon aantal berichten niet ophalen');
    }

    /**
     * @return int[] UIDs
     */
    public function searchUids(string $criteria): array
    {
        $result = self::guarded(
            fn () => imap_search($this->connection, $criteria, SE_UID),
            'Zoekopdracht op de mailserver is mislukt'
        );

        return $result === false ? [] : array_map('intval', $result);
    }

    /**
     * @param int[] $uids
     * @return array<int, object>
     */
    public function overviewsByUid(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $sequence = implode(',', $uids);
        $result = self::guarded(
            fn () => imap_fetch_overview($this->connection, $sequence, FT_UID),
            'Kon e-mailoverzicht niet ophalen'
        );

        return $result === false ? [] : $result;
    }

    /**
     * @return array<int, object> overview rows for sequence range (msgno-based)
     */
    public function overviewsBySequence(string $sequenceRange): array
    {
        $result = self::guarded(
            fn () => imap_fetch_overview($this->connection, $sequenceRange, 0),
            'Kon e-mailoverzicht niet ophalen'
        );

        return $result === false ? [] : $result;
    }

    public function uidForSequence(int $msgNo): int
    {
        return (int) self::guarded(
            fn () => imap_uid($this->connection, $msgNo),
            'Kon UID niet bepalen'
        );
    }

    /**
     * Fetches full message content by UID and returns a structured array
     * with envelope info, plain text, html and attachment metadata.
     */
    public function readMessage(int $uid): ?array
    {
        $overview = $this->overviewsByUid([$uid]);
        if ($overview === []) {
            return null;
        }
        $meta = $overview[0];

        $structure = self::guarded(
            fn () => imap_fetchstructure($this->connection, $uid, FT_UID),
            'Kon berichtstructuur niet ophalen'
        );

        $textPlain = null;
        $textHtml = null;
        $attachments = [];
        $this->walkStructure($uid, $structure, '1', $textPlain, $textHtml, $attachments);

        return [
            'uid' => $uid,
            'subject' => isset($meta->subject) ? self::decodeMimeHeader($meta->subject) : '(geen onderwerp)',
            'from' => $meta->from ?? '',
            'to' => $meta->to ?? '',
            'date' => isset($meta->date) ? self::toIso8601($meta->date) : null,
            'unread' => empty($meta->seen),
            'text' => $textPlain,
            'html' => $textHtml,
            'attachments' => $attachments,
        ];
    }

    private function walkStructure(
        int $uid,
        object $structure,
        string $section,
        ?string &$textPlain,
        ?string &$textHtml,
        array &$attachments
    ): void {
        if (($structure->type ?? 0) === 1 && !empty($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $this->walkStructure($uid, $part, $section . '.' . ($index + 1), $textPlain, $textHtml, $attachments);
            }
            return;
        }

        $typeNames = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $type = $typeNames[$structure->type ?? 0] ?? 'application';
        $subtype = strtolower($structure->subtype ?? 'plain');
        $mimeType = $type . '/' . $subtype;

        $filename = self::extractFilename($structure);
        $disposition = strtolower($structure->disposition ?? '');

        if ($filename === null && $type === 'text' && ($subtype === 'plain' || $subtype === 'html') && $disposition !== 'attachment') {
            $raw = self::guarded(
                fn () => imap_fetchbody($this->connection, $uid, $section, FT_UID),
                'Kon berichttekst niet ophalen'
            );
            $decoded = self::decodeBody($raw, $structure->encoding ?? 0);
            $decoded = self::convertCharset($decoded, self::extractCharset($structure));

            if ($subtype === 'plain' && $textPlain === null) {
                $textPlain = $decoded;
            } elseif ($subtype === 'html' && $textHtml === null) {
                $textHtml = $decoded;
            }
            return;
        }

        if ($filename !== null || $disposition === 'attachment') {
            $attachments[] = [
                'filename' => $filename ?? '(zonder naam)',
                'contentType' => $mimeType,
                'size' => (int) ($structure->bytes ?? 0),
            ];
        }
    }

    private static function extractFilename(object $structure): ?string
    {
        foreach (['dparameters', 'parameters'] as $prop) {
            if (empty($structure->$prop)) {
                continue;
            }
            foreach ($structure->$prop as $param) {
                if (in_array(strtolower($param->attribute ?? ''), ['filename', 'name'], true)) {
                    return self::decodeMimeHeader($param->value);
                }
            }
        }
        return null;
    }

    private static function extractCharset(object $structure): ?string
    {
        if (empty($structure->parameters)) {
            return null;
        }
        foreach ($structure->parameters as $param) {
            if (strtolower($param->attribute ?? '') === 'charset') {
                return $param->value;
            }
        }
        return null;
    }

    private static function decodeBody(string $raw, int $encoding): string
    {
        return match ($encoding) {
            3 => (string) imap_base64($raw),
            4 => (string) imap_qprint($raw),
            default => $raw,
        };
    }

    private static function convertCharset(string $data, ?string $charset): string
    {
        if ($charset === null || strcasecmp($charset, 'UTF-8') === 0) {
            return $data;
        }

        $result = @mb_convert_encoding($data, 'UTF-8', $charset);
        return $result === false ? $data : $result;
    }

    private static function decodeMimeHeader(string $value): string
    {
        $decoded = @iconv_mime_decode($value, 0, 'UTF-8');
        return $decoded === false ? $value : $decoded;
    }

    private static function toIso8601(string $imapDate): ?string
    {
        $timestamp = strtotime($imapDate);
        return $timestamp === false ? null : gmdate('c', $timestamp);
    }

    private static function encodeFolder(string $folder): string
    {
        $converted = @mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
        return $converted === false ? $folder : $converted;
    }

    /**
     * Runs an ext-imap call while converting any PHP warning/notice it
     * raises into a catchable exception, so connection problems produce a
     * clean tool error instead of surfacing a raw PHP warning or crashing.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private static function guarded(callable $fn, string $context)
    {
        $previousHandler = set_error_handler(static function (int $severity, string $message) use ($context): bool {
            throw new ImapConnectionException("$context: $message");
        });

        try {
            return $fn();
        } finally {
            set_error_handler($previousHandler);
        }
    }
}
