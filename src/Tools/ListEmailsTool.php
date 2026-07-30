<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;

final class ListEmailsTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_emails';
    }

    public function definition(): array
    {
        return [
            'title' => 'Lijst recente e-mails',
            'description' => 'Haalt recente e-mails op uit een map (standaard INBOX), met basismetadata: ' .
                'afzender, ontvanger, onderwerp, datum en gelezen/ongelezen status. Inhoud wordt live via ' .
                'IMAP opgehaald, er wordt niets opgeslagen.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'folder' => ['type' => 'string', 'description' => 'IMAP-map om te doorzoeken, standaard "INBOX".'],
                    'limit' => [
                        'type' => 'integer', 'minimum' => 1, 'maximum' => 100,
                        'description' => 'Maximum aantal e-mails, standaard 20, max 100.',
                    ],
                    'unseenOnly' => ['type' => 'boolean', 'description' => 'Alleen ongelezen e-mails teruggeven.'],
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
        $unseenOnly = (bool) ($args['unseenOnly'] ?? false);

        $client = null;
        try {
            $account = Config::getAccount(isset($args['account']) ? (string) $args['account'] : null);
            $client = ImapClient::connect($account, $folder);

            $total = $client->messageCount();
            if ($total === 0) {
                return Support::jsonResult(['folder' => $folder, 'total' => 0, 'emails' => []]);
            }

            if ($unseenOnly) {
                $uids = $client->searchUids('UNSEEN');
                $uids = array_slice($uids, -$limit);
                $overviews = $client->overviewsByUid($uids);
            } else {
                $start = max(1, $total - $limit + 1);
                $overviews = $client->overviewsBySequence("$start:$total");
            }

            $summaries = array_map([Support::class, 'overviewToSummary'], $overviews);
            usort($summaries, static fn ($a, $b) => $b['id'] <=> $a['id']);

            return Support::jsonResult(['folder' => $folder, 'total' => $total, 'emails' => $summaries]);
        } catch (ImapConnectionException $e) {
            return Support::mailConnectionError($e);
        } catch (Throwable $e) {
            return Support::errorResult('Kon e-mails niet ophalen: ' . $e->getMessage());
        } finally {
            $client?->close();
        }
    }
}
