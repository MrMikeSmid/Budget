<?php

namespace App\Models;

use App\Support\Database;

/**
 * Gedeelde basis voor income_items en fixed_costs: beide zijn een lijst
 * regels per periode met omschrijving, begroot/werkelijk bedrag en status.
 */
abstract class LineItem
{
    abstract protected static function table(): string;

    public static function forPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM ' . static::table() . ' WHERE period_id = :period_id ORDER BY sort_order, id'
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ' . static::table() . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $periodId, string $description, float $budgeted, ?float $actual, string $status): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ' . static::table() . ' WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO ' . static::table() . ' (period_id, description, budgeted, actual, status, sort_order)
             VALUES (:period_id, :description, :budgeted, :actual, :status, :sort_order)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $description, float $budgeted, ?float $actual, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE ' . static::table() . ' SET description = :description, budgeted = :budgeted, actual = :actual, status = :status WHERE id = :id'
        );
        $stmt->execute([
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM ' . static::table() . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function totals(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(budgeted), 0) AS budgeted, COALESCE(SUM(actual), 0) AS actual
             FROM ' . static::table() . ' WHERE period_id = :period_id'
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetch();
    }
}
