<?php

declare(strict_types=1);

namespace McpEmail\Mail;

/** DNS, TCP, TLS and certificate diagnostics which never receive credentials. */
final class MailConnectionDiagnostics
{
    /** @return array<string, mixed> */
    public static function runtime(): array
    {
        $curl = extension_loaded('curl') ? curl_version() : null;

        return [
            'php_version' => PHP_VERSION,
            'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
            'imap_extension_loaded' => extension_loaded('imap'),
            'openssl_extension_loaded' => extension_loaded('openssl'),
            'curl_version' => is_array($curl) ? ($curl['version'] ?? null) : null,
            'curl_ssl_version' => is_array($curl) ? ($curl['ssl_version'] ?? null) : null,
            'stream_wrappers' => stream_get_wrappers(),
            'openssl_cafile' => ini_get('openssl.cafile') ?: null,
            'openssl_capath' => ini_get('openssl.capath') ?: null,
        ];
    }

    /** @return string[] Drains the process OpenSSL error queue after an SSL operation. */
    public static function openSslErrors(): array
    {
        return self::drainOpenSslErrors();
    }

    /** @return array<string, mixed> */
    public static function diagnoseMailConnection(
        string $host,
        int $port,
        string $protocol,
        bool $ssl,
        bool $connectIpv4 = false,
        bool $noValidateCert = false,
        float $timeout = 5.0,
    ): array {
        $startedAt = hrtime(true);
        $timings = [];
        $dnsStartedAt = hrtime(true);
        self::drainOpenSslErrors();
        $ipv4 = self::records($host, DNS_A, 'ip');
        $ipv6 = self::records($host, DNS_AAAA, 'ipv6');
        $timings['dns_lookup_ms'] = self::elapsedMs($dnsStartedAt);
        $target = $connectIpv4 ? ($ipv4[0] ?? null) : $host;
        $result = [
            'phase' => 'dns',
            'host' => $host,
            'port' => $port,
            'protocol' => strtolower($protocol),
            'ssl' => $ssl,
            'novalidate_cert' => true,
            'dns_result' => ($ipv4 !== [] || $ipv6 !== []) ? 'resolved' : 'unresolved',
            'ipv4_addresses' => $ipv4,
            'ipv4_address' => $ipv4[0] ?? null,
            'ipv6_addresses' => $ipv6,
            'ipv6_address' => $ipv6[0] ?? null,
            'connection_target' => $target,
            'socket_successful' => false,
            'errno' => 0,
            'errstr' => '',
            'tls_handshake' => $ssl ? false : null,
            'tls_version' => null,
            'cipher' => null,
            'certificate_subject' => null,
            'certificate_issuer' => null,
            'certificate_san' => [],
            'certificate_expired' => null,
            'certificate_hostname_matches' => null,
            'certificate_ca_valid' => null,
            'certificate_chain_complete' => null,
            'certificate_chain_length' => 0,
            'openssl_errors' => [],
            'error_type' => null,
            'timings' => &$timings,
        ];

        if ($target === null || ($ipv4 === [] && $ipv6 === [])) {
            $result['error_type'] = 'dns_error';
            $result['errstr'] = $connectIpv4
                ? 'Geen IPv4-adres gevonden voor de geforceerde IPv4-verbinding.'
                : 'Geen A- of AAAA-record gevonden.';
            $result['duration_ms'] = self::elapsedMs($startedAt);
            return $result;
        }

        $uri = filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? "tcp://[$target]:$port" : "tcp://$target:$port";
        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
        ]]);
        $errno = 0;
        $errstr = '';
        $socketStartedAt = hrtime(true);
        $socket = @stream_socket_client($uri, $errno, $errstr, min(5.0, $timeout), STREAM_CLIENT_CONNECT, $context);
        $timings['socket_connect_ms'] = self::elapsedMs($socketStartedAt);
        $result['phase'] = 'socket';
        $result['errno'] = $errno;
        $result['errstr'] = $errstr;
        if ($socket === false) {
            $result['error_type'] = self::isTimeout($errstr, $timings['socket_connect_ms'], $timeout)
                ? 'timeout' : 'socket_unreachable';
            $result['openssl_errors'] = self::drainOpenSslErrors();
            $result['duration_ms'] = self::elapsedMs($startedAt);
            return $result;
        }

        $result['socket_successful'] = true;
        stream_set_timeout($socket, 5);
        if ($ssl) {
            $tlsStartedAt = hrtime(true);
            $warning = '';
            set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
                $warning .= ($warning === '' ? '' : ' | ') . $message;
                return true;
            });
            try {
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            } finally {
                restore_error_handler();
            }
            $result['phase'] = 'tls';
            $timings['ssl_handshake_ms'] = self::elapsedMs($tlsStartedAt);
            $result['openssl_errors'] = self::drainOpenSslErrors();
            if ($crypto !== true) {
                $meta = stream_get_meta_data($socket);
                $result['error_type'] = !empty($meta['timed_out'])
                    ? 'timeout' : 'ssl_handshake_error';
                $result['errstr'] = $warning !== '' ? $warning : 'TLS-handshake is mislukt zonder PHP-waarschuwing.';
                fclose($socket);
                $result['duration_ms'] = self::elapsedMs($startedAt);
                return $result;
            }

            $result['tls_handshake'] = true;
            $meta = stream_get_meta_data($socket);
            $result['tls_version'] = $meta['crypto']['protocol'] ?? null;
            $result['cipher'] = $meta['crypto']['cipher_name'] ?? null;
            $options = stream_context_get_options($socket)['ssl'] ?? [];
            $chain = is_array($options['peer_certificate_chain'] ?? null) ? $options['peer_certificate_chain'] : [];
            $certificate = $options['peer_certificate'] ?? ($chain[0] ?? null);
            $result['certificate_chain_length'] = count($chain);
            $result['certificate_ca_valid'] = null;
            $result['certificate_chain_complete'] = null;
            if ($certificate !== null) {
                $parsed = openssl_x509_parse($certificate, false);
                if (is_array($parsed)) {
                    $result['certificate_subject'] = $parsed['subject'] ?? null;
                    $result['certificate_issuer'] = $parsed['issuer'] ?? null;
                    $san = (string) ($parsed['extensions']['subjectAltName'] ?? '');
                    $result['certificate_san'] = $san === '' ? [] : array_map('trim', explode(',', $san));
                    $result['certificate_expired'] = isset($parsed['validTo_time_t'])
                        ? (int) $parsed['validTo_time_t'] < time() : null;
                    $result['certificate_hostname_matches'] = self::certificateMatchesHost($parsed, $host);
                }
            }
        }
        fclose($socket);
        $result['phase'] = 'socket_complete';
        $result['duration_ms'] = self::elapsedMs($startedAt);
        return $result;
    }

    private static function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private static function isTimeout(string $error, int $durationMs, float $timeout): bool
    {
        return preg_match('/timed?\s*out|timeout/i', $error) === 1
            || $durationMs >= (int) floor($timeout * 1000 * 0.9);
    }

    /** @return string[] */
    private static function drainOpenSslErrors(): array
    {
        $errors = [];
        if (!function_exists('openssl_error_string')) {
            return $errors;
        }
        while (($message = openssl_error_string()) !== false) {
            $errors[] = $message;
        }
        return $errors;
    }

    /** @param array<string, mixed> $certificate */
    private static function certificateMatchesHost(array $certificate, string $host): bool
    {
        $names = [];
        foreach (explode(',', (string) ($certificate['extensions']['subjectAltName'] ?? '')) as $san) {
            $san = trim($san);
            if (str_starts_with(strtoupper($san), 'DNS:')) {
                $names[] = substr($san, 4);
            }
        }
        if ($names === [] && isset($certificate['subject']['CN'])) {
            $names[] = (string) $certificate['subject']['CN'];
        }
        foreach ($names as $name) {
            $pattern = '/^' . str_replace('\\*', '[^.]+', preg_quote(strtolower($name), '/')) . '$/D';
            if (preg_match($pattern, strtolower($host)) === 1) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    private static function records(string $host, int $type, string $field): array
    {
        $records = @dns_get_record($host, $type);
        if (!is_array($records)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            static fn (array $record): ?string => isset($record[$field]) ? (string) $record[$field] : null,
            $records,
        ))));
    }
}
