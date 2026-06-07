<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

final class AuthController extends Controller
{
    public function show(): void
    {
        if (current_user()) { redirect('/'); }
        view('auth/login', ['title' => 'Samen beginnen', 'step' => 'email']);
    }

    public function identify(): void
    {
        $this->verifyCsrf();
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            view('auth/login', ['title' => 'Samen beginnen', 'step' => 'email', 'error' => 'Vul een geldig e-mailadres in.', 'email' => $email]);
            return;
        }
        $user = (new User())->findOrCreate($email);
        if ($user['password_hash']) {
            $_SESSION['pending_user_id'] = $user['id'];
            view('auth/login', ['title' => 'Welkom terug', 'step' => 'password', 'email' => $email]);
            return;
        }
        Auth::login($user);
        flash('welcome', 'Welkom! Je kunt meteen samen aan de slag.');
        redirect('/');
    }

    public function password(): void
    {
        $this->verifyCsrf();
        $id = (int) ($_SESSION['pending_user_id'] ?? 0);
        $user = (new User())->find($id);
        $password = (string) ($_POST['password'] ?? '');
        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            view('auth/login', ['title' => 'Welkom terug', 'step' => 'password', 'email' => $user['email'] ?? '', 'error' => 'Dat wachtwoord klopt niet. Probeer het opnieuw.']);
            return;
        }
        unset($_SESSION['pending_user_id']);
        Auth::login($user);
        redirect('/');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        header('Location: ' . url('/login'));
        exit;
    }
}
