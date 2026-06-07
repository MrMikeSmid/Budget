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

    protected function verifyCsrf(): void
    {
        if (!hash_equals($_SESSION['_token'] ?? '', (string) ($_POST['_token'] ?? ''))) {
            http_response_code(419);
            exit('Je sessie is verlopen. Vernieuw de pagina en probeer opnieuw.');
        }
    }
}
