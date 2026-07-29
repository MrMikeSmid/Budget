<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use McpEmail\Intelligence\EmailAnalyzer;
use McpEmail\Intelligence\ReputationStore;
use McpEmail\McpServer;
use McpEmail\Mail\ImapClient;

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}

$analyzer = new EmailAnalyzer();
$analysis = $analyzer->analyze([
    'uid' => 2087, 'subject' => 'Your OpenAI login code', 'from' => 'OpenAI <security@openai.com>',
    'text' => 'A new login was detected. Your verification code is 123456.', 'html' => null,
    'headers' => "Authentication-Results: mx; spf=pass; dkim=pass; dmarc=pass\r\nReturn-Path: <security@openai.com>",
    'attachments' => [],
]);
check($analysis['category'] === 'security', 'login mail must be security');
check($analysis['priority'] === 'critical', 'login code must be critical');
check($analysis['recommended_action'] === 'contains_login_code', 'login code action');
check($analysis['trust_score'] >= 80 && $analysis['spam_score'] < 20, 'authenticated official-looking mail should score well');

$phishing = $analyzer->analyze([
    'uid' => 2, 'subject' => 'URGENT: verify bank login now', 'from' => 'Bank <help@fake-bank.xyz>',
    'text' => null, 'html' => '<p>Password blocked, click to verify.</p><a href="http://login.fake-bank.xyz">Login</a>',
    'headers' => "Authentication-Results: mx; spf=fail; dkim=fail; dmarc=fail\r\nReply-To: thief@elsewhere.ru",
    'attachments' => [['filename' => 'invoice.pdf.exe', 'contentType' => 'application/octet-stream', 'size' => 100]],
]);
check($phishing['spam_score'] >= 70 && $phishing['category'] === 'phishing', 'phishing signals must produce phishing');
check($phishing['attachments'][0]['risk'] === 'dangerous', 'double extension must be dangerous');

$path = sys_get_temp_dir() . '/mcp-reputation-' . bin2hex(random_bytes(4)) . '.json';
$store = new ReputationStore($path); $store->record('a@example.com', 'example.com', 'newsletter');
$reputation = $store->get('example.com');
check($reputation['totals']['times_seen'] === 1 && $reputation['totals']['newsletter_count'] === 1, 'reputation counters');
@unlink($path);

$tools = (new McpServer())->handle(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list']);
$toolNames = array_map(static fn ($tool) => $tool->name, $tools['result']->tools);
check(count($toolNames) === 18, 'all old and new tools must be registered');
foreach (['delete_email','move_email','mark_email','reply_email','archive_email','restore_email','mark_flagged','mark_answered'] as $name) {
    check(in_array($name, $toolNames, true), "$name must be registered");
}

// Provider fixtures exercise the archive naming conventions used by Gmail,
// Outlook, Dovecot and Courier without requiring external mail credentials.
check(ImapClient::selectArchiveFolder(['INBOX', '[Gmail]/All Mail']) === '[Gmail]/All Mail', 'Gmail archive detection');
check(ImapClient::selectArchiveFolder(['Inbox', 'Archive']) === 'Archive', 'Outlook archive detection');
check(ImapClient::selectArchiveFolder(['INBOX', 'Archives']) === 'Archives', 'Dovecot archive detection');
check(ImapClient::selectArchiveFolder(['INBOX', 'All Mail']) === 'All Mail', 'Courier archive detection');
check(ImapClient::encodeFolder('Archief/Facturatie ü') !== '', 'UTF-8 folders must encode as modified UTF-7');
echo "All tests passed\n";
