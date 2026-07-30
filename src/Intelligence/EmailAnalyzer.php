<?php

declare(strict_types=1);

namespace McpEmail\Intelligence;

/** Transparent, deterministic mail classifier designed for future AI strategy replacement. */
final class EmailAnalyzer
{
    public function __construct(
        private readonly LinkAnalyzer $links = new LinkAnalyzer(),
        private readonly AttachmentAnalyzer $attachments = new AttachmentAnalyzer(),
    ) {}

    /** @param array<string,mixed> $message @return array<string,mixed> */
    public function analyze(array $message): array
    {
        $headers = (string) ($message['headers'] ?? '');
        $subject = (string) ($message['subject'] ?? '');
        $text = trim((string) ($message['text'] ?? '') ?: strip_tags((string) ($message['html'] ?? '')));
        $html = (string) ($message['html'] ?? '');
        $from = $this->address((string) ($message['from'] ?? ''));
        $domain = $this->domain($from);
        $displayFrom = strtolower((string) ($message['from'] ?? ''));
        $replyTo = $this->header($headers, 'Reply-To');
        $returnPath = $this->address($this->header($headers, 'Return-Path'));
        $authentication = strtolower($this->header($headers, 'Authentication-Results') . ' ' . $this->header($headers, 'Received-SPF'));
        $spf = $this->authState($authentication, 'spf');
        $dkim = $this->authState($authentication, 'dkim');
        $dmarc = $this->authState($authentication, 'dmarc');
        $links = $this->links->analyze($html, $text);
        $attachments = $this->attachments->analyze((array) ($message['attachments'] ?? []));
        $signals = []; $spam = 0; $trust = 45;
        foreach (['SPF' => $spf, 'DKIM' => $dkim, 'DMARC' => $dmarc] as $name => $state) {
            if ($state === 'pass') { $trust += 14; $signals[] = "$name geldig"; }
            elseif ($state === 'fail') { $spam += 22; $trust -= 18; $signals[] = "$name mislukt"; }
            else { $spam += 4; $signals[] = "$name ontbreekt of is onbekend"; }
        }
        if ($replyTo !== '' && $this->domain($this->address($replyTo)) !== $domain) { $spam += 16; $trust -= 12; $signals[] = 'Reply-To wijkt af van de afzender'; }
        if ($returnPath !== '' && $this->domain($returnPath) !== $domain) { $spam += 10; $trust -= 8; $signals[] = 'Return-Path wijkt af van de afzender'; }
        if ($this->riskyDomain($domain)) { $spam += 22; $trust -= 20; $signals[] = "verdachte domeinextensie ($domain)"; }
        foreach (['openai' => 'openai.com', 'spotify' => 'spotify.com', 'paypal' => 'paypal.com', 'microsoft' => 'microsoft.com'] as $brand => $official) {
            if (str_contains($displayFrom, $brand) && $domain !== $official && !str_ends_with($domain, '.' . $official)) {
                $spam += 28; $trust -= 25; $signals[] = "Display Name noemt $brand maar het werkelijke domein is $domain";
            }
        }
        if (preg_match('/[^\x00-\x7F]/', $domain)) { $spam += 22; $trust -= 20; $signals[] = 'mogelijk Unicode-domeinspoofing'; }
        if (($message['text'] ?? null) === null && $html !== '') { $spam += 6; $signals[] = 'alleen HTML-inhoud'; }
        if (count($links) > 10) { $spam += min(20, count($links)); $signals[] = 'ongebruikelijk veel hyperlinks'; }
        if (preg_match('/<img[^>]+(?:width=["\']?1|height=["\']?1|pixel|track)/i', $html)) { $spam += 8; $signals[] = 'trackingpixel gevonden'; }
        $corpus = strtolower($subject . ' ' . $text);
        if (preg_match('/urgent|act now|direct actie|laatste waarschuwing|je hebt gewonnen|claim nu/i', $corpus)) { $spam += 12; $signals[] = 'clickbait of urgentietaal'; }
        if (preg_match('/bitcoin|crypto|wallet|seed phrase|airdrop|ethereum/i', $corpus)) { $spam += 8; $signals[] = 'cryptopatroon'; }
        if (preg_match('/(?:bank|rekening|payment|betaling).{0,40}(?:verify|verifieer|blokk|klik)/is', $corpus)) { $spam += 20; $signals[] = 'bank-phishingpatroon'; }
        if (preg_match('/(?:login|sign[ -]?in|wachtwoord|password).{0,50}(?:verify|reset|code|klik)/is', $corpus)) { $spam += 10; $signals[] = 'login- of wachtwoordpatroon'; }
        foreach ($links as $link) if ($link['risk'] === 'dangerous') { $spam += 12; $trust -= 8; $signals[] = 'gevaarlijke link gevonden'; break; }
        foreach ($attachments as $attachment) if ($attachment['risk'] === 'dangerous') { $spam += 30; $trust -= 25; $signals[] = 'gevaarlijke bijlage gevonden'; break; }

        $category = $this->category($corpus, $spam);
        $priority = $this->priority($corpus, $category);
        $action = $this->action($corpus, $category, $spam);
        $spam = max(0, min(100, $spam)); $trust = max(0, min(100, $trust - intdiv($spam, 4)));
        $result = ['id' => (int) ($message['uid'] ?? 0), 'sender' => $from, 'domain' => $domain, 'spam_score' => $spam,
            'trust_score' => $trust, 'category' => $category, 'priority' => $priority, 'labels' => $this->labels($corpus, $category),
            'summary' => $this->summary($subject, $text), 'recommended_action' => $action,
            'reason' => ucfirst(implode('. ', array_slice(array_unique($signals), 0, 5))) . '.',
            'authentication' => ['spf' => $spf, 'dkim' => $dkim, 'dmarc' => $dmarc], 'links' => $links, 'attachments' => $attachments];
        error_log(sprintf('[mail-intelligence] uid=%d domain=%s spam=%d trust=%d category=%s signals=%d',
            $result['id'], str_replace(["\r", "\n"], '', $domain), $spam, $trust, $category, count($signals)));
        return $result;
    }

    private function category(string $text, int $spam): string
    {
        if ($spam >= 70) return preg_match('/login|password|wachtwoord|bank|verify|verifieer/', $text) ? 'phishing' : 'spam';
        $map = ['security' => '/login|security|beveiliging|verification code|inlogcode|2fa/', 'invoice' => '/invoice|factuur/',
            'government' => '/belasting|overheid|gemeente|government|digid/', 'crypto' => '/bitcoin|crypto|ethereum|wallet/',
            'development' => '/github|gitlab|deploy|developer|pull request|production/', 'ai' => '/openai|chatgpt|artificial intelligence|\bai\b/',
            'newsletter' => '/newsletter|nieuwsbrief|unsubscribe|uitschrijven/', 'shopping' => '/order|bestelling|shipping|bezorg/',
            'social' => '/linkedin|facebook|instagram|social/', 'finance' => '/bank|betaling|payment|rekening/',
            'marketing' => '/aanbieding|korting|sale|promotion/'];
        foreach ($map as $category => $pattern) if (preg_match($pattern, $text)) return $category;
        return 'unknown';
    }

    private function priority(string $text, string $category): string
    {
        if (preg_match('/login code|inlogcode|verification code|2fa|account compromised/', $text)) return 'critical';
        if (in_array($category, ['security', 'phishing'], true)) return 'critical';
        if (in_array($category, ['invoice', 'finance', 'government', 'important'], true)) return 'high';
        return in_array($category, ['newsletter', 'marketing', 'shopping'], true) ? 'low' : 'normal';
    }

    private function action(string $text, string $category, int $spam): string
    {
        if ($spam >= 70) return 'move_to_spam';
        if ($spam >= 35) return 'verify_sender';
        if (preg_match('/login code|inlogcode|verification code|2fa/', $text)) return 'contains_login_code';
        if ($category === 'invoice') return preg_match('/pay|betaling|betaal/', $text) ? 'contains_payment_request' : 'contains_invoice';
        if (in_array($category, ['newsletter', 'marketing'], true)) return 'archive';
        return in_array($category, ['security', 'finance', 'government'], true) ? 'read_now' : 'read_now';
    }

    /** @return list<string> */
    private function labels(string $text, string $category): array
    {
        $labels = ['security' => 'Beveiliging', 'invoice' => 'Factuur', 'finance' => 'Bank', 'government' => 'Belasting',
            'work' => 'Werk', 'personal' => 'Persoonlijk', 'newsletter' => 'Nieuwsbrief', 'marketing' => 'Marketing',
            'social' => 'Social', 'crypto' => 'Crypto', 'development' => 'Development', 'ai' => 'AI', 'spam' => 'Spam', 'phishing' => 'Phishing'];
        $result = isset($labels[$category]) ? [$labels[$category]] : [];
        if (str_contains($text, 'openai') || str_contains($text, 'chatgpt')) $result[] = 'OpenAI';
        if (preg_match('/login|inlog|2fa/', $text)) $result[] = 'Login';
        return array_values(array_unique($result ?: ['Onbekend']));
    }

    private function summary(string $subject, string $text): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        $first = $clean === '' ? '' : (preg_split('/(?<=[.!?])\s+/', $clean, 2)[0] ?? $clean);
        if (mb_strlen($first) > 220) $first = rtrim(mb_substr($first, 0, 217)) . '...';
        return $first !== '' ? $first : ($subject !== '' ? "E-mail met onderwerp: $subject." : 'E-mail zonder tekstuele inhoud.');
    }

    private function header(string $headers, string $name): string { return preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : ''; }
    private function address(string $value): string { return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+/i', $value, $m) ? strtolower($m[0]) : strtolower(trim($value, '<> ')); }
    private function domain(string $address): string { return str_contains($address, '@') ? strtolower(substr(strrchr($address, '@') ?: '', 1)) : strtolower($address); }
    private function authState(string $header, string $name): string { return preg_match('/\b' . $name . '\s*=\s*(pass|fail|softfail|neutral|none|temperror|permerror)/', $header, $m) ? ($m[1] === 'pass' ? 'pass' : ($m[1] === 'fail' || $m[1] === 'softfail' ? 'fail' : 'unknown')) : 'unknown'; }
    private function riskyDomain(string $domain): bool { return (bool) preg_match('/\.(?:ru|xyz|top|shop|space|click|link|work|zip|mov)$/i', $domain); }
}
