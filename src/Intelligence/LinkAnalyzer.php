<?php

declare(strict_types=1);

namespace McpEmail\Intelligence;

/** Local URL inspection only: links are never resolved, opened or requested. */
final class LinkAnalyzer
{
    private const SHORTENERS = ['bit.ly','t.co','tinyurl.com','goo.gl','ow.ly','buff.ly','rebrand.ly','is.gd','cutt.ly'];
    private const RISKY_TLDS = ['ru','xyz','top','click','link','work','zip','mov','country','gq','tk'];

    /** @return list<array<string,mixed>> */
    public function analyze(?string $html, ?string $text = null, string $senderDomain = ''): array
    {
        $found = [];
        if ($html !== null && trim($html) !== '' && class_exists(\DOMDocument::class)) {
            $dom = new \DOMDocument(); $old = libxml_use_internal_errors(true);
            $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($dom->getElementsByTagName('a') as $a) {
                $url = html_entity_decode(trim($a->getAttribute('href')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('~^https?://~i', $url)) $found[$url] = trim($a->textContent);
            }
            libxml_clear_errors(); libxml_use_internal_errors($old);
        }
        preg_match_all('~https?://[^\s<>"\']+~iu', ($html ?? '')."\n".($text ?? ''), $matches);
        foreach ($matches[0] ?? [] as $url) { $url = rtrim(html_entity_decode($url), '.,);]'); $found[$url] ??= $url; }
        $result=[];
        foreach (array_slice($found, 0, 100, true) as $url=>$label) {
            $clean = filter_var($url, FILTER_SANITIZE_URL) ?: $url;
            $host = strtolower((string) parse_url($clean, PHP_URL_HOST)); $host = rtrim($host, '.');
            $scheme = strtolower((string) parse_url($clean, PHP_URL_SCHEME)); $port = parse_url($clean, PHP_URL_PORT);
            $ip = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
            $short = in_array(preg_replace('/^www\./','',$host), self::SHORTENERS, true);
            $puny = str_contains($host, 'xn--');
            $unicode = preg_match('/[^\x20-\x7E]/u', $host) === 1;
            $tld = strtolower(pathinfo($host, PATHINFO_EXTENSION));
            $tracking = preg_match('/[?&](?:utm_[^=]+|fbclid|gclid|mc_[^=]+)=/i', $clean) === 1;
            $labelUrl = preg_match('~https?://[^\s]+~i', $label, $lm) ? strtolower(rtrim($lm[0], '/')) : '';
            $misleading = $labelUrl !== '' && $labelUrl !== strtolower(rtrim($url, '/'));
            $senderMismatch = $senderDomain !== '' && !$ip && $host !== $senderDomain && !str_ends_with($host, '.'.$senderDomain);
            $signals=[]; if($scheme!=='https')$signals[]='unencrypted_http'; if($ip)$signals[]='ip_address'; if($short)$signals[]='url_shortener';
            if($puny)$signals[]='punycode'; if($unicode)$signals[]='unicode_lookalike_possible'; if($misleading)$signals[]='misleading_anchor';
            if(in_array($tld,self::RISKY_TLDS,true))$signals[]='higher_risk_tld'; if($port!==null&&!in_array($port,[80,443],true))$signals[]='unusual_port';
            if($senderMismatch)$signals[]='sender_domain_mismatch';
            $penalty = count($signals)*15 + ($ip||$misleading?15:0); $score=max(0,100-$penalty);
            $result[]=['visible_text'=>$label,'original_url'=>$url,'clean_url'=>$clean,'url'=>$url,'protocol'=>$scheme,
                'hostname'=>$host,'domain'=>$host,'registrable_domain'=>$this->registrableDomain($host),'path'=>(string)parse_url($clean,PHP_URL_PATH),
                'query'=>(string)parse_url($clean,PHP_URL_QUERY),'visible_url_mismatch'=>$misleading,'uses_ip'=>$ip,'shortened'=>$short,
                'punycode'=>$puny,'unicode_lookalike_possible'=>$unicode,'sender_domain_mismatch'=>$senderMismatch,'tracking'=>$tracking,
                'https'=>$scheme==='https','risk_signals'=>$signals,'risk'=>$score>=75?'safe':($score>=45?'suspicious':'dangerous'),'score'=>$score];
        }
        return $result;
    }

    private function registrableDomain(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) return $host;
        $parts=explode('.',$host); return count($parts)>1?implode('.',array_slice($parts,-2)):$host;
    }
}
