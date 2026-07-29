<?php

declare(strict_types=1);

namespace McpEmail\Security;

final class HeaderAnalyzer
{
    /** @return array<string,mixed> */
    public function analyze(string $raw): array
    {
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $raw) ?? $raw;
        $value = static fn(string $name): string => preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/mi', $unfolded, $m) ? trim($m[1]) : '';
        preg_match_all('/^Received:\s*(.+)$/mi', $unfolded, $received);
        preg_match_all('/^ARC-(?:Authentication-Results|Message-Signature|Seal):\s*(.+)$/mi', $unfolded, $arc);
        preg_match_all('/\[?((?:\d{1,3}\.){3}\d{1,3}|[a-f0-9:]{3,})\]?/i', implode(' ', $received[1] ?? []), $ips);
        preg_match_all('/\b(?:from|by)\s+([a-z0-9.-]+\.[a-z]{2,})/i', implode(' ', $received[1] ?? []), $domains);
        $auth = strtolower($value('Authentication-Results'));
        $state = static fn(string $kind): string => preg_match('/\b'.$kind.'\s*=\s*([a-z]+)/', $auth, $m) ? $m[1] : 'missing';
        $email = static fn(string $v): string => preg_match('/[\w.%+\-]+@([\w.\-]+)/u', $v, $m) ? strtolower($m[0]) : '';
        $domain = static fn(string $v): string => str_contains($v, '@') ? substr(strrchr($v, '@') ?: '', 1) : '';
        $from = $email($value('From')); $reply = $email($value('Reply-To')); $return = $email($value('Return-Path'));
        $mismatch = ['reply_to' => $reply !== '' && $domain($reply) !== $domain($from), 'return_path' => $return !== '' && $domain($return) !== $domain($from)];
        return ['from'=>$value('From'),'reply_to'=>$value('Reply-To'),'return_path'=>$value('Return-Path'),
            'message_id'=>$value('Message-ID'),'received'=>$received[1] ?? [],'authentication_results'=>$value('Authentication-Results'),
            'spf'=>$state('spf'),'dkim'=>$state('dkim'),'dmarc'=>$state('dmarc'),'arc'=>$arc[1] ?? [],
            'sending_ips'=>array_values(array_unique($ips[1] ?? [])),'sending_domains'=>array_values(array_unique(array_map('strtolower', $domains[1] ?? []))),
            'mail_client'=>$value('User-Agent') ?: $value('X-Mailer'),'address_mismatch'=>$mismatch];
    }
}
