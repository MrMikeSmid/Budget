<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Mail\ImapClient;

final class MailMutationSupport
{
    public static function uid(array $args): int
    {
        $uid = (int) ($args['id'] ?? 0);
        if ($uid <= 0) {
            throw new \InvalidArgumentException('Ongeldige e-mail-id; verwacht wordt een positieve numerieke UID.');
        }
        return $uid;
    }

    public static function requireMessage(ImapClient $client, int $uid, string $folder): array
    {
        $message = $client->readMessage($uid);
        if ($message === null) {
            throw new \InvalidArgumentException("Geen e-mail gevonden met UID $uid in map \"$folder\".");
        }
        return $message;
    }
}
