<?php

namespace App\Models;

use App\Support\Database;

final class Pot
{
    public const TYPES = ['leefpotje' => 'Leefpotje', 'spaarpotje' => 'Spaarpotje'];

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
            $base = $row['linked_period_id']
                ? BudgetPeriod::endingBalance((int) $row['linked_period_id'])
                : (float) $row['amount'];
            $row['base_amount'] = $base;
            $row['resolved_amount'] = $base + PotTransaction::sumForPot((int) $row['id']);
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

    /**
     * Eén potje met dezelfde afgeleide velden (base_amount/resolved_amount)
     * als all(), voor de detailpagina met transacties.
     */
    public static function withDetails(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, bp.name AS linked_period_name
             FROM pots p
             LEFT JOIN budget_periods bp ON bp.id = p.linked_period_id
             WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $base = $row['linked_period_id']
            ? BudgetPeriod::endingBalance((int) $row['linked_period_id'])
            : (float) $row['amount'];
        $row['base_amount'] = $base;
        $row['resolved_amount'] = $base + PotTransaction::sumForPot((int) $row['id']);

        return $row;
    }

    public static function create(string $name, string $icon, ?float $amount, string $note, ?int $linkedPeriodId, string $type = 'leefpotje'): int
    {
        $pdo = Database::connection();

        $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM pots')->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO pots (name, icon, amount, note, linked_period_id, type, sort_order)
             VALUES (:name, :icon, :amount, :note, :linked_period_id, :type, :sort_order)'
        );
        $stmt->execute([
            'name' => $name,
            'icon' => $icon,
            'amount' => $linkedPeriodId ? null : $amount,
            'note' => $note,
            'linked_period_id' => $linkedPeriodId ?: null,
            'type' => self::normalizeType($type),
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $icon, ?float $amount, string $note, ?int $linkedPeriodId, string $type = 'leefpotje'): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pots SET name = :name, icon = :icon, amount = :amount, note = :note, linked_period_id = :linked_period_id, type = :type WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'icon' => $icon,
            'amount' => $linkedPeriodId ? null : $amount,
            'note' => $note,
            'linked_period_id' => $linkedPeriodId ?: null,
            'type' => self::normalizeType($type),
            'id' => $id,
        ]);
    }

    public static function normalizeType(string $type): string
    {
        return array_key_exists($type, self::TYPES) ? $type : 'leefpotje';
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM pots WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
