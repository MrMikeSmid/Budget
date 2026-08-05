<?php

namespace App\Controllers;

use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Support\View;

/**
 * Verwerkt het wegklikken van een "meer betaald/ontvangen dan begroot"-
 * waarschuwing op het dashboard: markeert 'm als gezien (komt daarna nooit
 * meer terug) en stuurt door naar de bijbehorende kasstroommutatie, zodat de
 * knop meteen ook "ga naar de mutatie" doet.
 */
final class WarningController
{
    public static function dismiss(): void
    {
        $type = $_POST['type'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);

        if ($type === 'fixed_cost') {
            FixedCost::dismissWarning($id);
        } elseif ($type === 'income') {
            IncomeItem::dismissWarning($id);
        }

        if ($transactionId > 0) {
            header('Location: ' . View::url('kasstroom', ['period' => $periodId, 'edit' => $transactionId]));
        } else {
            header('Location: ' . View::url('dashboard', ['period' => $periodId]));
        }
        exit;
    }
}
