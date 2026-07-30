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
     * Periode uit de request (?period=ID) of anders de actieve periode.
     */
    public static function resolveFromRequest(): ?array
    {
        $id = $_GET['period'] ?? $_POST['period_id'] ?? null;

        if ($id) {
            $period = self::find((int) $id);
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

    public static function create(string $name, string $startDate, string $endDate, float $openingBalance): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO budget_periods (name, start_date, end_date, opening_balance) VALUES (:name, :start, :end, :opening)'
        );
        $stmt->execute([
            'name' => $name,
            'start' => $startDate,
            'end' => $endDate,
            'opening' => $openingBalance,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $startDate, string $endDate, float $openingBalance): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE budget_periods SET name = :name, start_date = :start, end_date = :end, opening_balance = :opening WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'start' => $startDate,
            'end' => $endDate,
            'opening' => $openingBalance,
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
     * Eindsaldo van de kasstroom voor deze periode: openingsbalans + som van alle mutaties.
     */
    public static function endingBalance(int $id): float
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT opening_balance FROM budget_periods WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $opening = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE period_id = :id');
        $stmt->execute(['id' => $id]);
        $sum = (float) $stmt->fetchColumn();

        return $opening + $sum;
    }
}
