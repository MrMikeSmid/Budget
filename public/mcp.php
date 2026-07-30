<?php

declare(strict_types=1);

// Capture output from dependencies and accidental debugging. Only emitJsonRpc()
// is allowed to write the API response.
ob_start();

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('default_socket_timeout', '5');
set_time_limit(10);

$requestStartedAt = hrtime(true);
$requestStopPhase = 'bootstrap';
error_log('[mcp-request] begin');

header('Content-Type: application/json; charset=utf-8');

/** @var int|string|null $requestId */
$requestId = null;
$responseSent = false;

/**
 * Discards and safely records accidental output without putting its possibly
 * sensitive contents (credentials, headers, or debug dumps) in a log.
 */
function discardUnexpectedOutput(): void
{
    while (ob_get_level() > 0) {
        if (ob_get_length() > 0) {
            $unexpectedOutput = ob_get_clean();
            if (is_string($unexpectedOutput) && trim($unexpectedOutput) !== '') {
                error_log(sprintf(
                    'Unexpected MCP output suppressed (bytes=%d, sha256=%s)',
                    strlen($unexpectedOutput),
                    hash('sha256', $unexpectedOutput),
                ));
            }
        } else {
            ob_end_clean();
        }
    }
}

/** @param array<string, mixed> $response */
function emitJsonRpc(array $response, int $httpStatus = 200): never
{
    global $responseSent;

    if ($responseSent) {
        exit;
    }
    $responseSent = true;

    discardUnexpectedOutput();
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');

    try {
        $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $json = '{"jsonrpc":"2.0","id":null,"error":{"code":-32603,"message":"Interne serverfout."}}';
    }

    if (strlen($json) > 5 * 1024 * 1024) {
        $json = '{"jsonrpc":"2.0","id":null,"error":{"code":-32603,"message":"MESSAGE_TOO_LARGE: response overschrijdt 5 MiB."}}';
        http_response_code(413);
    }

    echo $json;
    exit;
}

function emitAuthError(string $error): never
{
    emitJsonRpc(['success' => false, 'error' => $error], 401);
}

/** @param int|string|null $id */
function emitError($id, int $code, string $message, int $httpStatus = 200, ?array $data = null): never
{
    $error = ['code' => $code, 'message' => $message];
    if ($data !== null) {
        $error['data'] = $data;
    }
    emitJsonRpc(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error], $httpStatus);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use (&$requestId): void {
    // Do not log the exception message: third-party messages can contain hosts,
    // usernames, passwords, Authorization headers, or mailbox credentials.
    error_log(sprintf(
        'Unhandled MCP exception: class=%s file=%s line=%d',
        get_class($exception),
        basename($exception->getFile()),
        $exception->getLine(),
    ));
    emitError($requestId, -32603, 'Interne serverfout.', 500);
});

register_shutdown_function(static function () use (&$requestId, &$responseSent, &$requestStartedAt, &$requestStopPhase): void {
    $durationMs = (int) round((hrtime(true) - $requestStartedAt) / 1_000_000);
    error_log(sprintf('[mcp-request] end duration_ms=%d stop_phase=%s', $durationMs, $requestStopPhase));
    if ($responseSent) {
        return;
    }
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
        error_log(sprintf(
            'Fatal MCP error suppressed: type=%d file=%s line=%d',
            $error['type'],
            basename($error['file']),
            $error['line'],
        ));
        emitError($requestId, -32603, 'Interne serverfout.', 500);
    }
});

require __DIR__ . '/../vendor/autoload.php';

use McpEmail\Auth;
use McpEmail\ConfigException;
use McpEmail\McpServer;
use McpEmail\RateLimiter;

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestStopPhase = 'request_validation';

// This server is stateless: GET and DELETE cannot resume or close a session.
if ($httpMethod !== 'POST') {
    header('Allow: POST');
    emitError(null, -32600, 'Invalid Request: alleen POST wordt ondersteund.', 405);
}

try {
    $authError = Auth::checkBearer();
} catch (ConfigException) {
    emitError(null, -32603, 'Server-configuratiefout.', 500);
}

if ($authError !== null) {
    emitAuthError($authError === 'missing_token' ? 'AUTH_REQUIRED' : 'AUTH_INVALID');
}

$clientIdentity=(string)($_SERVER['REMOTE_ADDR']??'unknown');
if(!RateLimiter::allow($clientIdentity,60,60)){emitJsonRpc(['success'=>false,'error'=>['code'=>'RATE_LIMITED','message'=>'Te veel aanvragen; probeer het later opnieuw.']],429);}

$raw = file_get_contents('php://input');
if(is_string($raw)&&strlen($raw)>1048576){emitError(null,-32600,'INVALID_ARGUMENT: request is te groot.',413);}
try {
    $request = json_decode($raw ?: '', true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    emitError(null, -32700, 'Parse error: ongeldige JSON.', 400);
}

if (!is_array($request) || array_is_list($request)) {
    emitError(null, -32600, 'Invalid Request: verwacht wordt één JSON-RPC-object (batches worden niet ondersteund).', 400);
}

$candidateId = $request['id'] ?? null;
if (is_int($candidateId) || is_string($candidateId) || $candidateId === null) {
    $requestId = $candidateId;
}

if (($request['jsonrpc'] ?? null) !== '2.0') {
    emitError($requestId, -32600, 'Invalid Request: "jsonrpc" moet "2.0" zijn.', 400);
}

$requestStopPhase = 'dispatch';
$response = (new McpServer())->handle($request);
$requestStopPhase = 'complete';
if ($response === null) {
    // JSON-RPC notifications intentionally have no response body.
    discardUnexpectedOutput();
    $responseSent = true;
    http_response_code(202);
    exit;
}

emitJsonRpc($response);
