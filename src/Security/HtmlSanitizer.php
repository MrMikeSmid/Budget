<?php

declare(strict_types=1);

namespace McpEmail\Security;

/** Sanitizes mail HTML without fetching any remote resource. */
final class HtmlSanitizer
{
    public static function sanitize(string $html): string
    {
        if (!class_exists(\DOMDocument::class)) {
            return strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><blockquote><a><table><tr><td><th>');
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // NONET prevents XXE/network access; no LIBXML_NOENT is used.
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="mail-root">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script|//iframe|//frame|//form|//object|//embed|//svg|//math|//meta|//base') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach ($xpath->query('//*') ?: [] as $node) {
            if (!$node instanceof \DOMElement) continue;
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                if (str_starts_with($name, 'on') || in_array($name, ['style', 'srcset', 'background'], true)
                    || ($name === 'src' && !str_starts_with(strtolower($value), 'cid:'))
                    || (in_array($name, ['href', 'src'], true) && preg_match('/^(?:javascript|data|file):/i', $value))) {
                    $node->removeAttribute($attribute->name);
                }
            }
        }
        $root = $dom->getElementById('mail-root');
        $result = '';
        if ($root !== null) foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return $result;
    }
}
