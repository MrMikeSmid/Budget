<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

final class AuthController extends Controller
{
    public function showSetup(): void
    {
        if ((new User())->exists()) {
            redirect('/login');
        }
        if (current_user()) {
            redirect('/');
        }
        view('auth/setup', ['title' => 'Account instellen']);
    }

    public function setup(): void
    {
        $this->verifyCsrf();
        $users = new User();
        if ($users->exists()) {
            redirect('/login');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '' || mb_strlen($name) > 60) {
            $this->setupError('Vul je naam in.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setupError('Vul een geldig e-mailadres in.');
        }
        if (mb_strlen($password) < 8) {
            $this->setupError('Kies een wachtwoord van minimaal 8 tekens.');
        }
        if ($password !== $confirmation) {
            $this->setupError('De wachtwoorden zijn niet hetzelfde.');
        }

        $id = $users->create($email, $name, $password);
        $user = $users->find($id);
        Auth::login($user);
        redirect('/');
    }

    public function show(): void
    {
        if (!(new User())->exists()) {
            redirect('/setup');
        }
        if (current_user()) {
            redirect('/');
        }
        view('auth/login', ['title' => 'Inloggen']);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $users = new User();
        $user = $users->findByEmail($email);

        if (!$user || !$users->verifyPassword($user, $password)) {
            view('auth/login', ['title' => 'Inloggen', 'error' => 'E-mailadres of wachtwoord klopt niet.', 'email' => $email]);
            return;
        }

        Auth::login($user);
        redirect('/');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        $this->auth();
        Auth::logout();
        redirect('/login');
    }

    private function setupError(string $message): never
    {
        flash('error', $message);
        redirect('/setup');
    }
}
