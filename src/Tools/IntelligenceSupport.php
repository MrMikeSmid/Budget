<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Intelligence\EmailAnalyzer;
use McpEmail\Intelligence\ReputationStore;
use McpEmail\Mail\ImapClient;

/** Shared mailbox orchestration for read-only intelligence tools. */
final class IntelligenceSupport
{
    /** @param array<string,mixed> $args @return array<string,mixed>|null */
    public static function analyzeOne(array $args, bool $record = true): ?array
    {
        $uid = (int) ($args['id'] ?? 0);
        if ($uid <= 0) throw new \InvalidArgumentException('Een geldige numerieke e-mail-id is verplicht.');
        $client = ImapClient::connect(Config::getAccount(isset($args['account']) ? (string) $args['account'] : null), (string) ($args['folder'] ?? 'INBOX'));
        try {
            $message = $client->readMessage($uid);
            if ($message === null) return null;
            $analysis = (new EmailAnalyzer())->analyze($message);
            if ($record) (new ReputationStore())->record($analysis['sender'], $analysis['domain'], $analysis['category']);
            return $analysis;
        } finally { $client->close(); }
    }

    /** @param array<string,mixed> $args @return list<array<string,mixed>> */
    public static function analyzeMany(array $args): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $client = ImapClient::connect(Config::getAccount(isset($args['account']) ? (string) $args['account'] : null), (string) ($args['folder'] ?? 'INBOX'));
        try {
            $uids = array_slice($client->searchUids('ALL'), -$limit);
            rsort($uids); $analyzer = new EmailAnalyzer(); $store = new ReputationStore(); $result = [];
            foreach ($uids as $uid) {
                $message = $client->readMessage($uid); if ($message === null) continue;
                $analysis = $analyzer->analyze($message); $store->record($analysis['sender'], $analysis['domain'], $analysis['category']); $result[] = $analysis;
            }
            return $result;
        } finally { $client->close(); }
    }

    public static function schema(array $properties, array $required = []): array
    {
        $properties += ['folder' => ['type' => 'string', 'description' => 'IMAP-map, standaard INBOX.'],
            'account' => ['type' => 'string', 'description' => 'Optioneel account-id.']];
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }
}
