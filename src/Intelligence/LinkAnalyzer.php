<?php

declare(strict_types=1);

namespace McpEmail\Intelligence;

/** Extracts and scores links locally; it never visits an external URL. */
final class LinkAnalyzer
{
    private const SHORTENERS = ['bit.ly', 't.co', 'tinyurl.com', 'goo.gl', 'ow.ly', 'buff.ly', 'rebrand.ly'];
    private const RISKY_TLDS = ['ru', 'xyz', 'top', 'shop', 'space', 'click', 'link', 'work', 'zip', 'mov'];

    /** @return list<array{url:string, domain:string, https:bool, shortened:bool, tracking:bool, risk:string, score:int}> */
    public function analyze(?string $html, ?string $text = null): array
    {
        $source = ($html ?? '') . "\n" . ($text ?? '');
        preg_match_all('~https?://[^\s<>"\']+~iu', $source, $matches);
        $urls = array_values(array_unique(array_map(static fn (string $url): string => rtrim(html_entity_decode($url), '.,);]'), $matches[0])));
        $result = [];
        foreach (array_slice($urls, 0, 100) as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            $shortened = in_array($host, self::SHORTENERS, true);
            $tracking = (bool) preg_match('/(?:[?&](?:utm_[^=]+|fbclid|gclid|mc_[^=]+)=|\/track(?:ing)?\/|pixel)/i', $url);
            $tld = strtolower((string) pathinfo($host, PATHINFO_EXTENSION));
            $score = 100;
            if (!str_starts_with(strtolower($url), 'https://')) $score -= 20;
            if ($shortened) $score -= 30;
            if ($tracking) $score -= 10;
            if (in_array($tld, self::RISKY_TLDS, true)) $score -= 35;
            if ($host === '' || filter_var($url, FILTER_VALIDATE_URL) === false) $score -= 40;
            if (preg_match('/(?:login|verify|secure|account|wallet|password)[.-]/i', $host)) $score -= 20;
            $score = max(0, $score);
            $result[] = ['url' => $url, 'domain' => $host, 'https' => str_starts_with(strtolower($url), 'https://'),
                'shortened' => $shortened, 'tracking' => $tracking, 'risk' => $score >= 75 ? 'safe' : ($score >= 45 ? 'suspicious' : 'dangerous'), 'score' => $score];
        }
        return $result;
    }
}
