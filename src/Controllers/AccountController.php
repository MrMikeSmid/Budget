<?php

namespace App\Controllers;

use App\Models\User;
use App\Support\Auth;
use App\Support\View;

final class AccountController
{
    public static function index(): void
    {
        View::render('accounts/index', [
            'users' => User::all(),
            'currentUser' => Auth::user(),
        ]);
    }

    public static function create(): void
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < 8) {
            View::flash('Vul een naam, e-mailadres en een wachtwoord van minimaal 8 tekens in.', 'error');
        } elseif (User::findByEmail($email)) {
            View::flash('Er bestaat al een account met dit e-mailadres.', 'error');
        } else {
            User::create($name, $email, $password);
            View::flash('Account aangemaakt.');
        }

        header('Location: ' . View::url('accounts'));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $current = Auth::user();

        if ($id === (int) $current['id']) {
            View::flash('Je kunt je eigen account niet verwijderen.', 'error');
        } elseif (User::count() <= 1) {
            View::flash('Er moet minstens één account overblijven.', 'error');
        } else {
            User::delete($id);
            View::flash('Account verwijderd.');
        }

        header('Location: ' . View::url('accounts'));
        exit;
    }
}
