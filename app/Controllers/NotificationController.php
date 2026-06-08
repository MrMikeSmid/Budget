<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\FirebaseSettings;
use App\Services\PushNotificationService;
use App\Services\PushSubscriptionService;
use JsonException;

final class NotificationController extends Controller
{
    public function show(): void
    {
        $user = $this->admin();
        $settings = new FirebaseSettings();
        view('admin/notifications', [
            'title' => 'Notificatietest',
            'firebase' => $settings,
            'firebase_public_config' => $settings->publicConfig(),
            'subscriptions' => (new PushSubscriptionService())->forUser((int) $user['id']),
        ]);
    }

    public function updateSettings(): void
    {
        $this->admin();
        $this->verifyCsrf();
        $serviceAccount = trim((string) ($_POST['service_account_json'] ?? ''));
        $clearServiceAccount = ($_POST['clear_service_account'] ?? '') === '1';

        if ($serviceAccount !== '') {
            try {
                $decoded = json_decode($serviceAccount, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                flash('error', 'Het serviceaccount is geen geldige JSON-export.');
                redirect('/admin/notifications');
            }
            if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
                flash('error', 'De JSON mist client_email of private_key. Gebruik een Firebase-serviceaccount.');
                redirect('/admin/notifications');
            }
        }

        (new FirebaseSettings())->save([
            'project_id' => $_POST['project_id'] ?? '',
            'api_key' => $_POST['api_key'] ?? '',
            'messaging_sender_id' => $_POST['messaging_sender_id'] ?? '',
            'app_id' => $_POST['app_id'] ?? '',
            'vapid_public_key' => $_POST['vapid_public_key'] ?? '',
        ], $clearServiceAccount ? '' : ($serviceAccount === '' ? null : $serviceAccount));

        flash('success', 'De Firebase-instellingen zijn opgeslagen.');
        redirect('/admin/notifications');
    }

    public function subscribe(): void
    {
        $user = $this->admin();
        $this->verifyCsrf();
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($token === '' || mb_strlen($token) > 4096) {
            $this->json(['ok' => false, 'message' => 'Firebase gaf geen geldig apparaattoken terug.'], 422);
            return;
        }
        (new PushSubscriptionService())->save((int) $user['id'], $token, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $this->json(['ok' => true, 'message' => 'Dit apparaat is klaar voor een testmelding.']);
    }

    public function unsubscribe(): void
    {
        $user = $this->admin();
        $this->verifyCsrf();
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($token !== '') {
            (new PushSubscriptionService())->delete((int) $user['id'], $token);
        }
        $this->json(['ok' => true, 'message' => 'Het lokale apparaattoken is verwijderd.']);
    }

    public function sendTest(): void
    {
        $user = $this->admin();
        $this->verifyCsrf();
        $subscriptionId = filter_var($_POST['subscription_id'] ?? null, FILTER_VALIDATE_INT);
        $message = trim((string) ($_POST['message'] ?? ''));
        if (!$subscriptionId || $message === '' || mb_strlen($message) > 500) {
            flash('error', 'Kies een apparaat en vul een testbericht van maximaal 500 tekens in.');
            redirect('/admin/notifications#testmelding');
        }

        $subscription = (new PushSubscriptionService())->findForUser((int) $user['id'], (int) $subscriptionId);
        if (!$subscription) {
            flash('error', 'Het gekozen apparaattoken bestaat niet meer.');
            redirect('/admin/notifications#testmelding');
        }

        $push = new PushNotificationService();
        if ($push->sendToken($subscription['token'], $message, '/admin/notifications')) {
            flash('success', 'Firebase heeft de testmelding geaccepteerd.');
        } else {
            flash('error', $push->lastError() ?? 'De testmelding kon niet worden verstuurd.');
        }
        redirect('/admin/notifications#testmelding');
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
