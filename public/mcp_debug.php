<?php

declare(strict_types=1);

/**
 * Standalone, read-only mail connection diagnostics.
 *
 * This endpoint deliberately does not include or alter public/mcp.php. It only
 * shares the project's authentication and configuration classes.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(60);

require __DIR__ . '/../vendor/autoload.php';

use McpEmail\Auth;
use McpEmail\Config;

const DEBUG_HOST = 'mail.mikesmid.nl';
const CONNECT_TIMEOUT = 8.0;

/** @var list<array{name: string, start: float, end: float, duration: float, ok: bool}> $timings */
$timings = [];

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return list<string> */
function drainOpenSslErrors(): array
{
    $errors = [];
    while (($error = openssl_error_string()) !== false) {
        $errors[] = $error;
    }
    return $errors;
}

function redact(string $message): string
{
    try {
        $account = Config::getAccount();
        foreach ([$account->imapPass, $account->smtpPass, $account->imapUser, $account->smtpUser] as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[REDACTED]', $message);
            }
        }
        $token = Config::getBearerToken();
        if ($token !== '') {
            $message = str_replace($token, '[REDACTED]', $message);
        }
    } catch (Throwable) {
        // Redaction remains best-effort when configuration itself is invalid.
    }
    return preg_replace('/([?&]token=)[^&\s]+/i', '$1[REDACTED]', $message) ?? $message;
}

function writeDebugLog(string $name, float $durationMs, bool $ok, string $detail): void
{
    $directory = __DIR__ . '/../logs';
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        return;
    }
    $line = sprintf(
        "[%s] test=%s duration_ms=%.2f result=%s detail=%s\n",
        date(DATE_ATOM),
        preg_replace('/[^a-z0-9_. -]/i', '_', $name),
        $durationMs,
        $ok ? 'OK' : 'FAILED',
        str_replace(["\r", "\n"], [' ', ' | '], redact($detail)),
    );
    @file_put_contents($directory . '/mcp_debug.log', $line, FILE_APPEND | LOCK_EX);
}

/** @return array{ok: bool, data: mixed, error: string} */
function runTest(string $name, callable $test): array
{
    global $timings;
    $start = microtime(true);
    $error = '';
    $data = null;
    try {
        $data = $test();
        $ok = !is_array($data) || !array_key_exists('ok', $data) || $data['ok'] === true;
        if (is_array($data) && isset($data['error'])) {
            $error = (string) $data['error'];
        }
    } catch (Throwable $exception) {
        $ok = false;
        $error = get_class($exception) . ': ' . $exception->getMessage();
    }
    $end = microtime(true);
    $duration = ($end - $start) * 1000;
    $timings[] = ['name' => $name, 'start' => $start, 'end' => $end, 'duration' => $duration, 'ok' => $ok];
    writeDebugLog($name, $duration, $ok, $error !== '' ? $error : 'Test voltooid');
    return ['ok' => $ok, 'data' => $data, 'error' => $error];
}

function status(bool $ok): string
{
    return '<span class="status ' . ($ok ? 'ok' : 'failed') . '">' . ($ok ? '✅ OK' : '❌ FAILED') . '</span>';
}

/** @param array<string, scalar|null> $rows */
function table(array $rows): string
{
    $html = '<dl>';
    foreach ($rows as $label => $value) {
        $html .= '<dt>' . h($label) . '</dt><dd>' . h($value ?? '—') . '</dd>';
    }
    return $html . '</dl>';
}

function socketErrorText(int $number, string $message): string
{
    return $message !== '' ? "[$number] $message" : "Socketfout $number";
}

/** @return array{ok: bool, time: float, error: string} */
function testSocket(int $port): array
{
    $start = microtime(true);
    $number = 0;
    $message = '';
    $socket = @stream_socket_client('tcp://' . DEBUG_HOST . ':' . $port, $number, $message, CONNECT_TIMEOUT);
    $duration = (microtime(true) - $start) * 1000;
    if ($socket === false) {
        return ['ok' => false, 'time' => $duration, 'error' => socketErrorText($number, $message)];
    }
    fclose($socket);
    return ['ok' => true, 'time' => $duration, 'error' => ''];
}

/** @return array{ok: bool, stream?: resource, time: float, tls: string, cipher: string, error: string} */
function tlsConnection(bool $verify = true, bool $captureCertificate = false): array
{
    drainOpenSslErrors();
    $options = ['ssl' => [
        'verify_peer' => $verify,
        'verify_peer_name' => $verify,
        'peer_name' => DEBUG_HOST,
        'SNI_enabled' => true,
        'capture_peer_cert' => $captureCertificate,
        'capture_peer_cert_chain' => $captureCertificate,
    ]];
    $context = stream_context_create($options);
    $number = 0;
    $message = '';
    $start = microtime(true);
    $stream = @stream_socket_client('ssl://' . DEBUG_HOST . ':993', $number, $message, CONNECT_TIMEOUT, STREAM_CLIENT_CONNECT, $context);
    $duration = (microtime(true) - $start) * 1000;
    $openssl = implode(' | ', drainOpenSslErrors());
    if ($stream === false) {
        $error = socketErrorText($number, $message);
        if ($openssl !== '') {
            $error .= ' | ' . $openssl;
        }
        return ['ok' => false, 'time' => $duration, 'tls' => '', 'cipher' => '', 'error' => $error];
    }
    $meta = stream_get_meta_data($stream);
    $crypto = $meta['crypto'] ?? [];
    return [
        'ok' => true, 'stream' => $stream, 'time' => $duration,
        'tls' => (string) ($crypto['protocol'] ?? 'Onbekend'),
        'cipher' => (string) ($crypto['cipher_name'] ?? 'Onbekend'), 'error' => '',
    ];
}

function certificateName(mixed $name): string
{
    if (!is_array($name)) {
        return (string) $name;
    }
    $parts = [];
    foreach ($name as $key => $value) {
        $parts[] = $key . '=' . (is_array($value) ? implode(', ', $value) : $value);
    }
    return implode(', ', $parts);
}

/** @param array<string, mixed> $certificate */
function certificateMatchesHost(array $certificate, string $host): bool
{
    $names = [];
    $san = (string) ($certificate['extensions']['subjectAltName'] ?? '');
    foreach (explode(',', $san) as $entry) {
        $entry = trim($entry);
        if (stripos($entry, 'DNS:') === 0) {
            $names[] = substr($entry, 4);
        }
    }
    if ($names === []) {
        $commonName = $certificate['subject']['CN'] ?? null;
        if (is_string($commonName)) {
            $names[] = $commonName;
        }
    }
    foreach ($names as $name) {
        $pattern = '/^' . str_replace('\\*', '[^.]+', preg_quote(strtolower($name), '/')) . '$/D';
        if (preg_match($pattern, strtolower($host)) === 1) {
            return true;
        }
    }
    return false;
}

// Authenticate before performing or displaying any diagnostics.
try {
    $authError = Auth::checkBearer();
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Server-configuratiefout.');
}
if ($authError !== null) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer');
    header('Content-Type: text/plain; charset=utf-8');
    exit($authError === 'missing_token' ? 'Bearer-token ontbreekt.' : 'Bearer-token is ongeldig.');
}

header('Content-Type: text/html; charset=utf-8');

$php = runTest('PHP informatie', static function (): array {
    $opensslVersion = defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'Niet beschikbaar';
    $openssl = extension_loaded('openssl');
    return ['ok' => $openssl && extension_loaded('imap'), 'values' => [
        'PHP versie' => PHP_VERSION, 'OS' => PHP_OS_FAMILY . ' (' . PHP_OS . ')',
        'OpenSSL versie' => $opensslVersion,
        'IMAP extensie geladen' => extension_loaded('imap') ? 'Ja' : 'Nee',
        'cURL geladen' => extension_loaded('curl') ? 'Ja' : 'Nee',
        'OpenSSL beschikbaar' => $openssl ? 'Ja' : 'Nee',
    ], 'error' => !$openssl ? 'OpenSSL-extensie ontbreekt' : (!extension_loaded('imap') ? 'IMAP-extensie ontbreekt' : '')];
});

$dns = runTest('DNS test', static function (): array {
    $start = microtime(true);
    $ip = gethostbyname(DEBUG_HOST);
    $duration = (microtime(true) - $start) * 1000;
    $ok = $ip !== DEBUG_HOST && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    return ['ok' => $ok, 'ip' => $ok ? $ip : '—', 'time' => $duration, 'error' => $ok ? '' : 'DNS-resolutie leverde geen IP-adres op'];
});

$ports = [];
foreach ([993 => 'SSL', 995 => 'SSL', 143 => 'STARTTLS', 110 => 'Onversleuteld'] as $port => $mode) {
    $ports[$port] = runTest("TCP $port $mode", static fn (): array => testSocket($port));
}

$certificate = runTest('SSL certificaat', static function (): array {
    $capture = tlsConnection(false, true);
    if (!$capture['ok']) {
        return $capture;
    }
    $parameters = stream_context_get_params($capture['stream']);
    fclose($capture['stream']);
    $resource = $parameters['options']['ssl']['peer_certificate'] ?? null;
    $parsed = $resource !== null ? openssl_x509_parse($resource) : false;
    if (!is_array($parsed)) {
        $errors = implode(' | ', drainOpenSslErrors());
        return ['ok' => false, 'error' => $errors !== '' ? $errors : 'Certificaat kon niet worden gelezen'];
    }
    $validation = tlsConnection(true, false);
    if ($validation['ok']) {
        fclose($validation['stream']);
    }
    $matches = certificateMatchesHost($parsed, DEBUG_HOST);
    return [
        'ok' => $validation['ok'] && $matches,
        'subject' => certificateName($parsed['subject'] ?? []),
        'issuer' => certificateName($parsed['issuer'] ?? []),
        'from' => isset($parsed['validFrom_time_t']) ? date(DATE_ATOM, $parsed['validFrom_time_t']) : 'Onbekend',
        'until' => isset($parsed['validTo_time_t']) ? date(DATE_ATOM, $parsed['validTo_time_t']) : 'Onbekend',
        'hostname' => $matches, 'chain' => $validation['ok'], 'error' => $validation['error'],
    ];
});

$handshake = runTest('TLS handshake', static function (): array {
    $result = tlsConnection(true, false);
    if ($result['ok']) {
        fclose($result['stream']);
    }
    return $result;
});

$imapHandle = null;
$imap = runTest('IMAP login', static function () use (&$imapHandle, $handshake): array {
    if (!$handshake['ok']) {
        return ['ok' => false, 'skipped' => true, 'error' => 'Niet uitgevoerd: TLS-handshake is mislukt.'];
    }
    if (!function_exists('imap_open')) {
        return ['ok' => false, 'error' => 'De PHP IMAP-extensie is niet geladen.'];
    }
    imap_errors();
    imap_alerts();
    drainOpenSslErrors();
    $account = Config::getAccount();
    $imapHandle = @imap_open('{mail.mikesmid.nl:993/imap/ssl}', $account->imapUser, $account->imapPass, 0, 1);
    if ($imapHandle !== false) {
        return ['ok' => true, 'error' => ''];
    }
    $messages = array_merge(imap_errors() ?: [], imap_alerts() ?: [], drainOpenSslErrors());
    $last = error_get_last();
    if ($last !== null) {
        $messages[] = sprintf('%s in %s:%d', $last['message'], basename($last['file']), $last['line']);
    }
    return ['ok' => false, 'error' => implode(' | ', array_unique($messages)) ?: 'Onbekende IMAP-fout'];
});

$mailbox = runTest('Mailbox informatie', static function () use (&$imapHandle, $imap): array {
    if (!$imap['ok'] || $imapHandle === null || $imapHandle === false) {
        return ['ok' => false, 'skipped' => true, 'error' => 'Niet uitgevoerd: IMAP-login is niet gelukt.'];
    }
    $mailboxes = @imap_list($imapHandle, '{' . DEBUG_HOST . ':993/imap/ssl}', '*') ?: [];
    $status = @imap_status($imapHandle, '{' . DEBUG_HOST . ':993/imap/ssl}INBOX', SA_MESSAGES | SA_UNSEEN);
    $subjects = [];
    $count = $status !== false ? (int) $status->messages : 0;
    for ($number = $count; $number > max(0, $count - 5); $number--) {
        $overview = @imap_fetch_overview($imapHandle, (string) $number, 0);
        $subject = $overview[0]->subject ?? '(geen onderwerp)';
        $subjects[] = function_exists('imap_utf8') ? imap_utf8((string) $subject) : (string) $subject;
    }
    return ['ok' => true, 'folders' => count($mailboxes), 'messages' => $count,
        'unseen' => $status !== false ? (int) $status->unseen : 0, 'subjects' => $subjects, 'error' => ''];
});
if ($imapHandle !== null && $imapHandle !== false) {
    imap_close($imapHandle);
}

$problem = 'Mailbox bereikbaar';
$recommendation = 'De mailverbinding werkt. Bewaar dit rapport en controleer de MCP-aanroep zelf als er nog klachten zijn.';
if (!$dns['ok']) {
    $problem = 'DNS-resolutie mislukt';
    $recommendation = 'Controleer de DNS-records en DNS-resolver van de hostingomgeving.';
} elseif (!$ports[993]['ok']) {
    $problem = 'TCP-verbinding naar poort 993 mislukt';
    $recommendation = 'Laat uitgaand verkeer naar mail.mikesmid.nl:993 door de hostingprovider of firewall toestaan.';
} elseif (!$certificate['ok']) {
    $problem = 'SSL certificaat ongeldig';
    $recommendation = 'Installeer een geldige volledige certificaatketen voor mail.mikesmid.nl en controleer de hostname in SAN.';
} elseif (!$handshake['ok']) {
    $problem = 'TLS handshake mislukt';
    $recommendation = 'Controleer de ondersteunde TLS-versies/ciphers en de volledige OpenSSL-fout hierboven.';
} elseif (!$imap['ok']) {
    $problem = 'Authenticatie mislukt';
    $recommendation = 'Controleer IMAP-gebruikersnaam, wachtwoord en of externe IMAP-login voor dit account is toegestaan.';
} elseif (!$mailbox['ok']) {
    $problem = 'Mailboxgegevens konden niet worden gelezen';
    $recommendation = 'Controleer mailboxrechten en de INBOX-naam op de mailserver.';
}
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>MCP Maildiagnose</title>
<style>
:root{color-scheme:dark;--bg:#090d12;--panel:#111821;--line:#263241;--text:#e9f0f7;--muted:#91a1b2;--ok:#38d27a;--bad:#ff5c6c}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#162334,var(--bg) 42%);color:var(--text);font:15px/1.55 system-ui,sans-serif}.wrap{max-width:1050px;margin:auto;padding:38px 20px 70px}h1{font-size:clamp(28px,5vw,44px);margin:0}.lead{color:var(--muted);margin:5px 0 28px}.grid{display:grid;gap:18px}.card{background:rgba(17,24,33,.94);border:1px solid var(--line);border-radius:14px;padding:22px;box-shadow:0 12px 35px #0005}.card h2{margin:0 0 16px;font-size:20px;display:flex;justify-content:space-between;gap:12px;align-items:center}.status{white-space:nowrap;border:1px solid;padding:5px 10px;border-radius:999px;font-size:13px}.status.ok{color:var(--ok);background:#113421;border-color:#247044}.status.failed{color:var(--bad);background:#3b1720;border-color:#7f2d3a}dl{display:grid;grid-template-columns:minmax(150px,240px) 1fr;margin:0;gap:1px;background:var(--line);border:1px solid var(--line);border-radius:9px;overflow:hidden}dt,dd{margin:0;padding:10px 12px;background:#0d141c;overflow-wrap:anywhere}dt{color:var(--muted)}.error{color:#ff9ba5;background:#301820;border-left:3px solid var(--bad);padding:10px 12px;margin:14px 0 0;white-space:pre-wrap;overflow-wrap:anywhere}.ports{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px}.port{border:1px solid var(--line);border-radius:9px;padding:14px;background:#0d141c}.port strong{display:flex;justify-content:space-between}.subjects{margin-bottom:0}.summary{border-width:2px;border-color:<?= $mailbox['ok'] ? 'var(--ok)' : 'var(--bad)' ?>}.summary h2{font-size:25px}.summary p:last-child{color:var(--muted)}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid var(--line);padding:9px 7px}th{color:var(--muted)}.privacy{font-size:13px;color:var(--muted)}@media(max-width:600px){dl{grid-template-columns:1fr}dt{padding-bottom:2px}dd{padding-top:2px}.card h2{align-items:flex-start;flex-direction:column}}
</style></head><body><main class="wrap">
<h1>MCP Maildiagnose</h1><p class="lead">Zelfstandige, alleen-lezen diagnose voor <strong><?= h(DEBUG_HOST) ?></strong></p>
<div class="grid">
<section class="card"><h2>Stap 1 — PHP informatie <?= status($php['ok']) ?></h2><?= table($php['data']['values']) ?><?php if ($php['error']): ?><div class="error"><?= h($php['error']) ?></div><?php endif ?></section>
<section class="card"><h2>Stap 2 — DNS test <?= status($dns['ok']) ?></h2><?= table(['Host' => DEBUG_HOST, 'IP-adres' => $dns['data']['ip'], 'Resolvetijd' => number_format($dns['data']['time'], 2) . ' ms']) ?><?php if ($dns['error']): ?><div class="error"><?= h($dns['error']) ?></div><?php endif ?></section>
<section class="card"><h2>Stap 3 — TCP Socket test <?= status(count(array_filter($ports, fn ($r) => !$r['ok'])) === 0) ?></h2><div class="ports"><?php foreach ($ports as $port => $result): ?><div class="port"><strong><?= h($port . ' ' . [993=>'SSL',995=>'SSL',143=>'STARTTLS',110=>'Onversleuteld'][$port]) ?> <?= status($result['ok']) ?></strong><div><?= number_format($result['data']['time'], 2) ?> ms</div><?php if ($result['error']): ?><div class="error"><?= h($result['error']) ?></div><?php endif ?></div><?php endforeach ?></div></section>
<section class="card"><h2>Stap 4 — SSL certificaat <?= status($certificate['ok']) ?></h2><?php if (is_array($certificate['data']) && isset($certificate['data']['subject'])): ?><?= table(['Subject'=>$certificate['data']['subject'],'Issuer'=>$certificate['data']['issuer'],'Geldig vanaf'=>$certificate['data']['from'],'Geldig tot'=>$certificate['data']['until'],'Hostname match'=>$certificate['data']['hostname']?'Ja':'Nee','Certificate chain geldig'=>$certificate['data']['chain']?'Ja':'Nee']) ?><?php endif ?><?php if ($certificate['error']): ?><div class="error"><?= h($certificate['error']) ?></div><?php endif ?></section>
<section class="card"><h2>Stap 5 — TLS handshake <?= status($handshake['ok']) ?></h2><?= table(['TLS versie'=>$handshake['data']['tls'] ?? '—','Cipher'=>$handshake['data']['cipher'] ?? '—','Handshake-tijd'=>isset($handshake['data']['time'])?number_format($handshake['data']['time'],2).' ms':'—']) ?><?php if ($handshake['error']): ?><div class="error"><?= h($handshake['error']) ?></div><?php endif ?></section>
<section class="card"><h2>Stap 6 — IMAP login <?= status($imap['ok']) ?></h2><p><?= $imap['ok'] ? 'Login gelukt.' : 'Login mislukt.' ?></p><?php if ($imap['error']): ?><div class="error"><?= h($imap['error']) ?></div><?php endif ?></section>
<section class="card"><h2>Stap 7 — Mailbox informatie <?= status($mailbox['ok']) ?></h2><?php if ($mailbox['ok']): ?><?= table(['Aantal mappen'=>$mailbox['data']['folders'],'Aantal inbox berichten'=>$mailbox['data']['messages'],'Aantal ongelezen'=>$mailbox['data']['unseen']]) ?><h3>Laatste 5 onderwerpen</h3><ol class="subjects"><?php foreach ($mailbox['data']['subjects'] as $subject): ?><li><?= h($subject) ?></li><?php endforeach ?></ol><?php else: ?><div class="error"><?= h($mailbox['error']) ?></div><?php endif ?></section>
<section class="card"><h2>Stap 8 — Volledige logging <?= status(is_dir(__DIR__ . '/../logs') && is_writable(__DIR__ . '/../logs')) ?></h2><p>Logbestand: <code>logs/mcp_debug.log</code></p><p class="privacy">Wachtwoorden en volledige tokens worden nooit gelogd.</p></section>
<section class="card"><h2>Stap 9 — Automatische timing <?= status(true) ?></h2><div style="overflow-x:auto"><table><thead><tr><th>Test</th><th>Starttijd</th><th>Eindtijd</th><th>Duur</th><th>Resultaat</th></tr></thead><tbody><?php foreach ($timings as $timing): ?><tr><td><?= h($timing['name']) ?></td><td><?= h(date('H:i:s', (int)$timing['start']) . sprintf('.%03d', ((int)($timing['start']*1000))%1000)) ?></td><td><?= h(date('H:i:s', (int)$timing['end']) . sprintf('.%03d', ((int)($timing['end']*1000))%1000)) ?></td><td><?= number_format($timing['duration'],2) ?> ms</td><td><?= status($timing['ok']) ?></td></tr><?php endforeach ?></tbody></table></div></section>
<section class="card summary"><h2>Stap 10 — Probleem gevonden</h2><h3><?= h($problem) ?></h3><p><?= h($recommendation) ?></p></section>
</div></main></body></html>
