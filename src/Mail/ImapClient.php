<?php

declare(strict_types=1);

namespace McpEmail\Mail;

use McpEmail\Config;
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
        $startedAt = hrtime(true);
        $debug = Config::debugMail();
        $timeout = min(5.0, $account->mailSocketTimeout);
        $timings = [];

        self::logLifecycle('begin', 0, 'php_environment');
        ini_set('default_socket_timeout', '5');
        if (function_exists('imap_timeout')) {
            imap_timeout(IMAP_OPENTIMEOUT, 5);
            imap_timeout(IMAP_READTIMEOUT, 5);
            imap_timeout(IMAP_WRITETIMEOUT, 5);
            imap_timeout(IMAP_CLOSETIMEOUT, 5);
        }

        if (!extension_loaded('imap')) {
            self::fail('missing_imap_extension', 'De vereiste PHP-extensie imap is niet geladen.', [
                'phase' => 'php_environment', 'duration_ms' => self::elapsedMs($startedAt),
                'runtime' => $debug ? MailConnectionDiagnostics::runtime() : [],
            ]);
        }
        if (!extension_loaded('openssl')) {
            self::fail('missing_openssl_extension', 'De vereiste PHP-extensie openssl is niet geladen.', [
                'phase' => 'php_environment', 'duration_ms' => self::elapsedMs($startedAt),
                'runtime' => $debug ? MailConnectionDiagnostics::runtime() : [],
            ]);
        }

        $protocol = $account->mailProtocol;
        $flags = "/$protocol/" . ($account->imapSecure ? 'ssl' : 'notls');
        // Match a mail client configured to accept every certificate. ext-imap
        // only exposes this behaviour through the mailbox flag.
        if ($account->imapSecure) {
            $flags .= '/novalidate-cert';
        }
        $connectHost = $account->imapHost;
        $socket = null;

        // Expensive DNS, TLS and certificate inspection is opt-in. If its single socket
        // attempt fails, return immediately: never follow it with imap_open or fallbacks.
        if ($debug) {
            $socket = MailConnectionDiagnostics::diagnoseMailConnection(
                $account->imapHost,
                $account->imapPort,
                $protocol,
                $account->imapSecure,
                $account->mailConnectIpv4,
                true,
                $timeout,
            );
            $timings = $socket['timings'] ?? [];
            if ($account->mailConnectIpv4) {
                $connectHost = (string) ($socket['ipv4_address'] ?? $account->imapHost);
            }
            if (($socket['error_type'] ?? null) !== null) {
                $phase = ($socket['error_type'] ?? null) === 'timeout'
                    ? (($socket['phase'] ?? '') === 'tls' ? 'ssl' : 'socket')
                    : (string) ($socket['phase'] ?? 'socket');
                $diagnostics = [
                    'phase' => $phase,
                    'duration_ms' => self::elapsedMs($startedAt),
                    'timings' => $timings,
                    'socket' => $socket,
                ];
                $type = (string) $socket['error_type'];
                self::fail($type, self::messageFor($type), $diagnostics);
            }
        }

        $elapsed = self::elapsedMs($startedAt);
        $remaining = $timeout - ($elapsed / 1000);
        // ext-imap only accepts whole seconds. Do not start it when less than one
        // second remains, because rounding up would exceed the shared 5s budget.
        if ($remaining < 1.0) {
            self::fail('timeout', self::messageFor('timeout'), [
                'phase' => 'imap', 'duration_ms' => $elapsed, 'timings' => $timings, 'socket' => $socket,
            ]);
        }
        $remainingSeconds = max(1, min(5, (int) floor($remaining)));
        if (function_exists('imap_timeout')) {
            imap_timeout(IMAP_OPENTIMEOUT, $remainingSeconds);
            imap_timeout(IMAP_READTIMEOUT, $remainingSeconds);
        }

        $mailbox = '{' . $connectHost . ':' . $account->imapPort . $flags . '}' . self::encodeFolder($folder);
        self::logConnectionDetails($account->imapHost, $account->imapPort, $mailbox, $protocol);
        $imapStartedAt = hrtime(true);
        imap_errors();
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });
        try {
            // Exactly one mailbox string and one imap_open attempt per request.
            $connection = imap_open($mailbox, $account->imapUser, $account->imapPass, 0, 1);
        } finally {
            restore_error_handler();
        }
        $timings['imap_open_ms'] = self::elapsedMs($imapStartedAt);
        $durationMs = self::elapsedMs($startedAt);

        if ($connection === false) {
            $lastError = imap_last_error() ?: '';
            $errors = imap_errors();
            $errorText = implode(' | ', array_filter([
                $warning ?? '', $lastError, ...(is_array($errors) ? $errors : []),
            ]));
            $type = self::classifyImapError($errorText, $protocol);
            if ($durationMs >= (int) ($timeout * 1000 * 0.9) || preg_match('/timed?\\s*out|timeout/i', $errorText)) {
                $type = 'timeout';
            }
            $phase = $type === 'ssl_handshake_error' ? 'ssl' : 'imap';
            $diagnostics = ['phase' => $phase, 'duration_ms' => $durationMs, 'timings' => $timings];
            if ($debug) {
                $diagnostics['imap_warning'] = $warning;
                $diagnostics['imap_last_error'] = $lastError;
                $diagnostics['imap_errors'] = is_array($errors) ? array_values($errors) : [];
                $diagnostics['socket'] = $socket;
                $diagnostics['runtime'] = MailConnectionDiagnostics::runtime();
            }
            self::fail($type, self::messageFor($type), self::redact($diagnostics, [$account->imapUser, $account->imapPass]));
        }

        self::logLifecycle('end', $durationMs, 'complete');
        return new self($connection);
    }

    private static function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private static function logLifecycle(string $event, int $durationMs, string $phase): void
    {
        error_log(sprintf('[mail-connection] %s duration_ms=%d stop_phase=%s', $event, $durationMs, $phase));
    }

    private static function logConnectionDetails(string $host, int $port, string $mailbox, string $protocol): void
    {
        $directory = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return;
        }
        $line = sprintf(
            "[%s] host=%s port=%d mailbox=%s protocol=%s novalidate_cert=%s\n",
            date(DATE_ATOM),
            str_replace(["\r", "\n"], '', $host),
            $port,
            str_replace(["\r", "\n"], '', $mailbox),
            str_replace(["\r", "\n"], '', strtolower($protocol)),
            str_contains($mailbox, '/novalidate-cert') ? 'yes' : 'no',
        );
        @file_put_contents($directory . '/mcp_debug.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $diagnostics */
    private static function fail(string $type, string $message, array $diagnostics): never
    {
        self::logLifecycle('end', (int) ($diagnostics['duration_ms'] ?? 0), (string) ($diagnostics['phase'] ?? 'unknown'));
        if (Config::debugMail()) {
            self::logDiagnostics(['event' => 'connection_failure', ...$diagnostics]);
        }
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
            'timeout' => 'Connection timed out',
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
            'headers' => $this->headers($uid),
        ];
    }

    /** Returns unfolded raw message headers without changing message flags. */
    private function headers(int $uid): string
    {
        $headers = self::guarded(
            fn () => imap_fetchheader($this->connection, $uid, FT_UID | FT_PEEK),
            'Kon berichtheaders niet ophalen'
        );
        return $headers === false ? '' : preg_replace("/\r?\n[ \t]+/", ' ', $headers) ?? $headers;
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
