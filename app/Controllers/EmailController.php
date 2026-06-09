<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\InvitationEmailSettings;
use App\Services\SmtpSettings;
use App\Services\SmtpTransport;

final class EmailController extends Controller
{
    public function show(): void
    {
        $user = $this->admin();
        $smtp = new SmtpSettings();
        $invitationEmail = new InvitationEmailSettings();

        view('admin/email', [
            'title' => 'E-mailinstellingen',
            'user' => $user,
            'smtp' => $smtp,
            'invitation_sender_name' => $invitationEmail->senderName(),
            'invitation_sender_email' => $invitationEmail->senderEmail(),
            'invitation_message_html' => $invitationEmail->message(),
            'invitation_preview_html' => $invitationEmail->renderEmail(
                ['name' => 'Mike', 'email' => 'mike@voorbeeld.nl'],
                ['id' => 1, 'title' => 'Weekendje weg'],
                'vriend@voorbeeld.nl'
            ),
            'invitation_tokens' => InvitationEmailSettings::tokens(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->admin();
        $this->verifyCsrf();

        $host = trim((string) ($_POST['smtp_host'] ?? ''));
        $port = filter_var($_POST['smtp_port'] ?? null, FILTER_VALIDATE_INT);
        $encryption = (string) ($_POST['smtp_encryption'] ?? '');
        $username = trim((string) ($_POST['smtp_username'] ?? ''));
        $password = (string) ($_POST['smtp_password'] ?? '');
        $timeout = filter_var($_POST['smtp_timeout'] ?? null, FILTER_VALIDATE_INT);
        $clearPassword = isset($_POST['clear_smtp_password']);

        if ($host === '' || mb_strlen($host) > 255 || preg_match('/^[a-z0-9.-]+$/i', $host) !== 1) {
            flash('error', 'Vul een geldige SMTP-hostnaam in.');
            redirect('/admin/email#smtp');
        }
        if ($port === false || $port < 1 || $port > 65535) {
            flash('error', 'Vul een geldige SMTP-poort tussen 1 en 65535 in.');
            redirect('/admin/email#smtp');
        }
        if (!in_array($encryption, ['starttls', 'tls', 'none'], true)) {
            flash('error', 'Kies een geldige verbindingsbeveiliging.');
            redirect('/admin/email#smtp');
        }
        if (mb_strlen($username) > 255 || preg_match('/[\r\n]/', $username) === 1) {
            flash('error', 'De SMTP-gebruikersnaam is ongeldig.');
            redirect('/admin/email#smtp');
        }
        if ($timeout === false || $timeout < 5 || $timeout > 60) {
            flash('error', 'Kies een time-out tussen 5 en 60 seconden.');
            redirect('/admin/email#smtp');
        }

        (new SmtpSettings())->save($host, $port, $encryption, $username, $password, $timeout, $clearPassword);
        flash('success', 'De SMTP-instellingen zijn opgeslagen. Stuur nu een testmail om de verbinding te controleren.');
        redirect('/admin/email#smtp');
    }

    public function sendTest(): void
    {
        $user = $this->admin();
        $this->verifyCsrf();
        $recipient = mb_strtolower(trim((string) ($_POST['test_email'] ?? '')));

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $recipient) === 1) {
            flash('error', 'Vul een geldig e-mailadres voor de testmail in.');
            redirect('/admin/email#testmail');
        }

        $email = new InvitationEmailSettings();
        $senderName = str_replace(["\r", "\n"], '', $email->senderName());
        $senderEmail = str_replace(["\r", "\n"], '', $email->senderEmail());
        $transport = new SmtpTransport();
        $sent = $transport->send(
            $recipient,
            (string) config('name') . ': SMTP-test geslaagd',
            $this->testMessage($user),
            [
                'From' => mb_encode_mimeheader($senderName, 'UTF-8') . ' <' . $senderEmail . '>',
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Mailer' => (string) config('name'),
            ]
        );

        if ($sent) {
            flash('success', 'De SMTP-testmail is verstuurd naar ' . $recipient . '. Controleer ook de spammap en de berichtheaders.');
        } else {
            flash('error', $transport->lastError() ?? 'De SMTP-testmail kon niet worden verstuurd.');
        }
        redirect('/admin/email#testmail');
    }

    private function testMessage(array $user): string
    {
        $name = e((string) ($user['name'] ?? $user['email'] ?? 'beheerder'));
        return '<!doctype html><html lang="nl"><body style="font-family:Arial,sans-serif;color:#242033">'
            . '<h1>SMTP werkt</h1><p>Hoi ' . $name . ',</p>'
            . '<p>Deze testmail is door Samen via de ingestelde SMTP-server verstuurd.</p>'
            . '<p><strong>Verstuurd op:</strong> ' . e(date('d-m-Y H:i:s T')) . '</p>'
            . '</body></html>';
    }
}
