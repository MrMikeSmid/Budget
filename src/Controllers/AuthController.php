<?php

namespace App\Controllers;

use App\Support\Auth;
use App\Support\View;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . View::url('dashboard'));
            exit;
        }

        View::render('auth/login', [], 'layout-guest');
    }

    public static function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = Auth::attempt($email, $password);

        if ($result === Auth::RESULT_OK) {
            header('Location: ' . View::url('dashboard'));
            exit;
        }

        if ($result === Auth::RESULT_UNVERIFIED) {
            View::flash('Bevestig eerst je e-mailadres via de link die we je gestuurd hebben voor je kan inloggen.', 'error');
        } else {
            View::flash('E-mailadres of wachtwoord onjuist.', 'error');
        }

        header('Location: ' . View::url('login'));
        exit;
    }

    public static function logout(): void
    {
        Auth::logout();
        header('Location: ' . View::url('login'));
        exit;
    }
}
