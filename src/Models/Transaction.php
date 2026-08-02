<?php

namespace App\Models;

use App\Support\Database;

final class Transaction
{
    /**
     * Alle transacties van een periode, elk met lopend saldo. Start niet
     * vanaf een handmatige beginstand, maar vanaf de ontvangen inkomsten
     * min de betaalde vaste lasten en min wat er deze periode al in
     * potjes is gestort/opgenomen: zo eindigt de laatste rij op hetzelfde
     * "verwachte saldo kasstroom" als BudgetPeriod::endingBalance().
     */
    public static function forPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*, p.name AS pot_name, p.icon AS pot_icon
             FROM transactions t
             LEFT JOIN pots p ON p.id = t.pot_id
             WHERE t.period_id = :period_id ORDER BY t.sort_order, t.id'
        );
        $stmt->execute(['period_id' => $periodId]);
        $rows = $stmt->fetchAll();

        $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(amount), 0) FROM pot_transactions WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $potSum = (float) $stmt->fetchColumn();

        $running = (float) IncomeItem::totals($periodId)['actual'] - (float) FixedCost::totals($periodId)['actual'] - $potSum;
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

    public static function create(int $periodId, string $date, string $description, float $amount, bool $isSettled, ?int $potId = null): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM transactions WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO transactions (period_id, txn_date, description, amount, is_settled, sort_order, pot_id)
             VALUES (:period_id, :date, :description, :amount, :settled, :sort_order, :pot_id)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'settled' => $isSettled ? 1 : 0,
            'sort_order' => $sortOrder,
            'pot_id' => $potId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $date, string $description, float $amount, bool $isSettled, ?int $potId = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE transactions SET txn_date = :date, description = :description, amount = :amount, is_settled = :settled, pot_id = :pot_id WHERE id = :id'
        );
        $stmt->execute([
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'settled' => $isSettled ? 1 : 0,
            'pot_id' => $potId,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
