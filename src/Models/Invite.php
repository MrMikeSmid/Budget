<?php

namespace App\Models;

use App\Support\AppDatabase;

final class Invite
{
    private const TTL_DAYS = 7;

    public static function create(int $householdId, string $email, int $invitedByUserId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400);

        $stmt = AppDatabase::connection()->prepare(
            'INSERT INTO invites (household_id, email, token, invited_by_user_id, expires_at)
             VALUES (:household_id, :email, :token, :invited_by_user_id, :expires_at)'
        );
        $stmt->execute([
            'household_id' => $householdId,
            'email' => $email,
            'token' => $token,
            'invited_by_user_id' => $invitedByUserId,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Haalt een uitnodiging op ongeacht status — de aanroeper bepaalt zelf of
     * verlopen/al-geaccepteerd apart getoond moet worden i.p.v. gewoon "niet
     * gevonden", zodat iemand die per ongeluk twee keer op de link klikt een
     * zinnig bericht krijgt.
     */
    public static function findByToken(string $token): ?array
    {
        $stmt = AppDatabase::connection()->prepare('SELECT * FROM invites WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $invite = $stmt->fetch();

        return $invite ?: null;
    }

    public static function isExpired(array $invite): bool
    {
        return strtotime($invite['expires_at']) < time();
    }

    public static function markAccepted(int $id): void
    {
        $stmt = AppDatabase::connection()->prepare(
            "UPDATE invites SET accepted_at = datetime('now') WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public static function pendingForHousehold(int $householdId): array
    {
        $stmt = AppDatabase::connection()->prepare(
            "SELECT * FROM invites WHERE household_id = :household_id AND accepted_at IS NULL
             ORDER BY created_at DESC"
        );
        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }
}
