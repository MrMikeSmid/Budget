<?php

namespace App\Models;

use App\Support\AppDatabase;

final class HouseholdMember
{
    public static function add(int $householdId, int $userId): void
    {
        $stmt = AppDatabase::connection()->prepare(
            'INSERT OR IGNORE INTO household_members (household_id, user_id) VALUES (:household_id, :user_id)'
        );
        $stmt->execute(['household_id' => $householdId, 'user_id' => $userId]);
    }

    public static function remove(int $householdId, int $userId): void
    {
        $stmt = AppDatabase::connection()->prepare(
            'DELETE FROM household_members WHERE household_id = :household_id AND user_id = :user_id'
        );
        $stmt->execute(['household_id' => $householdId, 'user_id' => $userId]);
    }

    public static function isMember(int $householdId, int $userId): bool
    {
        $stmt = AppDatabase::connection()->prepare(
            'SELECT 1 FROM household_members WHERE household_id = :household_id AND user_id = :user_id'
        );
        $stmt->execute(['household_id' => $householdId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    public static function count(int $householdId): int
    {
        $stmt = AppDatabase::connection()->prepare(
            'SELECT COUNT(*) FROM household_members WHERE household_id = :household_id'
        );
        $stmt->execute(['household_id' => $householdId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Alle huishoudens waar $userId lid van is, meest recent lidmaatschap
     * eerst — gebruikt om bij inloggen/elke request een geldig actief
     * huishouden te bepalen (nooit blind op de sessie vertrouwen).
     */
    public static function householdsFor(int $userId): array
    {
        $stmt = AppDatabase::connection()->prepare(
            'SELECT h.* FROM households h
             INNER JOIN household_members hm ON hm.household_id = h.id
             WHERE hm.user_id = :user_id
             ORDER BY hm.joined_at ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Alle leden van een huishouden (naam/e-mail), voor de ledenlijst-pagina.
     */
    public static function membersOf(int $householdId): array
    {
        $stmt = AppDatabase::connection()->prepare(
            'SELECT u.id, u.name, u.email, hm.joined_at FROM users u
             INNER JOIN household_members hm ON hm.user_id = u.id
             WHERE hm.household_id = :household_id
             ORDER BY hm.joined_at ASC'
        );
        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }
}
