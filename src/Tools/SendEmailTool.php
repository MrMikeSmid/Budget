<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Mail\SmtpClient;
use Throwable;

final class SendEmailTool implements ToolInterface
{
    public function name(): string
    {
        return 'send_email';
    }

    public function definition(): array
    {
        $addressList = [
            'anyOf' => [
                ['type' => 'string'],
                ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        return [
            'title' => 'Verstuur e-mail',
            'description' => "Verstuurt een nieuwe e-mail via SMTP. Vereist minstens één van 'body' " .
                "(platte tekst) of 'html'.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'to' => $addressList + ['description' => 'Ontvanger(s), als e-mailadres of lijst van e-mailadressen.'],
                    'subject' => ['type' => 'string', 'description' => 'Onderwerp van de e-mail.'],
                    'body' => ['type' => 'string', 'description' => 'Platte-tekst inhoud van de e-mail.'],
                    'html' => ['type' => 'string', 'description' => 'HTML-inhoud van de e-mail (optioneel, naast of in plaats van body).'],
                    'cc' => $addressList + ['description' => 'CC-ontvanger(s).'],
                    'bcc' => $addressList + ['description' => 'BCC-ontvanger(s).'],
                    'account' => ['type' => 'string', 'description' => 'Account-id, alleen nodig bij meerdere geconfigureerde accounts.'],
                ],
                'required' => ['to', 'subject'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function call(array $args): array
    {
        if (empty($args['body']) && empty($args['html'])) {
            return Support::errorResult("Geef minstens 'body' (platte tekst) of 'html' op als inhoud van de e-mail.");
        }
        if (empty($args['to']) || empty($args['subject'])) {
            return Support::errorResult("'to' en 'subject' zijn verplicht.");
        }

        try {
            $account = Config::getAccount(isset($args['account']) ? (string) $args['account'] : null);

            $messageId = SmtpClient::send(
                $account,
                $args['to'],
                (string) $args['subject'],
                isset($args['body']) ? (string) $args['body'] : null,
                isset($args['html']) ? (string) $args['html'] : null,
                $args['cc'] ?? null,
                $args['bcc'] ?? null,
            );

            return Support::jsonResult(['sent' => true, 'messageId' => $messageId]);
        } catch (Throwable $e) {
            return Support::errorResult('Versturen van e-mail is mislukt: ' . $e->getMessage());
        }
    }
}
