<?php

declare(strict_types=1);

namespace App\Services;

use Closure;

final class InvitationMailer
{
    private Closure $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport !== null
            ? Closure::fromCallable($transport)
            : static fn (string $to, string $subject, string $message, array $headers): bool =>
                (new SmtpTransport())->send($to, $subject, $message, $headers);
    }

    public function send(string $email, array $inviter, array $list): bool
    {
        $settings = new InvitationEmailSettings();
        $subject = (string) config('name') . ': uitnodiging voor ' . $list['title'];
        $senderName = str_replace(["\r", "\n"], '', $settings->senderName());
        $senderEmail = str_replace(["\r", "\n"], '', $settings->senderEmail());
        $encodedSenderName = mb_encode_mimeheader($senderName, 'UTF-8');
        $headers = [
            'From' => $encodedSenderName . ' <' . $senderEmail . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Mailer' => (string) config('name'),
        ];

        return (bool) ($this->transport)($email, $subject, $settings->renderEmail($inviter, $list, $email), $headers);
    }
}
