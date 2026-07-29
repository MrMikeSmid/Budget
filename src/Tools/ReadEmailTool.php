<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;

final class ReadEmailTool implements ToolInterface
{
    public function name(): string
    {
        return 'read_email';
    }

    public function definition(): array
    {
        return [
            'title' => 'Lees volledige e-mail',
            'description' => 'Haalt de volledige inhoud van één e-mail op (tekst/HTML-body, headers, ' .
                'bijlagenamen) op basis van UID. Wordt live via IMAP opgehaald, niets wordt op de server bewaard.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => ['string', 'integer'],
                        'description' => 'De UID van de e-mail (zoals teruggegeven door list_emails/search_emails).',
                    ],
                    'folder' => ['type' => 'string', 'description' => 'IMAP-map waarin de e-mail staat, standaard "INBOX".'],
                    'account' => ['type' => 'string', 'description' => 'Account-id, alleen nodig bij meerdere geconfigureerde accounts.'],
                ],
                'required' => ['id'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function call(array $args): array
    {
        $folder = (string) ($args['folder'] ?? 'INBOX');
        $uid = (int) ($args['id'] ?? 0);

        if ($uid <= 0) {
            return Support::errorResult('Ongeldige e-mail-id: "' . ($args['id'] ?? '') . '". Verwacht wordt een numerieke UID.');
        }

        $client = null;
        try {
            $account = Config::getAccount(isset($args['account']) ? (string) $args['account'] : null);
            $client = ImapClient::connect($account, $folder);

            $message = $client->readMessage($uid);
            if ($message === null) {
                return Support::errorResult("Geen e-mail gevonden met UID $uid in map \"$folder\".");
            }

            $message['from'] = Support::decodeHeader($message['from']);
            $message['to'] = Support::decodeHeader($message['to']);
            $message['folder'] = $folder;

            return Support::jsonResult($message);
        } catch (ImapConnectionException $e) {
            return Support::mailConnectionError($e);
        } catch (Throwable $e) {
            return Support::errorResult('Kon e-mail niet lezen: ' . $e->getMessage());
        } finally {
            $client?->close();
        }
    }
}
