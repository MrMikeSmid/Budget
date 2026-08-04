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
     * Inkomsten waarvan het werkelijke bedrag nog niet is ingevuld — het
     * begrote bedrag is dan nog een verwachting, geen ontvangen geld. Input
     * voor het betaaladvies op het dashboard: "wat kan ik betalen zodra dit
     * nog binnenkomt?".
     */
    public static function unreceivedForPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, description, budgeted FROM income_items
             WHERE period_id = :period_id AND actual IS NULL
             ORDER BY budgeted DESC"
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetchAll();
    }
}
