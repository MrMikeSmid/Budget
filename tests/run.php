<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use McpEmail\Intelligence\EmailAnalyzer;
use McpEmail\Intelligence\ReputationStore;
use McpEmail\McpServer;
use McpEmail\Mail\ImapClient;
use McpEmail\Intelligence\LinkAnalyzer;
use McpEmail\Intelligence\AttachmentAnalyzer;
use McpEmail\Security\EmailSecurityAnalyzer;
use McpEmail\Security\HeaderAnalyzer;
use McpEmail\Security\HtmlSanitizer;
use McpEmail\RateLimiter;
use McpEmail\Auth;

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
check(count($toolNames) === 26, 'all old and new tools must be registered');
foreach (['get_email','get_email_headers','list_attachments','extract_links','analyze_email_security','scan_mailbox_security','list_folders','get_security_summary'] as $name) {
    check(in_array($name, $toolNames, true), "$name must be registered");
}
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

$security = new EmailSecurityAnalyzer();
$fixture = static fn(string $subject='', string $text='', string $headers='', array $attachments=[], ?string $html=null): array => compact('subject','text','headers','attachments','html');
$newsletter=$security->analyze($fixture('Nieuwsbrief juli','Bekijk ons nieuws. Uitschrijven kan onderaan.',"Authentication-Results: mx; spf=pass; dkim=pass; dmarc=pass\r\nFrom: news@example.com\r\nReturn-Path: <news@example.com>"));
check($newsletter['risk_score'] < 25, 'legitimate newsletter is low risk');
$login=$security->analyze($fixture('Nieuwe login','Er was een nieuwe login.',"Authentication-Results: mx; spf=pass; dkim=pass; dmarc=pass\r\nFrom: security@example.com\r\nReturn-Path: <security@example.com>"));
check($login['risk_score'] < 25, 'authenticated legitimate login notice is not high risk');
$fake=$security->analyze($fixture('URGENT account blocked','Enter your password now.',"Authentication-Results: mx; spf=fail; dkim=fail; dmarc=fail\r\nFrom: bank@example.com\r\nReply-To: thief@evil.xyz\r\nReturn-Path: <thief@evil.xyz>"));
check($fake['risk_score'] >= 50, 'fake login and authentication failures are suspicious');
check($fake['authentication']['spf']==='fail' && $fake['authentication']['dkim']==='fail' && $fake['authentication']['dmarc']==='fail', 'SPF DKIM DMARC failures parsed');
check($fake['authentication']['address_mismatch']['reply_to'], 'Reply-To mismatch parsed');
$links=(new LinkAnalyzer())->analyze('<a href="https://192.0.2.1/login">https://bank.example/login</a> <a href="https://xn--pple-43d.com">Apple</a>');
check($links[0]['uses_ip'] && $links[0]['visible_url_mismatch'], 'IP and misleading hyperlink detected');
check($links[1]['punycode'], 'punycode detected');
$files=(new AttachmentAnalyzer())->analyze([['filename'=>'factuur.pdf.exe'],['filename'=>'page.html'],['filename'=>'docs.zip']]);
check($files[0]['risk']==='dangerous', 'executable double extension detected');
check($files[1]['risk']==='suspicious' && $files[2]['risk']==='suspicious', 'HTML and zip attachments detected');
$unicode=$security->analyze($fixture('Factuur 😊','Geachte klant, bedrag € 10. Nederlandse tekens: één.',"From: test@example.nl\r\nAuthentication-Results: mx; spf=pass; dkim=pass; dmarc=pass"));
check(str_contains(json_encode($unicode, JSON_UNESCAPED_UNICODE), 'inschatting'), 'Dutch and emoji fixture remains valid UTF-8');
$clean=HtmlSanitizer::sanitize('<p onclick="steal()">ok</p><script>alert(1)</script><img src="https://tracker/p.gif"><form>bad</form>');
check(!str_contains($clean,'script') && !str_contains($clean,'onclick') && !str_contains($clean,'https://tracker'), 'HTML scripts forms handlers and remote images sanitized');
$missing=(new HeaderAnalyzer())->analyze("From: sender@example.com\r\nMessage-ID: <x@example.com>");
check($missing['spf']==='missing' && $missing['received']===[], 'missing headers handled');
check(!RateLimiter::allow('fixture-'.getmypid(), 0, 60), 'rate limiter rejects over-limit request');
$schema=array_column($tools['result']->tools,null,'name');
check($schema['search_emails']->inputSchema['properties']['offset']['minimum']===0, 'pagination schema');
check(isset($schema['search_emails']->inputSchema['properties']['date_from'],$schema['search_emails']->inputSchema['properties']['from']), 'date and sender search schema');
$authMethod=new ReflectionMethod(Auth::class, 'getAuthToken');
$oldGet=$_GET; $oldServer=$_SERVER;
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
$_GET=['token'=>'gpt-url-token'];
check($authMethod->invoke(null)===['gpt-url-token','query'], 'GPT URL bearer token is accepted');
$_SERVER['HTTP_AUTHORIZATION']='Bearer header-token';
check($authMethod->invoke(null)===['header-token','authorization'], 'Authorization header takes precedence over URL token');
$_GET=$oldGet; $_SERVER=$oldServer;
echo "All tests passed\n";
