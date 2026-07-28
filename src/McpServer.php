<?php

declare(strict_types=1);

namespace McpEmail;

use McpEmail\Tools\ListEmailsTool;
use McpEmail\Tools\ReadEmailTool;
use McpEmail\Tools\SearchEmailsTool;
use McpEmail\Tools\SendEmailTool;
use McpEmail\Tools\ToolInterface;
use Throwable;

/**
 * Stateless JSON-RPC dispatcher for the MCP "Streamable HTTP" transport.
 *
 * Each HTTP request is handled independently: no session state is kept
 * between calls (the server never issues an Mcp-Session-Id), which is a
 * spec-compliant "stateless" mode and matches how a plain PHP script
 * naturally works on shared hosting (no persistent process required).
 */
final class McpServer
{
    private const PROTOCOL_VERSION = '2025-06-18';
    private const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26'];

    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function __construct()
    {
        foreach ([new ListEmailsTool(), new ReadEmailTool(), new SearchEmailsTool(), new SendEmailTool()] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Handles one decoded JSON-RPC message. Returns the response array to
     * encode as JSON, or null when the message was a notification (no
     * response body should be sent, just HTTP 202/204).
     *
     * @param array<string, mixed> $request
     */
    public function handle(array $request): ?array
    {
        $id = $request['id'] ?? null;
        $isNotification = !array_key_exists('id', $request);
        $method = $request['method'] ?? null;
        $params = is_array($request['params'] ?? null) ? $request['params'] : [];

        if (!is_string($method)) {
            return $isNotification ? null : $this->error($id, -32600, 'Invalid Request: "method" ontbreekt.');
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'notifications/initialized', 'notifications/cancelled' => null,
                'ping' => (object) [],
                'tools/list' => $this->toolsList(),
                'tools/call' => $this->toolsCall($params),
                default => throw new McpMethodNotFoundException($method),
            };
        } catch (McpMethodNotFoundException $e) {
            return $isNotification ? null : $this->error($id, -32601, "Method not found: {$e->getMessage()}");
        } catch (McpInvalidParamsException $e) {
            return $isNotification ? null : $this->error($id, -32602, $e->getMessage());
        } catch (Throwable $e) {
            return $isNotification ? null : $this->error($id, -32603, 'Interne serverfout: ' . $e->getMessage());
        }

        if ($isNotification) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /** @param array<string, mixed> $params */
    private function initialize(array $params): object
    {
        $requested = $params['protocolVersion'] ?? null;
        $version = in_array($requested, self::SUPPORTED_VERSIONS, true) ? $requested : self::PROTOCOL_VERSION;

        return (object) [
            'protocolVersion' => $version,
            'capabilities' => (object) [
                'tools' => (object) ['listChanged' => false],
            ],
            'serverInfo' => (object) [
                'name' => 'mcp-email-connector-php',
                'version' => '1.0.0',
            ],
        ];
    }

    private function toolsList(): object
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $definition = $tool->definition();
            $tools[] = (object) [
                'name' => $tool->name(),
                'title' => $definition['title'],
                'description' => $definition['description'],
                'inputSchema' => $definition['inputSchema'],
            ];
        }
        return (object) ['tools' => $tools];
    }

    /** @param array<string, mixed> $params */
    private function toolsCall(array $params): array
    {
        $name = $params['name'] ?? null;
        if (!is_string($name) || !isset($this->tools[$name])) {
            throw new McpInvalidParamsException('Onbekende tool: "' . (is_string($name) ? $name : '') . '".');
        }

        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        return $this->tools[$name]->call($arguments);
    }

    /** @param int|string|null $id */
    private function error($id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }
}
