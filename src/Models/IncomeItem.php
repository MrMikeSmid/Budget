<?php

namespace App\Models;

use App\Support\Database;

final class IncomeItem extends LineItem
{
    protected static function table(): string
    {
        return 'income_items';
    }

    protected static function transactionLinkColumn(): string
    {
        return 'income_item_id';
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

    /**
     * Daadwerkelijk ontvangen: bedrag van regels met status "ontvangen". Werkelijk
     * bedrag telt als dat is ingevuld, anders valt dit terug op het begrote bedrag
     * (een regel op "ontvangen" zetten zonder apart het werkelijke bedrag in te
     * vullen is de normale route voor bijv. een vast salaris).
     */
    public static function receivedTotal(int $periodId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(COALESCE(actual, budgeted)), 0) FROM income_items
             WHERE period_id = :period_id AND status LIKE 'Ontvangen%'"
        );
        $stmt->execute(['period_id' => $periodId]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Inkomsten waarvan werkelijk hoger is dan begroot, en waarvan de
     * waarschuwing daarover nog niet bekeken is — voor het positieve
     * waarschuwingsvenster op het dashboard ("meer ontvangen dan begroot").
     */
    public static function overreceivedForPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ii.*,
                    (SELECT t.id FROM transactions t WHERE t.income_item_id = ii.id ORDER BY t.id DESC LIMIT 1) AS linked_transaction_id
             FROM income_items ii
             WHERE ii.period_id = :period_id
               AND ii.actual IS NOT NULL
               AND ii.actual > ii.budgeted
               AND ii.warning_dismissed_at IS NULL
             ORDER BY ii.description"
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetchAll();
    }
}
