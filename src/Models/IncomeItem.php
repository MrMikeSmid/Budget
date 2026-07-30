<?php

namespace App\Models;

use App\Support\Database;

final class IncomeItem extends LineItem
{
    protected static function table(): string
    {
        return 'income_items';
    }

    /**
     * Nog te ontvangen: begroot van regels waarvan de status niet als "ontvangen" telt.
     */
    public static function outstanding(int $periodId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(budgeted), 0) FROM income_items
             WHERE period_id = :period_id AND status NOT LIKE 'Ontvangen%'"
        );
        $stmt->execute(['period_id' => $periodId]);

        return (float) $stmt->fetchColumn();
    }
}
