<?php

declare(strict_types=1);

namespace McpEmail;

final class Auth
{
    /** Returns null when valid, or a stable API error code when invalid. */
    public static function checkBearer(): ?string
    {
        $expected = Config::getBearerToken();
        [$provided, $method] = self::getAuthToken();

        if ($provided === null) {
            return 'missing_token';
        }

        self::debugLog($method, $provided);

        if (!hash_equals($expected, $provided)) {
            return 'invalid_token';
        }

        return null;
    }

    /** @return array{?string, ?string} Token and authentication method. */
    private static function getAuthToken(): array
    {
        $header = self::getAuthorizationHeader();
        if ($header !== null) {
            $token = preg_match('/^Bearer[ \t]+([^\s]+)$/i', trim($header), $match) ? $match[1] : '';

            return [$token !== '' ? $token : null, 'authorization'];
        }

        // Compatibility for GPT connectors that can only put credentials in
        // the endpoint URL. The header always takes precedence when present.
        $queryToken = $_GET['token'] ?? null;
        if (is_string($queryToken) && $queryToken !== '') {
            return [$queryToken, 'query'];
        }

        return [null, null];
    }

    private static function debugLog(?string $method, string $token): void
    {
        if (!Config::debug()) {
            return;
        }

        error_log('Auth method: ' . ($method === 'query' ? 'URL query parameter' : 'Authorization header') . '; token was not logged');
    }

    private static function getAuthorizationHeader(): ?string
    {
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return $value;
                }
            }
        }

        // Fallback for SAPIs/hosts that strip the Authorization header from
        // getallheaders() but still expose it via CGI-style variables.
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
    }
}
