<?php

declare(strict_types=1);

namespace McpEmail\Tools;

use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;

final class FlagEmailTool implements ToolInterface
{
    public function __construct(private string $toolName, private string $argument, private string $flag) {}

    public function name(): string { return $this->toolName; }

    public function definition(): array
    {
        return [
            'title' => 'Wijzig e-mailvlag',
            'description' => "Wijzigt de IMAP {$this->flag}-vlag van een e-mail op basis van UID.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => ['string', 'integer'], 'description' => 'UID van de e-mail.'],
                    $this->argument => ['type' => 'boolean', 'description' => 'Nieuwe status.'],
                    'folder' => ['type' => 'string', 'description' => 'IMAP-map, standaard INBOX.'],
                    'account' => ['type' => 'string', 'description' => 'Optioneel account-id.'],
                ],
                'required' => ['id', $this->argument],
                'additionalProperties' => false,
            ],
        ];
    }

    public function call(array $args): array
    {
        $client = null;
        try {
            $uid = MailMutationSupport::uid($args);
            if (!array_key_exists($this->argument, $args) || !is_bool($args[$this->argument])) {
                throw new \InvalidArgumentException("'{$this->argument}' moet een boolean zijn.");
            }
            $folder = (string) ($args['folder'] ?? 'INBOX');
            $account = Config::getAccount(isset($args['account']) ? (string) $args['account'] : null);
            $client = ImapClient::connect($account, $folder);
            MailMutationSupport::requireMessage($client, $uid, $folder);
            $client->setFlag($uid, $this->flag, $args[$this->argument]);
            return Support::jsonResult(['success' => true, 'uid' => $uid, $this->argument => $args[$this->argument]]);
        } catch (ImapConnectionException $e) {
            return Support::mailConnectionError($e);
        } catch (Throwable $e) {
            return Support::errorResult('Kon e-mailstatus niet wijzigen: ' . $e->getMessage());
        } finally { $client?->close(); }
    }
}
