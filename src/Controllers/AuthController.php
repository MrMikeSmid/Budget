<?php

namespace App\Controllers;

use App\Models\User;
use App\Support\Auth;
use App\Support\View;

final class AuthController
{
    public static function showSetup(): void
    {
        if (User::count() > 0) {
            header('Location: ' . View::url('login'));
            exit;
        }

        View::render('auth/setup', [], 'layout-guest');
    }

    public static function setup(): void
    {
        if (User::count() > 0) {
            header('Location: ' . View::url('login'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < 8) {
            View::flash('Vul een naam, e-mailadres en een wachtwoord van minimaal 8 tekens in.', 'error');
            header('Location: ' . View::url('setup'));
            exit;
        }

        User::create($name, $email, $password);
        Auth::attempt($email, $password);

        header('Location: ' . View::url('dashboard'));
        exit;
    }

    public static function showLogin(): void
    {
        if (User::count() === 0) {
            header('Location: ' . View::url('setup'));
            exit;
        }

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

        if (Auth::attempt($email, $password)) {
            header('Location: ' . View::url('dashboard'));
            exit;
        }

        View::flash('E-mailadres of wachtwoord onjuist.', 'error');
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
