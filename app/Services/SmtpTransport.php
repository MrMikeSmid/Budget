<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmtpTransport
{
    /** @var resource|null */
    private $connection = null;
    private ?string $lastError = null;

    public function __construct(private readonly ?SmtpSettings $settings = null)
    {
    }

    public function send(string $to, string $subject, string $html, array $headers): bool
    {
        $this->lastError = null;
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $to) === 1) {
            $this->lastError = 'Het adres van de ontvanger is ongeldig.';
            return false;
        }
        $settings = $this->settings ?? new SmtpSettings();

        if (!$settings->isConfigured()) {
            $this->lastError = 'De SMTP-configuratie is nog niet compleet.';
            return false;
        }

        try {
            $this->connect($settings);
            $this->command('EHLO ' . $this->clientName(), [250]);

            if ($settings->encryption() === 'starttls') {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('De beveiligde STARTTLS-verbinding kon niet worden gestart.');
                }
                $this->command('EHLO ' . $this->clientName(), [250]);
            }

            if ($settings->username() !== '') {
                $this->authenticate($settings->username(), $settings->password());
            }

            $from = $this->extractAddress((string) ($headers['From'] ?? ''));
            if ($from === '') {
                throw new RuntimeException('Het afzenderadres ontbreekt.');
            }

            $this->command('MAIL FROM:<' . $from . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);
            $message = $this->message($to, $subject, $html, $headers);
            $this->write($this->dotStuff($message) . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
            $this->disconnect();
            return true;
        } catch (RuntimeException $exception) {
            $this->lastError = $exception->getMessage();
            $this->disconnect();
            return false;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function connect(SmtpSettings $settings): void
    {
        $scheme = $settings->encryption() === 'tls' ? 'tls' : 'tcp';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $settings->host(),
                'SNI_enabled' => true,
            ],
        ]);
        $errorNumber = 0;
        $errorMessage = '';
        $connection = @stream_socket_client(
            $scheme . '://' . $settings->host() . ':' . $settings->port(),
            $errorNumber,
            $errorMessage,
            $settings->timeout(),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($connection)) {
            throw new RuntimeException('Verbinding met SMTP-server mislukt: ' . ($errorMessage ?: 'onbekende fout') . '.');
        }

        $this->connection = $connection;
        stream_set_timeout($this->connection, $settings->timeout());
        $this->expect([220]);
    }

    private function authenticate(string $username, string $password): void
    {
        if ($password === '') {
            throw new RuntimeException('Voor SMTP-authenticatie ontbreekt het wachtwoord.');
        }

        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($username), [334]);
        $this->command(base64_encode($password), [235]);
    }

    private function command(string $command, array $expectedCodes): string
    {
        $this->write($command . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function write(string $content): void
    {
        if (!is_resource($this->connection) || fwrite($this->connection, $content) === false) {
            throw new RuntimeException('De verbinding met de SMTP-server is verbroken.');
        }
    }

    private function expect(array $expectedCodes): string
    {
        if (!is_resource($this->connection)) {
            throw new RuntimeException('Er is geen actieve SMTP-verbinding.');
        }

        $response = '';
        do {
            $line = fgets($this->connection, 515);
            if ($line === false) {
                $metadata = stream_get_meta_data($this->connection);
                throw new RuntimeException(!empty($metadata['timed_out'])
                    ? 'De SMTP-server antwoordde niet binnen de ingestelde tijd.'
                    : 'De SMTP-server heeft de verbinding onverwacht gesloten.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            $detail = trim(preg_replace('/^\d{3}[ -]?/m', '', $response) ?? $response);
            throw new RuntimeException('SMTP-fout ' . $code . ($detail !== '' ? ': ' . $detail : '.'));
        }

        return $response;
    }

    private function message(string $to, string $subject, string $html, array $headers): string
    {
        $lines = [
            'Date: ' . date(DATE_RFC2822),
            'To: <' . $to . '>',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->clientName() . '>',
        ];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . str_replace(["\r", "\n"], '', (string) $value);
        }
        return implode("\r\n", $lines) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $html);
    }

    private function dotStuff(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/^\./m', '..', $message) ?? $message;
        return str_replace("\n", "\r\n", $message);
    }

    private function extractAddress(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
            return filter_var(trim($matches[1]), FILTER_VALIDATE_EMAIL) ? trim($matches[1]) : '';
        }
        return filter_var(trim($from), FILTER_VALIDATE_EMAIL) ? trim($from) : '';
    }

    private function clientName(): string
    {
        $host = parse_url((string) config('app_url', ''), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function disconnect(): void
    {
        if (is_resource($this->connection)) {
            fclose($this->connection);
        }
        $this->connection = null;
    }
}
