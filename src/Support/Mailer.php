<?php

namespace App\Support;

use App\Models\Settings;

/**
 * Kleine, afhankelijkheidsvrije SMTP-client. Genoeg voor de twee soorten mail
 * die deze app verstuurt (verificatie, uitnodiging): STARTTLS/impliciete TLS,
 * AUTH LOGIN, één multipart text+html bericht, geen bijlagen.
 *
 * trySend() gooit bewust NOOIT een exception: elke aanroeper (registratie,
 * uitnodigen) moet altijd een bruikbaar resultaat krijgen en bij falen
 * (geen SMTP geconfigureerd, verkeerde gegevens, netwerkfout) gewoon
 * terugvallen op het zelf tonen van de link — mail is nergens een harde vereiste.
 */
final class Mailer
{
    public static function trySend(string $to, string $subject, string $html, string $text): bool
    {
        try {
            $config = Settings::mailConfig();

            if (empty($config['host'])) {
                return false;
            }

            self::send($config, $to, $subject, $html, $text);
            return true;
        } catch (\Throwable $e) {
            error_log('Mailer::trySend mislukt: ' . $e->getMessage());
            return false;
        }
    }

    private static function send(array $config, string $to, string $subject, string $html, string $text): void
    {
        $host = $config['host'];
        $port = (int) ($config['port'] ?? 587);
        $encryption = $config['encryption'] ?? 'tls';

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $stream = stream_socket_client($transport . ':' . $port, $errno, $errstr, 10);
        if (!$stream) {
            throw new \RuntimeException("Kan geen verbinding maken met SMTP-server: {$errstr}");
        }
        stream_set_timeout($stream, 10);

        self::expect($stream, '220');
        $localName = parse_url(Settings::appUrl() ?? '', PHP_URL_HOST) ?: 'localhost';

        self::command($stream, "EHLO {$localName}", '250');

        if ($encryption === 'tls') {
            self::command($stream, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS opzetten is mislukt.');
            }
            self::command($stream, "EHLO {$localName}", '250');
        }

        if (!empty($config['username'])) {
            self::command($stream, 'AUTH LOGIN', '334');
            self::command($stream, base64_encode($config['username']), '334');
            self::command($stream, base64_encode((string) $config['password']), '235');
        }

        $fromAddress = $config['from_address'];
        $fromName = $config['from_name'] ?? '';

        self::command($stream, "MAIL FROM:<{$fromAddress}>", '250');
        self::command($stream, "RCPT TO:<{$to}>", ['250', '251']);
        self::command($stream, 'DATA', '354');

        $boundary = 'bnd-' . bin2hex(random_bytes(12));
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::encodeHeaderAddress($fromName, $fromAddress),
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeaderText($subject),
            'Date: ' . date('r'),
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--\r\n";

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // Regels die met een punt beginnen moeten volgens SMTP verdubbeld worden.
        $message = preg_replace('/^\./m', '..', $message);

        fwrite($stream, $message . "\r\n.\r\n");
        self::expect($stream, '250');

        fwrite($stream, "QUIT\r\n");
        fclose($stream);
    }

    /** @param string|array<string> $expectedCode */
    private static function command($stream, string $line, $expectedCode): void
    {
        fwrite($stream, $line . "\r\n");
        self::expect($stream, $expectedCode);
    }

    /** @param string|array<string> $expectedCode */
    private static function expect($stream, $expectedCode): void
    {
        $expected = (array) $expectedCode;
        $response = '';

        do {
            $line = fgets($stream, 515);
            if ($line === false) {
                throw new \RuntimeException('Geen antwoord van SMTP-server.');
            }
            $response = $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException("Onverwacht SMTP-antwoord: {$response}");
        }
    }

    private static function encodeHeaderText(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function encodeHeaderAddress(string $name, string $address): string
    {
        if ($name === '') {
            return "<{$address}>";
        }

        return self::encodeHeaderText($name) . " <{$address}>";
    }
}
