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
             WHERE p.deleted_at IS NULL
             ORDER BY p.sort_order, p.id'
        );
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $base = $row['linked_period_id']
                ? BudgetPeriod::endingBalance((int) $row['linked_period_id'])
                : (float) $row['amount'];
            $row['base_amount'] = $base;
            $row['resolved_amount'] = $base + self::mutationsSum((int) $row['id']);
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
        $row['resolved_amount'] = $base + self::mutationsSum((int) $row['id']);

        return $row;
    }

    /**
     * Som van alle mutaties op dit potje: handmatige pot-transacties plus
     * kasstroommutaties die aan dit potje gekoppeld zijn.
     */
    public static function mutationsSum(int $potId): float
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM pot_transactions WHERE pot_id = :pot_id');
        $stmt->execute(['pot_id' => $potId]);
        $fromPot = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE pot_id = :pot_id');
        $stmt->execute(['pot_id' => $potId]);
        $fromKasstroom = (float) $stmt->fetchColumn();

        return $fromPot + $fromKasstroom;
    }

    /**
     * Volledige mutatiegeschiedenis van een potje: handmatige pot-transacties
     * samengevoegd met gekoppelde kasstroommutaties, chronologisch met
     * lopend saldo. Basis voor de detailpagina van een potje.
     */
    public static function ledger(int $potId, float $openingBalance): array
    {
        // ORDER BY op kolompositie (3=txn_date, 1=id) i.p.v. naam: SQLite kan de
        // naam niet altijd matchen zodra dezelfde kolomnaam ('id') via een JOIN
        // dubbel voorkomt binnen een UNION.
        $stmt = Database::connection()->prepare(
            "SELECT pt.id, 'potje' AS source, pt.txn_date, pt.description, pt.amount,
                    u.name AS user_name, NULL AS period_id, NULL AS period_name
             FROM pot_transactions pt
             LEFT JOIN users u ON u.id = pt.user_id
             WHERE pt.pot_id = :pot_id1

             UNION ALL

             SELECT t.id, 'kasstroom' AS source, t.txn_date, t.description, t.amount,
                    NULL AS user_name, t.period_id, bp.name AS period_name
             FROM transactions t
             LEFT JOIN budget_periods bp ON bp.id = t.period_id
             WHERE t.pot_id = :pot_id2

             ORDER BY 3, 1"
        );
        $stmt->execute(['pot_id1' => $potId, 'pot_id2' => $potId]);
        $rows = $stmt->fetchAll();

        $running = $openingBalance;
        foreach ($rows as &$row) {
            $running += (float) $row['amount'];
            $row['balance'] = $running;
        }

        return $rows;
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

    /**
     * Zacht verwijderen: het potje verdwijnt uit actieve lijsten en
     * keuzemenu's (zie all()), maar de rij — en daarmee de geschiedenis
     * van al zijn mutaties en hun effect op het saldo van (afgesloten)
     * periodes — blijft intact.
     */
    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE pots SET deleted_at = datetime('now') WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
