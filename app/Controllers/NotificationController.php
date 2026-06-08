<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\BeamsSettings;
use App\Services\PushNotificationService;
use App\Services\PushSubscriptionService;

final class NotificationController extends Controller
{
    public function show(): void
    {
        $user = $this->admin();
        $settings = new BeamsSettings();
        view('admin/notifications', [
            'title' => 'Notificatietest',
            'beams' => $settings,
            'subscriptions' => (new PushSubscriptionService())->forUser((int) $user['id']),
        ]);
    }

    public function updateSettings(): void
    {
        $this->admin();
        $this->verifyCsrf();
        $instanceId = trim((string) ($_POST['instance_id'] ?? ''));
        $secretKey = trim((string) ($_POST['secret_key'] ?? ''));
        $clearSecret = ($_POST['clear_secret_key'] ?? '') === '1';
        if (preg_match('/^[0-9a-f-]{36}$/i', $instanceId) !== 1) {
            flash('error', 'De Instance ID is ongeldig. Kopieer de volledige waarde uit Pusher Beams → Credentials.');
            redirect('/admin/notifications');
        }
        (new BeamsSettings())->save(
            $instanceId,
            $clearSecret ? '' : ($secretKey !== '' ? $secretKey : null),
        );
        flash('success', 'De Pusher Beams-instellingen zijn opgeslagen.');
        redirect('/admin/notifications');
    }

    public function subscribe(): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $interest = trim((string) ($_POST['token'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_\-=@,.;]{1,164}$/', $interest)) {
            $this->json(['ok' => false, 'message' => 'Pusher Beams gaf geen geldige apparaatregistratie terug.'], 422);
        }
        (new PushSubscriptionService())->save((int) $user['id'], $interest, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $this->json(['ok' => true, 'message' => 'Meldingen zijn op dit apparaat geactiveerd. Je ontvangt nu updates van gedeelde lijstjes.']);
    }

    public function unsubscribe(): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $interest = trim((string) ($_POST['token'] ?? ''));
        if ($interest !== '') {
            (new PushSubscriptionService())->delete((int) $user['id'], $interest);
        }
        $this->json(['ok' => true, 'message' => 'Dit apparaat is afgemeld.']);
    }

    public function sendTest(): void
    {
        $user = $this->admin();
        $this->verifyCsrf();
        $subscriptionId = filter_input(INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT);
        $message = trim((string) ($_POST['message'] ?? ''));
        if (!$subscriptionId || $message === '') {
            flash('error', 'Kies een apparaat en vul een bericht in.');
            redirect('/admin/notifications#testmelding');
        }
        $subscription = (new PushSubscriptionService())->findForUser((int) $user['id'], (int) $subscriptionId);
        if (!$subscription) {
            flash('error', 'Dit apparaat is niet gevonden.');
            redirect('/admin/notifications#testmelding');
        }
        $push = new PushNotificationService();
        if ($push->sendToken($subscription['token'], $message, '/admin/notifications')) {
            flash('success', 'Pusher Beams heeft de testmelding geaccepteerd.');
        } else {
            flash('error', $push->lastError() ?? 'De testmelding kon niet worden verstuurd.');
        }
        redirect('/admin/notifications#testmelding');
    }
}
