<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;

final class SearchEmailsTool implements ToolInterface
{
    public function name(): string
    {
        return 'search_emails';
    }

    public function definition(): array
    {
        return [
            'title' => 'Zoek e-mails',
            'description' => 'Zoekt e-mails op afzender, onderwerp, inhoud en/of datumrange binnen een map. ' .
                'Minstens één zoekcriterium moet worden opgegeven.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'from' => ['type' => 'string', 'description' => 'Zoek op (deel van) het afzender-adres of de naam.'],
                    'subject' => ['type' => 'string', 'description' => 'Zoek op (deel van) het onderwerp.'],
                    'text' => ['type' => 'string', 'description' => 'Zoek op tekst in de inhoud van de e-mail.'],
                    'since' => ['type' => 'string', 'description' => 'Alleen e-mails vanaf deze datum (ISO 8601, bv. 2026-01-01).'],
                    'before' => ['type' => 'string', 'description' => 'Alleen e-mails tot deze datum (ISO 8601, bv. 2026-02-01).'],
                    'folder' => ['type' => 'string', 'description' => 'IMAP-map om te doorzoeken, standaard "INBOX".'],
                    'limit' => [
                        'type' => 'integer', 'minimum' => 1, 'maximum' => 100,
                        'description' => 'Maximum aantal resultaten, standaard 20, max 100.',
                    ],
                    'account' => ['type' => 'string', 'description' => 'Account-id, alleen nodig bij meerdere geconfigureerde accounts.'],
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    public function call(array $args): array
    {
        $folder = (string) ($args['folder'] ?? 'INBOX');
        $limit = max(1, min(100, (int) ($args['limit'] ?? 20)));

        if (empty($args['from']) && empty($args['subject']) && empty($args['text']) && empty($args['since']) && empty($args['before'])) {
            return Support::errorResult('Geef minstens één zoekcriterium op: from, subject, text, since en/of before.');
        }

        $criteriaParts = [];
        if (!empty($args['from'])) {
            $criteriaParts['FROM'] = (string) $args['from'];
        }
        if (!empty($args['subject'])) {
            $criteriaParts['SUBJECT'] = (string) $args['subject'];
        }
        if (!empty($args['text'])) {
            $criteriaParts['TEXT'] = (string) $args['text'];
        }

        if (!empty($args['since'])) {
            $since = self::parseDate((string) $args['since']);
            if ($since === null) {
                return Support::errorResult('Ongeldige datum voor "since": "' . $args['since'] . '".');
            }
            $criteriaParts['SINCE'] = $since;
        }

        if (!empty($args['before'])) {
            $before = self::parseDate((string) $args['before']);
            if ($before === null) {
                return Support::errorResult('Ongeldige datum voor "before": "' . $args['before'] . '".');
            }
            $criteriaParts['BEFORE'] = $before;
        }

        $criteria = Support::buildSearchCriteria($criteriaParts);

        $client = null;
        try {
            $account = Config::getAccount(isset($args['account']) ? (string) $args['account'] : null);
            $client = ImapClient::connect($account, $folder);

            $uids = $client->searchUids($criteria);
            $uids = array_slice($uids, -$limit);

            $overviews = $client->overviewsByUid($uids);
            $summaries = array_map([Support::class, 'overviewToSummary'], $overviews);
            usort($summaries, static fn ($a, $b) => $b['id'] <=> $a['id']);

            return Support::jsonResult([
                'folder' => $folder,
                'criteria' => array_intersect_key($args, array_flip(['from', 'subject', 'text', 'since', 'before'])),
                'emails' => $summaries,
            ]);
        } catch (ImapConnectionException $e) {
            return Support::mailConnectionError($e);
        } catch (Throwable $e) {
            return Support::errorResult('Zoeken naar e-mails is mislukt: ' . $e->getMessage());
        } finally {
            $client?->close();
        }
    }

    private static function parseDate(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
