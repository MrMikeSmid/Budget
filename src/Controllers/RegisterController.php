<?php

namespace App\Controllers;

use App\Models\EmailVerification;
use App\Models\Household;
use App\Models\User;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Mailer;
use App\Support\View;

final class RegisterController
{
    public static function showRegister(): void
    {
        if (Auth::check()) {
            header('Location: ' . View::url('dashboard'));
            exit;
        }

        View::render('auth/register', [], 'layout-guest');
    }

    public static function register(): void
    {
        if (Auth::check()) {
            header('Location: ' . View::url('dashboard'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < 8) {
            View::flash('Vul een naam, e-mailadres en een wachtwoord van minimaal 8 tekens in.', 'error');
            header('Location: ' . View::url('registreren'));
            exit;
        }

        if (User::findByEmail($email)) {
            View::flash('Er bestaat al een account met dit e-mailadres. Log in of gebruik "wachtwoord vergeten".', 'error');
            header('Location: ' . View::url('registreren'));
            exit;
        }

        $userId = User::create($name, $email, $password);
        Household::createWithOwner('Huishouden van ' . $name, $userId, Config::get()['households_dir']);

        self::sendVerificationEmail($userId, $email);
    }

    /**
     * Verstuurt de verificatiemail en toont daarna altijd een "check je
     * e-mail"-pagina. Lukt het versturen niet (geen SMTP geconfigureerd, of
     * verzenden faalt) dan toont die pagina de link zelf, zodat registreren
     * nooit geblokkeerd wordt door een ontbrekende mailconfiguratie.
     */
    public static function sendVerificationEmail(int $userId, string $email): void
    {
        $token = EmailVerification::issue($userId);
        $verifyUrl = View::absoluteUrl('verifieer-email', ['token' => $token]);

        $sent = Mailer::trySend(
            $email,
            'Bevestig je e-mailadres - Budgetapp',
            "<p>Welkom bij Budgetapp!</p><p><a href=\"{$verifyUrl}\">Klik hier om je e-mailadres te bevestigen</a>.</p>",
            "Welkom bij Budgetapp!\n\nBevestig je e-mailadres via deze link:\n{$verifyUrl}"
        );

        View::render('auth/verify-notice', [
            'email' => $email,
            'mailSent' => $sent,
            'verifyUrl' => $verifyUrl,
        ], 'layout-guest');
    }
}
