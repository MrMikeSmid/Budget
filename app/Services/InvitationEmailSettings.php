<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use DOMDocument;
use DOMElement;
use DOMNode;

final class InvitationEmailSettings
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'blockquote'];
    private const TOKENS = ['{{inviter_name}}', '{{inviter_email}}', '{{list_title}}', '{{invitee_email}}'];

    public function senderName(): string
    {
        return trim((new AppSetting())->get('invitation_sender_name', (string) config('name')) ?? (string) config('name'));
    }

    public function senderEmail(): string
    {
        return trim((new AppSetting())->get('invitation_sender_email', (string) config('mail_from')) ?? (string) config('mail_from'));
    }

    public function message(): string
    {
        return (new AppSetting())->get('invitation_message_html', self::defaultMessage()) ?? self::defaultMessage();
    }

    public function save(string $senderName, string $senderEmail, string $message): void
    {
        (new AppSetting())->setMany([
            'invitation_sender_name' => trim($senderName),
            'invitation_sender_email' => trim($senderEmail),
            'invitation_message_html' => $this->sanitizeMessage($message),
        ]);
    }

    public function sanitizeMessage(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return self::defaultMessage();
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="invitation-message">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('invitation-message');
        if (!$root) {
            return self::defaultMessage();
        }

        $this->sanitizeChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result) !== '' ? trim($result) : self::defaultMessage();
    }

    /** @return list<string> */
    public static function tokens(): array
    {
        return self::TOKENS;
    }

    public function renderMessage(array $inviter, array $list, string $inviteeEmail): string
    {
        $replacements = [
            '{{inviter_name}}' => htmlspecialchars((string) $inviter['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{inviter_email}}' => htmlspecialchars((string) $inviter['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{list_title}}' => htmlspecialchars((string) $list['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{invitee_email}}' => htmlspecialchars($inviteeEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];

        return strtr($this->message(), $replacements);
    }

    public function renderEmail(array $inviter, array $list, string $inviteeEmail): string
    {
        $appName = htmlspecialchars((string) config('name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logoUrl = htmlspecialchars(absolute_url('/pwa-icon/app-192'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $websiteUrl = htmlspecialchars(absolute_url('/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $listUrl = htmlspecialchars(absolute_url('/lists/' . $list['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $privacyUrl = htmlspecialchars(absolute_url('/privacy'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $termsUrl = htmlspecialchars(absolute_url('/voorwaarden'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $year = date('Y');
        $message = $this->renderMessage($inviter, $list, $inviteeEmail);

        return <<<HTML
<!doctype html>
<html lang="nl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$appName}</title><style>#email-message h2{margin:0 0 18px;color:#29242f;font-size:26px;line-height:1.25}#email-message h3{margin:22px 0 10px;color:#29242f;font-size:19px}#email-message p{margin:0 0 16px}#email-message ul,#email-message ol{margin:0 0 16px;padding-left:24px}#email-message blockquote{margin:18px 0;padding:12px 16px;border-left:3px solid #6d4aff;background:#f7f4ff}#email-message a{color:#6d4aff}</style></head>
<body style="margin:0;padding:0;background:#f6f3f8;color:#29242f;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#f6f3f8;"><tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 12px 40px rgba(48,35,70,.10);">
<tr><td style="padding:26px 34px;background:#6d4aff;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td><img src="{$logoUrl}" width="48" height="48" alt="" style="display:block;border:0;border-radius:14px;"></td><td style="padding-left:14px;color:#ffffff;font-size:24px;font-weight:700;">{$appName}</td></tr></table></td></tr>
<tr><td id="email-message" style="padding:38px 34px 18px;font-size:16px;line-height:1.7;color:#4c4652;">{$message}</td></tr>
<tr><td style="padding:4px 34px 42px;"><a href="{$listUrl}" style="display:inline-block;padding:15px 24px;border-radius:14px;background:#6d4aff;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Open het lijstje</a><p style="margin:18px 0 0;color:#817989;font-size:12px;line-height:1.6;">Werkt de knop niet? Open dan deze link:<br><a href="{$listUrl}" style="color:#6d4aff;word-break:break-all;">{$listUrl}</a></p></td></tr>
<tr><td style="padding:24px 34px;background:#f8f6fa;border-top:1px solid #ece7f0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td><table role="presentation" cellpadding="0" cellspacing="0"><tr><td><img src="{$logoUrl}" width="28" height="28" alt="{$appName}" style="display:block;border:0;border-radius:8px;"></td><td style="padding-left:10px;font-size:12px;"><a href="{$websiteUrl}" style="color:#4c4652;text-decoration:none;">{$websiteUrl}</a></td></tr></table></td></tr><tr><td style="padding-top:18px;color:#8b8491;font-size:11px;line-height:1.7;"><a href="{$privacyUrl}" style="color:#6d4aff;text-decoration:none;">Privacy</a>&nbsp;&nbsp;·&nbsp;&nbsp;<a href="{$termsUrl}" style="color:#6d4aff;text-decoration:none;">Voorwaarden</a><br>&copy; {$year} {$appName}. Alle rechten voorbehouden.</td></tr></table></td></tr>
</table></td></tr></table></body></html>
HTML;
    }

    public static function defaultMessage(): string
    {
        return '<h2>Je bent uitgenodigd!</h2><p>Hoi,</p><p><strong>{{inviter_name}}</strong> ({{inviter_email}}) heeft je uitgenodigd voor het lijstje <strong>“{{list_title}}”</strong>.</p><p>Log in met <strong>{{invitee_email}}</strong> en vink daarna gezellig samen mee.</p>';
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->sanitizeChildren($node);
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                foreach (iterator_to_array($node->attributes) as $attribute) {
                    if ($tag !== 'a' || strtolower($attribute->name) !== 'href') {
                        $node->removeAttribute($attribute->name);
                    }
                }

                if ($tag === 'a') {
                    $href = trim($node->getAttribute('href'));
                    if ($href === '' || preg_match('#^(https?://|mailto:)#i', $href) !== 1) {
                        $node->removeAttribute('href');
                    } else {
                        $node->setAttribute('target', '_blank');
                        $node->setAttribute('rel', 'noopener noreferrer');
                    }
                }

                $this->sanitizeChildren($node);
            }
            $node = $next;
        }
    }
}
