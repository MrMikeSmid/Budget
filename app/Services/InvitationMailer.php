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
                @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $message, $headers);
    }

    public function send(string $email, array $inviter, array $list): bool
    {
        $subject = (string) config('name') . ': uitnodiging voor ' . $list['title'];
        $message = implode("\n", [
            'Hoi,',
            '',
            $inviter['name'] . ' (' . $inviter['email'] . ') heeft je uitgenodigd voor de lijst "' . $list['title'] . '" in ' . config('name') . '.',
            '',
            'Open de lijst via:',
            absolute_url('/lists/' . $list['id']),
            '',
            'Log in met dit e-mailadres om mee te doen: ' . $email,
            '',
            'Groet,',
            config('name'),
        ]);
        $headers = [
            'From' => config('mail_from'),
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Mailer' => config('name'),
        ];

        return (bool) ($this->transport)($email, $subject, $message, $headers);
    }
}
