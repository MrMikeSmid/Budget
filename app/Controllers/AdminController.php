<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\InvitationEmailSettings;
use App\Services\OneSignalSettings;
use App\Services\PushNotificationService;

final class AdminController extends Controller
{
    public function show(): void
    {
        $this->admin();
        $oneSignal = new OneSignalSettings();
        $invitationEmail = new InvitationEmailSettings();
        $sampleInviter = ['name' => 'Mike', 'email' => 'mike@voorbeeld.nl'];
        $sampleList = ['id' => 1, 'title' => 'Weekendje weg'];
        view('admin/index', [
            'title' => 'Admin',
            'onesignal_app_id' => $oneSignal->appId(),
            'onesignal_configured' => $oneSignal->isConfigured(),
            'invitation_sender_name' => $invitationEmail->senderName(),
            'invitation_sender_email' => $invitationEmail->senderEmail(),
            'invitation_message_html' => $invitationEmail->message(),
            'invitation_preview_html' => $invitationEmail->renderEmail($sampleInviter, $sampleList, 'vriend@voorbeeld.nl'),
            'invitation_tokens' => InvitationEmailSettings::tokens(),
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

    public function testPushNotification(): void
    {
        $user = $this->admin();
        $this->verifyCsrf();

        $push = new PushNotificationService();
        if ($push->send([(int) $user['id']], 'Je testmelding van Samen werkt.', '/settings')) {
            flash('success', 'De testmelding is door OneSignal geaccepteerd.');
        } else {
            flash('error', $push->lastError() ?? 'De testmelding kon niet worden verstuurd.');
        }
        redirect('/admin#pushnotificaties');
    }

    public function updateOneSignal(): void
    {
        $this->admin();
        $this->verifyCsrf();

        $appId = trim((string) ($_POST['onesignal_app_id'] ?? ''));
        $apiKey = trim((string) ($_POST['onesignal_api_key'] ?? ''));
        $clearApiKey = isset($_POST['clear_onesignal_api_key']);

        if ($appId !== '' && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $appId) !== 1) {
            flash('error', 'Vul een geldige OneSignal App ID in.');
            redirect('/admin');
        }

        if ($clearApiKey) {
            $apiKey = '';
        }

        (new OneSignalSettings())->save($appId, $apiKey === '' && !$clearApiKey ? null : $apiKey);
        flash('success', 'De OneSignal-instellingen zijn opgeslagen.');
        redirect('/admin');
    }
}
