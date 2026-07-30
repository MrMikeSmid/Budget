<?php

declare(strict_types=1);

namespace McpEmail\Mail;

use McpEmail\MailAccountConfig;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpClient
{
    /**
     * @param string|string[] $to
     * @param string|string[]|null $cc
     * @param string|string[]|null $bcc
     */
    public static function send(
        MailAccountConfig $account,
        string|array $to,
        string $subject,
        ?string $text,
        ?string $html,
        string|array|null $cc = null,
        string|array|null $bcc = null,
        array $headers = []
    ): string {
        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = $account->smtpHost;
            $mailer->Port = $account->smtpPort;
            $mailer->SMTPAuth = true;
            $mailer->Username = $account->smtpUser;
            $mailer->Password = $account->smtpPass;
            $mailer->SMTPSecure = $account->smtpSecure ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->SMTPOptions = ['ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]];
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            // PHPMailer passes this to fsockopen(); keep both connect and socket reads bounded.
            $mailer->Timeout = (int) ceil(min(5.0, $account->mailSocketTimeout));
            $mailer->getSMTPInstance()->Timelimit = 5;
            $mailer->SMTPKeepAlive = false;

            $mailer->setFrom($account->fromAddress, $account->fromName ?? '');

            foreach (self::toArray($to) as $address) {
                $mailer->addAddress($address);
            }
            foreach (self::toArray($cc) as $address) {
                $mailer->addCC($address);
            }
            foreach (self::toArray($bcc) as $address) {
                $mailer->addBCC($address);
            }

            $mailer->Subject = $subject;
            foreach ($headers as $name => $value) {
                if (!in_array($name, ['In-Reply-To', 'References'], true)) {
                    throw new SmtpException('Niet-toegestane extra e-mailheader.');
                }
                $mailer->addCustomHeader($name, (string) $value);
            }

            if ($html !== null) {
                $mailer->isHTML(true);
                $mailer->Body = $html;
                $mailer->AltBody = $text ?? '';
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $text ?? '';
            }

            $mailer->send();

            return $mailer->getLastMessageID();
        } catch (PHPMailerException $e) {
            throw new SmtpException(
                "Versturen via SMTP-server {$account->smtpHost}:{$account->smtpPort} is mislukt ({$mailer->ErrorInfo})."
            );
        }
    }

    /**
     * @param string|string[]|null $value
     * @return string[]
     */
    private static function toArray(string|array|null $value): array
    {
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }
}
