<?php

namespace App\Support;

use App\Models\User;

final class Auth
{
    public const RESULT_OK = 'ok';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_UNVERIFIED = 'unverified';

    public static function attempt(string $email, string $password): string
    {
        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return self::RESULT_INVALID;
        }

        if (!User::isVerified($user)) {
            return self::RESULT_UNVERIFIED;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['household_id']);

        return self::RESULT_OK;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return User::find((int) $_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . View::url('login'));
            exit;
        }
    }
}
