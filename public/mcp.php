<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use McpEmail\Auth;
use McpEmail\ConfigException;
use McpEmail\McpServer;

header('Content-Type: application/json');

function respond_error(?int $httpStatus, $id, int $code, string $message): never
{
    if ($httpStatus !== null) {
        http_response_code($httpStatus);
    }
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => ['code' => $code, 'message' => $message],
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// This server runs stateless (no Mcp-Session-Id is ever issued), so there is
// nothing to resume via GET and nothing to tear down via DELETE.
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method Not Allowed: alleen POST wordt ondersteund op deze stateless MCP-server.']);
    exit;
}

try {
    $authError = Auth::checkBearer();
} catch (ConfigException $e) {
    respond_error(500, null, -32603, 'Server-configuratiefout: ' . $e->getMessage());
}

if ($authError !== null) {
    respond_error(401, null, -32001, $authError);
}

$raw = file_get_contents('php://input');
$request = json_decode($raw ?: '', true);

if (!is_array($request) || array_is_list($request)) {
    respond_error(400, null, -32700, 'Parse error: verwacht wordt één JSON-RPC-object (batches worden niet ondersteund).');
}

try {
    $response = (new McpServer())->handle($request);
} catch (\Throwable $e) {
    respond_error(500, $request['id'] ?? null, -32603, 'Interne serverfout: ' . $e->getMessage());
}

if ($response === null) {
    // Notification: no JSON-RPC response body, per spec.
    http_response_code(202);
    exit;
}

echo json_encode($response);
