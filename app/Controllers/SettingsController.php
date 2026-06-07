<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class SettingsController extends Controller
{
    public function show(): void
    {
        $user = $this->auth();
        view('settings/index', ['title' => 'Instellingen', 'user' => $user]);
    }

    public function profile(): void
    {
        $user = $this->auth(); $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) { flash('error', 'Vul een geldige naam in.'); redirect('/settings'); }
        (new User())->updateProfile((int) $user['id'], $name);
        flash('success', 'Je profiel is bijgewerkt.'); redirect('/settings');
    }

    public function password(): void
    {
        $user = $this->auth(); $this->verifyCsrf();
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (mb_strlen($password) < 8) { flash('error', 'Kies een wachtwoord van minimaal 8 tekens.'); redirect('/settings'); }
        if ($password !== $confirmation) { flash('error', 'De wachtwoorden zijn niet hetzelfde.'); redirect('/settings'); }
        (new User())->setPassword((int) $user['id'], $password);
        flash('success', 'Mooi! Je account is nu beveiligd met een wachtwoord.'); redirect('/settings');
    }
}
