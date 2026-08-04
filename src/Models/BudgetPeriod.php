<?php

namespace App\Models;

use App\Support\Database;

final class BudgetPeriod
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM budget_periods ORDER BY start_date DESC')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM budget_periods WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Periode uit de request (?period=ID), anders de laatst gekozen periode
     * uit de sessie, anders de actieve periode. Zo blijft een handmatig
     * gekozen periode staan zolang je door de app navigeert, i.p.v. steeds
     * terug te vallen op de actieve periode zodra je een andere pagina opent.
     */
    public static function resolveFromRequest(): ?array
    {
        $id = $_GET['period'] ?? $_POST['period_id'] ?? null;

        if ($id) {
            $period = self::find((int) $id);
            if ($period) {
                $_SESSION['selected_period_id'] = (int) $period['id'];

                return $period;
            }
        }

        if (!empty($_SESSION['selected_period_id'])) {
            $period = self::find((int) $_SESSION['selected_period_id']);
            if ($period) {
                return $period;
            }
        }

        return self::active();
    }

    public static function active(): ?array
    {
        $row = Database::connection()
            ->query('SELECT * FROM budget_periods WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1')
            ->fetch();

        if ($row) {
            return $row;
        }

        // Geen actieve periode gezet: pak de meest recente als fallback.
        $row = Database::connection()
            ->query('SELECT * FROM budget_periods ORDER BY start_date DESC LIMIT 1')
            ->fetch();

        return $row ?: null;
    }

    /**
     * Vindt een bestaande periode met exact dezelfde start- en einddatum.
     * Gebruikt om te voorkomen dat een dubbele formulierverzending (bijv.
     * dubbeltikken op "Toevoegen" op een trage verbinding) dezelfde periode
     * twee keer aanmaakt, elk met hun eigen kopie van terugkerende regels.
     */
    public static function findByDates(string $startDate, string $endDate): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM budget_periods WHERE start_date = :start AND end_date = :end LIMIT 1'
        );
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(string $name, string $startDate, string $endDate): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO budget_periods (name, start_date, end_date) VALUES (:name, :start, :end)'
        );
        $stmt->execute([
            'name' => $name,
            'start' => $startDate,
            'end' => $endDate,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $startDate, string $endDate): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE budget_periods SET name = :name, start_date = :start, end_date = :end WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'start' => $startDate,
            'end' => $endDate,
            'id' => $id,
        ]);
    }

    public static function setActive(int $id): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE budget_periods SET is_active = 0');
        $stmt = $pdo->prepare('UPDATE budget_periods SET is_active = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM budget_periods WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Alle periodes, chronologisch, elk met totalen inkomsten/vaste lasten en eindsaldo.
     * Basis voor de statistiekenpagina.
     */
    public static function allWithTotals(): array
    {
        $rows = Database::connection()->query(
            "SELECT p.*,
                COALESCE((SELECT SUM(budgeted) FROM income_items WHERE period_id = p.id), 0) AS income_budgeted,
                COALESCE((SELECT SUM(actual) FROM income_items WHERE period_id = p.id), 0) AS income_actual,
                COALESCE((SELECT SUM(budgeted) FROM fixed_costs WHERE period_id = p.id), 0) AS fixed_budgeted,
                COALESCE((SELECT SUM(actual) FROM fixed_costs WHERE period_id = p.id), 0) AS fixed_actual,
                COALESCE((SELECT SUM(amount) FROM transactions WHERE period_id = p.id AND pot_id IS NULL AND fixed_cost_id IS NULL AND income_item_id IS NULL), 0) AS transactions_sum,
                COALESCE((SELECT SUM(amount) FROM pot_transactions WHERE period_id = p.id), 0) AS pot_transactions_sum
             FROM budget_periods p
             ORDER BY p.start_date ASC"
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['ending_balance'] = (float) $row['income_actual'] - (float) $row['fixed_actual'] + (float) $row['transactions_sum'] - (float) $row['pot_transactions_sum'];
        }

        return $rows;
    }

    /**
     * Verwacht saldo kasstroom van deze periode: ontvangen inkomsten min
     * betaalde vaste lasten, plus de som van de "losse" kasstroommutaties
     * (dus niet de mutaties die aan een potje gekoppeld zijn — dat geld is
     * al bij het potje af/bijgeboekt en raakt het losse saldo niet meer,
     * en ook niet de mutaties die aan een last/inkomst gekoppeld zijn —
     * dat geld is al verwerkt via het "werkelijk"-bedrag van die last/
     * inkomst, anders telt dezelfde betaling dubbel), min wat er in deze
     * periode in potjes is gestort (en plus wat eruit is opgenomen) — dat
     * geld staat immers niet meer los op het saldo.
     */
    public static function endingBalance(int $id): float
    {
        $incomeActual = (float) IncomeItem::totals($id)['actual'];
        $fixedActual = (float) FixedCost::totals($id)['actual'];

        $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE period_id = :id AND pot_id IS NULL AND fixed_cost_id IS NULL AND income_item_id IS NULL');
        $stmt->execute(['id' => $id]);
        $sum = (float) $stmt->fetchColumn();

        $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(amount), 0) FROM pot_transactions WHERE period_id = :id');
        $stmt->execute(['id' => $id]);
        $potSum = (float) $stmt->fetchColumn();

        return $incomeActual - $fixedActual + $sum - $potSum;
    }
}
