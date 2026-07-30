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
        $rows = Database::connection()->query('SELECT * FROM loans ORDER BY created_at DESC')->fetchAll();

        foreach ($rows as &$row) {
            $row['paid_amount'] = self::paidAmount((int) $row['id']);
            $row['remaining_amount'] = (float) $row['total_amount'] - $row['paid_amount'];
        }

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM loans WHERE id = :id');
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

    public static function create(string $name, float $totalAmount, float $monthlyPayment, string $note): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO loans (name, total_amount, monthly_payment, note) VALUES (:name, :total, :monthly, :note)'
        );
        $stmt->execute([
            'name' => $name,
            'total' => $totalAmount,
            'monthly' => $monthlyPayment,
            'note' => $note,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, float $totalAmount, float $monthlyPayment, string $note): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE loans SET name = :name, total_amount = :total, monthly_payment = :monthly, note = :note WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'total' => $totalAmount,
            'monthly' => $monthlyPayment,
            'note' => $note,
            'id' => $id,
        ]);
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
