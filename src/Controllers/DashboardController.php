<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\Pot;
use App\Support\View;

final class DashboardController
{
    public static function index(): void
    {
        $period = BudgetPeriod::resolveFromRequest();

        $balance = null;
        $leefpotjesTotal = 0.0;
        $spaarpotjesTotal = 0.0;
        $totalKapitaal = null;
        $paidActual = 0.0;
        $openBudgeted = 0.0;
        $totalPayments = 0.0;

        if ($period) {
            $balance = BudgetPeriod::endingBalance((int) $period['id']);

            $pots = Pot::allForPeriod((int) $period['id']);
            $leefpotjesTotal = array_sum(array_map(
                static fn ($p) => (float) $p['resolved_amount'],
                array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'leefpotje')
            ));
            $spaarpotjesTotal = array_sum(array_map(
                static fn ($p) => (float) $p['resolved_amount'],
                array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'spaarpotje')
            ));
            $totalKapitaal = $balance + $leefpotjesTotal + $spaarpotjesTotal;

            $paidActual = FixedCost::paidTotal((int) $period['id']);
            $openBudgeted = FixedCost::outstanding((int) $period['id']);
            $totalPayments = $paidActual + $openBudgeted;
        }

        View::render('dashboard/index', [
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'balance' => $balance,
            'leefpotjesTotal' => $leefpotjesTotal,
            'spaarpotjesTotal' => $spaarpotjesTotal,
            'totalKapitaal' => $totalKapitaal,
            'paidActual' => $paidActual,
            'openBudgeted' => $openBudgeted,
            'totalPayments' => $totalPayments,
        ]);
    }
}
