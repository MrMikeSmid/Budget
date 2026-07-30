<?php

namespace App\Models;

use App\Support\Database;

final class FixedCost extends LineItem
{
    protected static function table(): string
    {
        return 'fixed_costs';
    }

    /**
     * Nog openstaand: begroot van regels met status die begint met "Open".
     */
    public static function outstanding(int $periodId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(budgeted), 0) FROM fixed_costs
             WHERE period_id = :period_id AND status LIKE 'Open%'"
        );
        $stmt->execute(['period_id' => $periodId]);

        return (float) $stmt->fetchColumn();
    }
}
