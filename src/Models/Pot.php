<?php

namespace App\Models;

use App\Support\Database;

final class Pot
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT p.*, bp.name AS linked_period_name
             FROM pots p
             LEFT JOIN budget_periods bp ON bp.id = p.linked_period_id
             ORDER BY p.sort_order, p.id'
        );
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['resolved_amount'] = $row['linked_period_id']
                ? BudgetPeriod::endingBalance((int) $row['linked_period_id'])
                : (float) $row['amount'];
        }

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pots WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(string $name, string $icon, ?float $amount, string $note, ?int $linkedPeriodId): int
    {
        $pdo = Database::connection();

        $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM pots')->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO pots (name, icon, amount, note, linked_period_id, sort_order)
             VALUES (:name, :icon, :amount, :note, :linked_period_id, :sort_order)'
        );
        $stmt->execute([
            'name' => $name,
            'icon' => $icon,
            'amount' => $linkedPeriodId ? null : $amount,
            'note' => $note,
            'linked_period_id' => $linkedPeriodId ?: null,
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $icon, ?float $amount, string $note, ?int $linkedPeriodId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pots SET name = :name, icon = :icon, amount = :amount, note = :note, linked_period_id = :linked_period_id WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'icon' => $icon,
            'amount' => $linkedPeriodId ? null : $amount,
            'note' => $note,
            'linked_period_id' => $linkedPeriodId ?: null,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM pots WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
