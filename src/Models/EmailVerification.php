<?php

namespace App\Models;

use App\Support\AppDatabase;

final class EmailVerification
{
    private const TTL_HOURS = 48;

    public static function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600);

        $stmt = AppDatabase::connection()->prepare(
            'INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)'
        );
        $stmt->execute(['user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt]);

        return $token;
    }

    /**
     * Zoekt een nog geldig (niet verlopen, niet al gebruikt) token op. Geeft
     * bij succes de rij terug (met user_id) en consumeert het token meteen,
     * zodat het maar één keer bruikbaar is.
     */
    public static function consume(string $token): ?array
    {
        $pdo = AppDatabase::connection();
        $stmt = $pdo->prepare(
            "SELECT * FROM email_verifications
             WHERE token = :token AND consumed_at IS NULL AND expires_at > datetime('now')"
        );
        $stmt->execute(['token' => $token]);
        $verification = $stmt->fetch();

        if (!$verification) {
            return null;
        }

        $update = $pdo->prepare("UPDATE email_verifications SET consumed_at = datetime('now') WHERE id = :id");
        $update->execute(['id' => $verification['id']]);

        return $verification;
    }
}
