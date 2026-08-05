<?php

namespace App\Controllers;

use App\Models\Household;
use App\Models\Settings;
use App\Models\User;
use App\Support\View;

final class AdminController
{
    public static function index(): void
    {
        View::render('admin/index', [
            'users' => User::all(),
            'households' => Household::allWithMemberCounts(),
            'mail' => Settings::mailConfig(),
            'appUrl' => Settings::appUrl(),
        ]);
    }

    public static function saveSettings(): void
    {
        Settings::set('mail_host', trim($_POST['mail_host'] ?? ''));
        Settings::set('mail_port', trim($_POST['mail_port'] ?? ''));
        Settings::set('mail_encryption', trim($_POST['mail_encryption'] ?? ''));
        Settings::set('mail_username', trim($_POST['mail_username'] ?? ''));
        Settings::set('mail_from_address', trim($_POST['mail_from_address'] ?? ''));
        Settings::set('mail_from_name', trim($_POST['mail_from_name'] ?? ''));
        Settings::set('app_url', trim($_POST['app_url'] ?? ''));

        // Wachtwoordveld staat bewust altijd leeg in het formulier (nooit
        // teruggetoond); alleen overschrijven als er iets is ingevuld, zodat
        // "opslaan" zonder het wachtwoord opnieuw te typen het bestaande
        // wachtwoord niet wist.
        $password = trim($_POST['mail_password'] ?? '');
        if ($password !== '') {
            Settings::set('mail_password', $password);
        }

        View::flash('Instellingen opgeslagen.');
        header('Location: ' . View::url('admin'));
        exit;
    }

    public static function verifyUser(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $user = User::find($id);

        if (!$user) {
            View::flash('Gebruiker niet gevonden.', 'error');
        } else {
            User::markVerified($id);
            View::flash('Gebruiker ' . $user['email'] . ' is geverifieerd.');
        }

        header('Location: ' . View::url('admin'));
        exit;
    }
}
