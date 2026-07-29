<?php

declare(strict_types=1);

namespace McpEmail\Mail;

/** Performs DNS, TCP and optional implicit-TLS checks without authenticating. */
final class MailConnectionDiagnostics
{
    /** @return array<string, mixed> Safe diagnostics; credentials are never accepted. */
    public static function diagnoseMailConnection(
        string $host,
        int $port,
        string $protocol,
        bool $ssl,
        bool $connectIpv4 = false,
        bool $noValidateCert = false,
        float $timeout = 10.0,
    ): array {
        $ipv4 = self::records($host, DNS_A, 'ip');
        $ipv6 = self::records($host, DNS_AAAA, 'ipv6');
        $result = [
            'host' => $host, 'port' => $port, 'protocol' => strtolower($protocol), 'ssl' => $ssl,
            'dns_resolved' => $ipv4 !== [] || $ipv6 !== [],
            'ipv4_addresses' => $ipv4, 'ipv4_address' => $ipv4[0] ?? null,
            'ipv6_addresses' => $ipv6,
            'connection_target' => $connectIpv4 ? ($ipv4[0] ?? null) : $host,
            'socket_connected' => false, 'ssl_handshake' => $ssl ? false : null,
            'socket_error_number' => 0, 'socket_error_message' => '',
            'certificate_validation' => $noValidateCert ? 'DISABLED' : 'ENABLED',
            'certificate_validation_disabled' => $noValidateCert,
        ];
        if (!$result['dns_resolved'] || ($connectIpv4 && $ipv4 === [])) {
            $result['error_type'] = 'dns_error';
            $result['socket_error_message'] = $connectIpv4
                ? 'Geen IPv4-adres gevonden voor rechtstreekse IPv4-verbinding.'
                : 'De hostnaam kon niet naar IPv4 of IPv6 worden vertaald.';
            return $result;
        }

        $target = (string) $result['connection_target'];
        $uri = filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? "tcp://[$target]:$port" : "tcp://$target:$port";
        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'verify_peer' => !$noValidateCert,
            'verify_peer_name' => !$noValidateCert,
            'allow_self_signed' => $noValidateCert,
            'SNI_enabled' => true,
        ]]);
        $errno = 0;
        $error = '';
        $socket = @stream_socket_client($uri, $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $context);
        $result['socket_error_number'] = $errno;
        $result['socket_error_message'] = $error;
        if ($socket === false) {
            $result['error_type'] = 'socket_unreachable';
            return $result;
        }
        $result['socket_connected'] = true;
        stream_set_timeout($socket, (int) ceil($timeout));
        if ($ssl) {
            $warning = '';
            set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
                $warning = $message;
                return true;
            });
            try {
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            } finally {
                restore_error_handler();
            }
            if ($crypto !== true) {
                $result['error_type'] = 'ssl_handshake_error';
                $result['socket_error_message'] = $warning !== '' ? $warning : 'TLS-handshake is mislukt.';
                fclose($socket);
                return $result;
            }
            $result['ssl_handshake'] = true;
            $params = stream_get_meta_data($socket);
            $result['tls_version'] = $params['crypto']['protocol'] ?? null;
        }
        fclose($socket);
        $result['error_type'] = null;
        return $result;
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
