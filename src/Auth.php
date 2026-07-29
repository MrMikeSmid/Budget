<?php

declare(strict_types=1);

namespace McpEmail;

final class Auth
{
    /**
     * Checks the Authorization header against the configured bearer token.
     * Returns null when valid, or an error message to send back when invalid.
     */
    public static function checkBearer(): ?string
    {
        $expected = Config::getBearerToken();
        $provided = self::extractProvidedToken();

        if ($provided === null) {
            return 'Unauthorized: bearer token ontbreekt.';
        }

        if (!hash_equals($expected, $provided)) {
            return 'Unauthorized: ongeldig bearer token.';
        }

        return null;
    }

    /** Reads the bearer token exclusively from the Authorization header. */
    private static function extractProvidedToken(): ?string
    {
        $header = self::getAuthorizationHeader();
        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, strlen('Bearer ')));
        }

        return null;
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
