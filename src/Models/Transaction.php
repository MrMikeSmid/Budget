<?php

namespace App\Models;

use App\Support\Database;

final class Transaction
{
    /**
     * Alle transacties van een periode, elk met lopend saldo (D-kolom in de oude Excel).
     */
    public static function forPeriod(int $periodId): array
    {
        $period = BudgetPeriod::find($periodId);
        $stmt = Database::connection()->prepare(
            'SELECT * FROM transactions WHERE period_id = :period_id ORDER BY sort_order, id'
        );
        $stmt->execute(['period_id' => $periodId]);
        $rows = $stmt->fetchAll();

        $running = $period ? (float) $period['opening_balance'] : 0.0;
        foreach ($rows as &$row) {
            $running += (float) $row['amount'];
            $row['balance'] = $running;
        }

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $periodId, string $date, string $description, float $amount, bool $isSettled): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM transactions WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO transactions (period_id, txn_date, description, amount, is_settled, sort_order)
             VALUES (:period_id, :date, :description, :amount, :settled, :sort_order)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'settled' => $isSettled ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $date, string $description, float $amount, bool $isSettled): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE transactions SET txn_date = :date, description = :description, amount = :amount, is_settled = :settled WHERE id = :id'
        );
        $stmt->execute([
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'settled' => $isSettled ? 1 : 0,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
