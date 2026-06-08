<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\NotificationSubscriptionService;
use App\Services\OneSignalNotificationService;
use App\Services\OneSignalSettings;

final class NotificationController extends Controller
{
    public function show(): void
    {
        $user = $this->admin();
        $settings = new OneSignalSettings();
        view('admin/notifications', [
            'title' => 'Notificatietest',
            'oneSignal' => $settings,
            'subscriptions' => (new NotificationSubscriptionService())->forUser((int) $user['id']),
        ]);
    }

    public function updateSettings(): void
    {
        $this->admin();
        $this->verifyCsrf();
        $appId = trim((string) ($_POST['app_id'] ?? ''));
        $restApiKey = trim((string) ($_POST['rest_api_key'] ?? ''));
        $clearKey = ($_POST['clear_rest_api_key'] ?? '') === '1';
        if (preg_match('/^[0-9a-f-]{36}$/i', $appId) !== 1) {
            flash('error', 'De App ID is ongeldig. Kopieer de volledige App ID uit OneSignal → Settings → Keys & IDs.');
            redirect('/admin/notifications');
        }
        (new OneSignalSettings())->save(
            $appId,
            $clearKey ? '' : ($restApiKey !== '' ? $restApiKey : null),
        );
        flash('success', 'De OneSignal-instellingen zijn opgeslagen.');
        redirect('/admin/notifications');
    }

    public function subscribe(): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $subscriptionId = trim((string) ($_POST['subscription_id'] ?? ''));
        if (preg_match('/^[0-9a-f-]{36}$/i', $subscriptionId) !== 1) {
            $this->json(['ok' => false, 'message' => 'OneSignal gaf geen geldige apparaatregistratie terug.'], 422);
        }
        (new NotificationSubscriptionService())->save((int) $user['id'], $subscriptionId, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $this->json(['ok' => true, 'message' => 'Meldingen zijn op dit apparaat geactiveerd. Je ontvangt nu updates van gedeelde lijstjes.']);
    }

    public function unsubscribe(): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $subscriptionId = trim((string) ($_POST['subscription_id'] ?? ''));
        if ($subscriptionId !== '') {
            (new NotificationSubscriptionService())->delete((int) $user['id'], $subscriptionId);
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
        $subscription = (new NotificationSubscriptionService())->findForUser((int) $user['id'], (int) $subscriptionId);
        if (!$subscription) {
            flash('error', 'Dit apparaat is niet gevonden.');
            redirect('/admin/notifications#testmelding');
        }
        $push = new OneSignalNotificationService();
        if ($push->sendSubscription($subscription['subscription_id'], $message, '/admin/notifications')) {
            flash('success', 'OneSignal heeft de testmelding geaccepteerd.');
        } else {
            flash('error', $push->lastError() ?? 'De testmelding kon niet worden verstuurd.');
        }
        redirect('/admin/notifications#testmelding');
    }
}
