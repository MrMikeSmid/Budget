<?php

namespace App\Models;

use App\Support\Database;

final class PotTransaction
{
    /**
     * Alle transacties van een potje, elk met lopend saldo (basisbedrag van
     * het potje + som van de mutaties tot en met die transactie).
     */
    public static function forPot(int $potId, float $openingBalance): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pt.*, u.name AS user_name
             FROM pot_transactions pt
             LEFT JOIN users u ON u.id = pt.user_id
             WHERE pt.pot_id = :pot_id ORDER BY pt.txn_date, pt.id'
        );
        $stmt->execute(['pot_id' => $potId]);
        $rows = $stmt->fetchAll();

        $running = $openingBalance;
        foreach ($rows as &$row) {
            $running += (float) $row['amount'];
            $row['balance'] = $running;
        }

        return $rows;
    }

    public static function sumForPot(int $potId): float
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM pot_transactions WHERE pot_id = :pot_id'
        );
        $stmt->execute(['pot_id' => $potId]);

        return (float) $stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pot_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $potId, ?int $userId, string $date, string $description, float $amount): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO pot_transactions (pot_id, user_id, txn_date, description, amount)
             VALUES (:pot_id, :user_id, :date, :description, :amount)'
        );
        $stmt->execute([
            'pot_id' => $potId,
            'user_id' => $userId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $date, string $description, float $amount): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pot_transactions SET txn_date = :date, description = :description, amount = :amount WHERE id = :id'
        );
        $stmt->execute([
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM pot_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
