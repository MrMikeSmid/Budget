<?php

namespace App\Models;

use App\Support\Database;

final class PotTransaction
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pot_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * $transferPotId wordt alleen gezet bij een overboeking tussen twee
     * potjes: dan bevat het de id van het andere potje in die overboeking,
     * zodat deze rij (die het losse saldo per saldo niet raakt) apart
     * herkenbaar is van een gewone storting/opname op/van het losse saldo.
     */
    public static function create(int $potId, ?int $userId, ?string $userName, ?int $periodId, string $date, string $description, float $amount, ?int $transferPotId = null): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO pot_transactions (pot_id, user_id, user_name, period_id, txn_date, description, amount, transfer_pot_id)
             VALUES (:pot_id, :user_id, :user_name, :period_id, :date, :description, :amount, :transfer_pot_id)'
        );
        $stmt->execute([
            'pot_id' => $potId,
            'user_id' => $userId,
            'user_name' => $userName,
            'period_id' => $periodId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'transfer_pot_id' => $transferPotId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM pot_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
