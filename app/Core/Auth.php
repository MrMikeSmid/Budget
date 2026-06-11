<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['authenticated_at'] = time();
        self::refreshSessionCookie();
        (new User())->recordLogin((int) $user['id']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        if (self::sessionExpiresAt() <= time()) {
            unset($_SESSION['user_id'], $_SESSION['authenticated_at']);
            session_regenerate_id(true);
            flash('info', 'Je sessie van 48 uur is verlopen. Log opnieuw in om verder te gaan.');
            return null;
        }

        return (new User())->find((int) $id);
    }

    public static function sessionExpiresAt(): ?int
    {
        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
        if ($authenticatedAt <= 0) {
            return null;
        }

        return $authenticatedAt + (int) config('session_lifetime', 48 * 60 * 60);
    }

    public static function sessionWarningStartsAt(): ?int
    {
        $expiresAt = self::sessionExpiresAt();
        if ($expiresAt === null) {
            return null;
        }

        return $expiresAt - (int) config('session_warning_time', 2 * 60 * 60);
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user ??= self::user();
        return $user !== null && (int) ($user['is_admin'] ?? 0) === 1;
    }

    private static function refreshSessionCookie(): void
    {
        if (!ini_get('session.use_cookies') || headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + (int) config('session_lifetime', 48 * 60 * 60),
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}
