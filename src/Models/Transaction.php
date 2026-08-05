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
     * "verwachte saldo kasstroom" als BudgetPeriod::endingBalance(). Een
     * mutatie die aan een potje gekoppeld is, verandert het lopende saldo
     * niet — dat geld komt/gaat immers al bij het potje zelf vandaan.
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
            if (empty($row['pot_id'])) {
                $running += (float) $row['amount'];
            }
            $row['balance'] = $running;
        }

        return $rows;
    }

    private const SORT_COLUMNS = ['datum' => 'txn_date', 'bedrag' => 'amount', 'omschrijving' => 'description'];

    /**
     * Kasstroommutaties én overboekingen (stortingen/opnames/overboekingen
     * op potjes die het losse saldo raken — potje-naar-potje overboekingen
     * niet, die zie je alleen in de betrokken potjes zelf) samengevoegd tot
     * één lijst voor de kasstroompagina, met lopend saldo.
     *
     * Het lopend saldo wordt altijd chronologisch opgebouwd (net als
     * BudgetPeriod::endingBalance()), ook als de rijen daarna anders
     * gesorteerd of gefilterd worden voor de weergave — zo blijft "saldo
     * na deze mutatie" per rij kloppen, ook al staat de rij niet meer op
     * chronologische volgorde.
     *
     * @param array{type?: string, pot_id?: int|string|null, sort?: string, dir?: string} $filters
     */
    public static function forPeriodUnified(int $periodId, array $filters = []): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id, 'kasstroom' AS source, t.txn_date, t.description, t.amount, t.is_settled, t.pot_id,
                    t.fixed_cost_id, t.income_item_id, p.name AS pot_name, p.icon AS pot_icon,
                    c.id AS category_id, c.name AS category_name
             FROM transactions t
             LEFT JOIN pots p ON p.id = t.pot_id
             LEFT JOIN fixed_costs fc ON fc.id = t.fixed_cost_id
             LEFT JOIN income_items ii ON ii.id = t.income_item_id
             LEFT JOIN categories c ON c.id = COALESCE(fc.category_id, ii.category_id)
             WHERE t.period_id = :period_id"
        );
        $stmt->execute(['period_id' => $periodId]);
        $rows = $stmt->fetchAll();

        $stmt = Database::connection()->prepare(
            "SELECT pt.id, 'overboeking' AS source, pt.txn_date, pt.description, -pt.amount AS amount, 0 AS is_settled, pt.pot_id,
                    NULL AS fixed_cost_id, NULL AS income_item_id, p.name AS pot_name, p.icon AS pot_icon,
                    NULL AS category_id, NULL AS category_name
             FROM pot_transactions pt
             JOIN pots p ON p.id = pt.pot_id
             WHERE pt.period_id = :period_id AND pt.transfer_pot_id IS NULL"
        );
        $stmt->execute(['period_id' => $periodId]);
        $rows = array_merge($rows, $stmt->fetchAll());

        usort($rows, static function (array $a, array $b): int {
            return [$a['txn_date'], $a['id']] <=> [$b['txn_date'], $b['id']];
        });

        $running = (float) IncomeItem::totals($periodId)['actual'] - (float) FixedCost::totals($periodId)['actual'];
        foreach ($rows as &$row) {
            $affectsLooseBalance = $row['source'] === 'overboeking'
                || (empty($row['pot_id']) && empty($row['fixed_cost_id']) && empty($row['income_item_id']));
            if ($affectsLooseBalance) {
                $running += (float) $row['amount'];
            }
            $row['balance'] = $running;
        }
        unset($row);

        $type = $filters['type'] ?? 'alle';
        $potId = isset($filters['pot_id']) && $filters['pot_id'] !== '' ? (int) $filters['pot_id'] : null;
        $rows = array_values(array_filter($rows, static function (array $row) use ($type, $potId): bool {
            if ($potId !== null && (int) $row['pot_id'] !== $potId) {
                return false;
            }
            if ($type === 'uitgaven' && $row['source'] !== 'kasstroom') {
                return false;
            }
            if ($type === 'overboekingen' && $row['source'] !== 'overboeking') {
                return false;
            }

            return true;
        }));

        $sortColumn = self::SORT_COLUMNS[$filters['sort'] ?? 'datum'] ?? 'txn_date';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? -1 : 1;
        usort($rows, static function (array $a, array $b) use ($sortColumn, $dir): int {
            $cmp = $a[$sortColumn] <=> $b[$sortColumn];
            if ($cmp === 0) {
                $cmp = $a['id'] <=> $b['id'];
            }

            return $cmp * $dir;
        });

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $periodId, string $date, string $description, float $amount, bool $isSettled, ?int $potId = null, ?int $fixedCostId = null, ?int $incomeItemId = null): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM transactions WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO transactions (period_id, txn_date, description, amount, is_settled, sort_order, pot_id, fixed_cost_id, income_item_id)
             VALUES (:period_id, :date, :description, :amount, :settled, :sort_order, :pot_id, :fixed_cost_id, :income_item_id)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'settled' => $isSettled ? 1 : 0,
            'sort_order' => $sortOrder,
            'pot_id' => $potId,
            'fixed_cost_id' => $fixedCostId,
            'income_item_id' => $incomeItemId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $date, string $description, float $amount, bool $isSettled, ?int $potId = null, ?int $fixedCostId = null, ?int $incomeItemId = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE transactions SET txn_date = :date, description = :description, amount = :amount, is_settled = :settled, pot_id = :pot_id, fixed_cost_id = :fixed_cost_id, income_item_id = :income_item_id WHERE id = :id'
        );
        $stmt->execute([
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'settled' => $isSettled ? 1 : 0,
            'pot_id' => $potId,
            'fixed_cost_id' => $fixedCostId,
            'income_item_id' => $incomeItemId,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
