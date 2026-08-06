<?php

namespace App\Controllers;

use App\Models\Household;
use App\Models\Settings;
use App\Models\User;
use App\Support\Auth;
use App\Support\DkimSigner;
use App\Support\Mailer;
use App\Support\View;

final class AdminController
{
    // Vaste, van cPanel's eigen (meestal "default") DKIM-selector
    // onderscheiden naam, zodat meerdere DKIM-records naast elkaar op
    // hetzelfde domein kunnen bestaan zonder te botsen.
    private const DKIM_SELECTOR = 'budgetapp';

    public static function index(): void
    {
        $mail = Settings::mailConfig();
        $dkim = Settings::dkimConfig();

        View::render('admin/index', [
            'users' => User::all(),
            'households' => Household::allWithMemberCounts(),
            'mail' => $mail,
            'appUrl' => Settings::appUrl(),
            'dkimDomain' => self::domainFromEmail($mail['from_address'] ?? ''),
            'dkimSelector' => $dkim['selector'] ?? null,
            'dkimPublicKeyDns' => $dkim !== null ? DkimSigner::publicKeyDnsFromPrivateKey($dkim['private_key']) : null,
        ]);
    }

    public static function generateDkim(): void
    {
        $keyPair = DkimSigner::generateKeyPair();

        if ($keyPair === null) {
            View::flash('Kon geen DKIM-sleutel genereren (openssl niet beschikbaar?).', 'error');
        } else {
            Settings::set('dkim_selector', self::DKIM_SELECTOR);
            Settings::set('dkim_private_key', $keyPair['private_key']);
            View::flash('DKIM-sleutel gegenereerd. Voeg de DNS-record hieronder toe om DKIM actief te maken.');
        }

        header('Location: ' . View::url('admin'));
        exit;
    }

    public static function removeDkim(): void
    {
        Settings::set('dkim_selector', null);
        Settings::set('dkim_private_key', null);

        View::flash('DKIM-ondertekening uitgeschakeld. Vergeet niet ook de DNS-record te verwijderen.');
        header('Location: ' . View::url('admin'));
        exit;
    }

    private static function domainFromEmail(string $email): string
    {
        return substr((string) strrchr($email, '@'), 1) ?: '';
    }

    public static function saveSettings(): void
    {
        self::persistSettingsFromRequest();

        View::flash('Instellingen opgeslagen.');
        header('Location: ' . View::url('admin'));
        exit;
    }

    /**
     * Slaat eerst de ingevulde velden op (zelfde als "Opslaan", zodat een
     * testmail altijd tegen de nieuwste invoer test, niet tegen wat toevallig
     * nog in de database stond) en verstuurt daarna een testmail naar het
     * eigen adres van de ingelogde admin.
     */
    public static function testSettings(): void
    {
        self::persistSettingsFromRequest();

        $admin = Auth::user();
        $result = Mailer::attempt(
            $admin['email'],
            'Testmail - Budgetapp',
            '<p>Dit is een testmail vanuit het admin-paneel van Budgetapp.</p><p>Kom je dit tegen? Dan werken je SMTP-instellingen.</p>',
            "Dit is een testmail vanuit het admin-paneel van Budgetapp.\n\nKom je dit tegen? Dan werken je SMTP-instellingen."
        );

        if ($result['ok']) {
            View::flash('Testmail verstuurd naar ' . $admin['email'] . '. Check je inbox (en spam-map) — komt er niks binnen, controleer dan de gegevens nog eens.');
        } else {
            View::flash('Testmail mislukt: ' . $result['error'], 'error');
        }

        header('Location: ' . View::url('admin'));
        exit;
    }

    private static function persistSettingsFromRequest(): void
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
        // opslaan/testen zonder het wachtwoord opnieuw te typen het
        // bestaande wachtwoord niet wist.
        $password = trim($_POST['mail_password'] ?? '');
        if ($password !== '') {
            Settings::set('mail_password', $password);
        }
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
