<?php

declare(strict_types=1);

namespace McpEmail\Mail;

use McpEmail\MailAccountConfig;

/**
 * Thin wrapper around PHP's ext-imap. Opens a fresh connection per call and
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
        $flags = $account->imapSecure ? '/imap/ssl' : '/imap';
        $mailboxSpec = '{' . $account->imapHost . ':' . $account->imapPort . $flags . '}' . self::encodeFolder($folder);

        $connection = self::guarded(
            fn () => imap_open($mailboxSpec, $account->imapUser, $account->imapPass, 0, 1),
            "Kan geen verbinding maken met IMAP-server {$account->imapHost}:{$account->imapPort}"
        );

        if ($connection === false) {
            $detail = imap_last_error();
            throw new ImapConnectionException(
                "Kan geen verbinding maken met IMAP-server {$account->imapHost}:{$account->imapPort}" .
                    ($detail ? " ($detail)" : '')
            );
        }

        return new self($connection);
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
