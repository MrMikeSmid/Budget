<?php

namespace App\Models;

use App\Support\Auth;
use App\Support\Database;
use PDO;

/**
 * Logboek van alle mutaties in de app (aanmaken/wijzigen/verwijderen van
 * transacties, potjes, inkomsten, vaste lasten en periodes), met wie het
 * deed en wanneer. Basis voor de activiteiten-tijdlijn.
 */
final class Activity
{
    public static function log(string $category, string $description, ?float $amount = null): void
    {
        $user = Auth::user();

        $stmt = Database::connection()->prepare(
            'INSERT INTO activities (user_id, user_name, category, description, amount)
             VALUES (:user_id, :user_name, :category, :description, :amount)'
        );
        $stmt->execute([
            'user_id' => $user['id'] ?? null,
            'user_name' => $user['name'] ?? 'Onbekend',
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
        ]);
    }

    public static function recent(int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM activities ORDER BY occurred_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
