<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function auth(): array
    {
        $user = current_user();
        if (!$user) {
            flash('info', 'Log in om verder te gaan.');
            redirect('/login');
        }
        return $user;
    }

    protected function admin(): array
    {
        $user = $this->auth();
        if (!Auth::isAdmin($user)) {
            http_response_code(404);
            view('errors/404', ['title' => 'Pagina niet gevonden']);
            exit;
        }

        if (empty($user['password_hash'])) {
            flash('error', 'Beveilig je adminaccount eerst met een wachtwoord.');
            redirect('/settings#wachtwoord');
        }

        return $user;
    }

    protected function verifyCsrf(): void
    {
        if (!hash_equals($_SESSION['_token'] ?? '', (string) ($_POST['_token'] ?? ''))) {
            http_response_code(419);
            exit('Je sessie is verlopen. Vernieuw de pagina en probeer opnieuw.');
        }
    }

    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
