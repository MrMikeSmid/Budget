<?php

namespace App\Controllers;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invite;
use App\Models\User;
use App\Support\Auth;
use App\Support\Mailer;
use App\Support\View;

final class InviteController
{
    /**
     * Vanuit een huishouden iemand uitnodigen (elk lid mag dit — past bij de
     * "geen rollen"-filosofie). Wordt aangeroepen binnen een authed() route,
     * dus $_SESSION['household_id'] is deze request al gevalideerd.
     */
    public static function send(): void
    {
        $email = trim($_POST['email'] ?? '');
        $householdId = (int) $_SESSION['household_id'];
        $user = Auth::user();

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            View::flash('Vul een geldig e-mailadres in.', 'error');
            header('Location: ' . View::url('huishouden'));
            exit;
        }

        $existingUser = User::findByEmail($email);
        if ($existingUser && HouseholdMember::isMember($householdId, (int) $existingUser['id'])) {
            View::flash('Dit e-mailadres is al lid van dit huishouden.', 'error');
            header('Location: ' . View::url('huishouden'));
            exit;
        }

        $token = Invite::create($householdId, $email, (int) $user['id']);
        $acceptUrl = View::absoluteUrl('uitnodiging', ['token' => $token]);
        $household = Household::find($householdId);

        $sent = Mailer::trySend(
            $email,
            $user['name'] . ' nodigt je uit voor Budgetapp',
            "<p>{$user['name']} nodigt je uit om samen '{$household['name']}' te beheren in Budgetapp.</p><p><a href=\"{$acceptUrl}\">Klik hier om de uitnodiging te accepteren</a>.</p>",
            "{$user['name']} nodigt je uit om samen '{$household['name']}' te beheren in Budgetapp.\n\nAccepteer via deze link:\n{$acceptUrl}"
        );

        if ($sent) {
            View::flash('Uitnodiging verstuurd naar ' . $email . '.');
        } else {
            View::flash('Uitnodiging aangemaakt, maar de mail kon niet verstuurd worden. Deel deze link zelf: ' . $acceptUrl);
        }

        header('Location: ' . View::url('huishouden'));
        exit;
    }

    public static function showAccept(): void
    {
        $token = $_GET['token'] ?? '';
        $invite = $token !== '' ? Invite::findByToken($token) : null;

        [$state, $existingUser] = self::resolveInviteState($invite);
        $household = $invite ? Household::find((int) $invite['household_id']) : null;

        View::render('invites/accept', [
            'token' => $token,
            'invite' => $invite,
            'household' => $household,
            'state' => $state,
            'existingUser' => $existingUser,
        ], 'layout-guest');
    }

    public static function acceptViaLogin(): void
    {
        $token = $_POST['token'] ?? '';
        $invite = $token !== '' ? Invite::findByToken($token) : null;
        [$state] = self::resolveInviteState($invite);

        if ($state !== 'login') {
            header('Location: ' . View::url('uitnodiging', ['token' => $token]));
            exit;
        }

        $password = $_POST['password'] ?? '';
        $result = Auth::attempt($invite['email'], $password);

        if ($result === Auth::RESULT_UNVERIFIED) {
            View::flash('Bevestig eerst je e-mailadres via de link die we je bij het registreren gestuurd hebben voor je deze uitnodiging kan accepteren.', 'error');
            header('Location: ' . View::url('uitnodiging', ['token' => $token]));
            exit;
        }

        if ($result !== Auth::RESULT_OK) {
            View::flash('Wachtwoord onjuist.', 'error');
            header('Location: ' . View::url('uitnodiging', ['token' => $token]));
            exit;
        }

        $user = Auth::user();
        HouseholdMember::add((int) $invite['household_id'], (int) $user['id']);
        Invite::markAccepted((int) $invite['id']);

        View::flash('Je bent toegevoegd aan het huishouden.');
        header('Location: ' . View::url('dashboard'));
        exit;
    }

    public static function acceptViaRegister(): void
    {
        $token = $_POST['token'] ?? '';
        $invite = $token !== '' ? Invite::findByToken($token) : null;
        [$state] = self::resolveInviteState($invite);

        if ($state !== 'register') {
            header('Location: ' . View::url('uitnodiging', ['token' => $token]));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || strlen($password) < 8) {
            View::flash('Vul een naam en een wachtwoord van minimaal 8 tekens in.', 'error');
            header('Location: ' . View::url('uitnodiging', ['token' => $token]));
            exit;
        }

        $userId = User::create($name, $invite['email'], $password);
        // Lidmaatschap meteen vastleggen, ook al moet de gebruiker nog
        // e-mailverificatie afronden — zo verloopt een trage verificatie de
        // uitnodiging niet en hoeft de link niet opnieuw gebruikt te worden.
        HouseholdMember::add((int) $invite['household_id'], $userId);
        Invite::markAccepted((int) $invite['id']);

        RegisterController::sendVerificationEmail($userId, $invite['email']);
    }

    /**
     * @return array{0: string, 1: ?array} state = 'invalid'|'expired'|'accepted'|'login'|'register'
     */
    private static function resolveInviteState(?array $invite): array
    {
        if (!$invite) {
            return ['invalid', null];
        }
        if ($invite['accepted_at'] !== null) {
            return ['accepted', null];
        }
        if (Invite::isExpired($invite)) {
            return ['expired', null];
        }

        $existingUser = User::findByEmail($invite['email']);

        return [$existingUser ? 'login' : 'register', $existingUser];
    }
}
