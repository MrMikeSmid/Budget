<?php

namespace App\Models;

use App\Support\Database;

/**
 * Lening/schuld met een totaalbedrag. Het openstaande bedrag wordt niet
 * opgeslagen maar berekend uit loan_payments (zelfde patroon als potjes met
 * pot_transactions), zodat een betaling altijd terug te draaien is.
 */
final class Loan
{
    public static function all(): array
    {
        $rows = Database::connection()->query(
            'SELECT loans.*, c.name AS category_name FROM loans LEFT JOIN categories c ON c.id = loans.category_id ORDER BY loans.created_at DESC'
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['paid_amount'] = self::paidAmount((int) $row['id']);
            $row['remaining_amount'] = (float) $row['total_amount'] - $row['paid_amount'];
        }

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT loans.*, c.name AS category_name FROM loans LEFT JOIN categories c ON c.id = loans.category_id WHERE loans.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['paid_amount'] = self::paidAmount($id);
        $row['remaining_amount'] = (float) $row['total_amount'] - $row['paid_amount'];

        return $row;
    }

    public static function paidAmount(int $loanId): float
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(amount), 0) FROM loan_payments WHERE loan_id = :loan_id');
        $stmt->execute(['loan_id' => $loanId]);

        return (float) $stmt->fetchColumn();
    }

    public static function remainingAmount(int $loanId): float
    {
        $loan = self::find($loanId);

        return $loan ? (float) $loan['remaining_amount'] : 0.0;
    }

    /**
     * Leningtermijnen in deze periode waarvan wél iets betaald is, maar niet
     * het volledige afgesproken (begrote) termijnbedrag — bijv. de helft van
     * de maandelijkse aflossing. Alleen deze gevallen zijn interessant voor
     * een aandachtsvenster: nog niets betaald is de normale "openstaand"-
     * situatie, en volledig betaald behoeft geen aandacht.
     */
    public static function partialPaymentsForPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT fc.id AS fixed_cost_id, fc.description, fc.budgeted, fc.actual, l.id AS loan_id, l.name AS loan_name
             FROM fixed_costs fc
             JOIN loans l ON l.id = fc.loan_id
             WHERE fc.period_id = :period_id
               AND fc.actual IS NOT NULL
               AND fc.actual > 0
               AND fc.actual < fc.budgeted
             ORDER BY fc.description"
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetchAll();
    }

    public static function create(string $name, float $totalAmount, float $monthlyPayment, string $note, ?int $categoryId = null): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO loans (name, total_amount, monthly_payment, note, category_id) VALUES (:name, :total, :monthly, :note, :category_id)'
        );
        $stmt->execute([
            'name' => $name,
            'total' => $totalAmount,
            'monthly' => $monthlyPayment,
            'note' => $note,
            'category_id' => $categoryId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Werkt ook de categorie bij van elke vaste-lastenregel die al aan deze
     * lening gekoppeld is (huidige en eerdere periodes) — anders zou de
     * termijn op "Vaste lasten" de oude categorie blijven tonen.
     */
    public static function update(int $id, string $name, float $totalAmount, float $monthlyPayment, string $note, ?int $categoryId = null): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'UPDATE loans SET name = :name, total_amount = :total, monthly_payment = :monthly, note = :note, category_id = :category_id WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'total' => $totalAmount,
            'monthly' => $monthlyPayment,
            'note' => $note,
            'category_id' => $categoryId,
            'id' => $id,
        ]);

        $stmt = $pdo->prepare('UPDATE fixed_costs SET category_id = :category_id WHERE loan_id = :loan_id');
        $stmt->execute(['category_id' => $categoryId, 'loan_id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM loans WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Registreert een betaling op deze lening, gekoppeld aan een vaste-lastenregel.
     * Eén vaste-lastenregel kan nooit twee keer meetellen (zie recordPaymentForFixedCost).
     */
    public static function addPayment(int $loanId, ?int $fixedCostId, float $amount): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO loan_payments (loan_id, fixed_cost_id, amount) VALUES (:loan_id, :fixed_cost_id, :amount)'
        );
        $stmt->execute([
            'loan_id' => $loanId,
            'fixed_cost_id' => $fixedCostId,
            'amount' => $amount,
        ]);
    }

    public static function paymentForFixedCost(int $fixedCostId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM loan_payments WHERE fixed_cost_id = :fixed_cost_id LIMIT 1');
        $stmt->execute(['fixed_cost_id' => $fixedCostId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function removePaymentForFixedCost(int $fixedCostId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM loan_payments WHERE fixed_cost_id = :fixed_cost_id');
        $stmt->execute(['fixed_cost_id' => $fixedCostId]);
    }

    public static function updatePaymentAmountForFixedCost(int $fixedCostId, float $amount): void
    {
        $stmt = Database::connection()->prepare('UPDATE loan_payments SET amount = :amount WHERE fixed_cost_id = :fixed_cost_id');
        $stmt->execute(['amount' => $amount, 'fixed_cost_id' => $fixedCostId]);
    }
}
