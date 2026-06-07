<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\OneSignalSettings;

final class AdminController extends Controller
{
    public function show(): void
    {
        $this->admin();
        $oneSignal = new OneSignalSettings();
        view('admin/index', [
            'title' => 'Admin',
            'onesignal_app_id' => $oneSignal->appId(),
            'onesignal_configured' => $oneSignal->isConfigured(),
        ]);
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
