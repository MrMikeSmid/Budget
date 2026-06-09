<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\InvitationEmailSettings;

final class AdminController extends Controller
{
    public function show(): void
    {
        $this->admin();
        $invitationEmail = new InvitationEmailSettings();
        $sampleInviter = ['name' => 'Mike', 'email' => 'mike@voorbeeld.nl'];
        $sampleList = ['id' => 1, 'title' => 'Weekendje weg'];

        view('admin/index', [
            'title' => 'Admin',
            'invitation_sender_name' => $invitationEmail->senderName(),
            'invitation_sender_email' => $invitationEmail->senderEmail(),
            'invitation_message_html' => $invitationEmail->message(),
            'invitation_preview_html' => $invitationEmail->renderEmail($sampleInviter, $sampleList, 'vriend@voorbeeld.nl'),
            'invitation_tokens' => InvitationEmailSettings::tokens(),
        ]);
    }

    public function accounts(): void
    {
        $this->admin();

        view('admin/accounts', [
            'title' => 'Accounts',
            'accounts' => (new User())->allForAdmin(),
        ]);
    }

    public function events(): void
    {
        $this->admin();
        $search = trim((string) ($_GET['q'] ?? ''));
        $category = in_array($_GET['category'] ?? '', ['account', 'list', 'member', 'task'], true)
            ? (string) $_GET['category']
            : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        view('admin/events', [
            'title' => 'Gebeurtenissen',
            'audit' => (new AuditLog())->forAdmin($search, $category, $page),
            'search' => $search,
            'category' => $category,
        ]);
    }

    public function updateInvitationEmail(): void
    {
        $this->admin();
        $this->verifyCsrf();

        $senderName = trim((string) ($_POST['invitation_sender_name'] ?? ''));
        $senderEmail = mb_strtolower(trim((string) ($_POST['invitation_sender_email'] ?? '')));
        $message = trim((string) ($_POST['invitation_message_html'] ?? ''));

        if ($senderName === '' || mb_strlen($senderName) > 100 || preg_match('/[\r\n]/', $senderName) === 1) {
            flash('error', 'Vul een geldige afzendernaam van maximaal 100 tekens in.');
            redirect('/admin#uitnodigingsmail');
        }
        if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $senderEmail) === 1) {
            flash('error', 'Vul een geldig afzenderadres in.');
            redirect('/admin#uitnodigingsmail');
        }
        if ($message === '' || mb_strlen($message) > 20000) {
            flash('error', 'Vul een bericht van maximaal 20.000 tekens in.');
            redirect('/admin#uitnodigingsmail');
        }

        (new InvitationEmailSettings())->save($senderName, $senderEmail, $message);
        flash('success', 'De uitnodigingsmail is opgeslagen.');
        redirect('/admin#uitnodigingsmail');
    }

}
