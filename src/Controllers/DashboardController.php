<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Models\Loan;
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
        $incomeBudgeted = 0.0;
        $incomeActual = 0.0;
        $incomeOutstanding = 0.0;
        $incomeTotal = 0.0;
        $partialLoanPayments = [];

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

            $incomeBudgeted = (float) IncomeItem::totals((int) $period['id'])['budgeted'];
            $incomeActual = IncomeItem::receivedTotal((int) $period['id']);
            $incomeOutstanding = IncomeItem::outstanding((int) $period['id']);
            $incomeTotal = $incomeActual + $incomeOutstanding;

            $partialLoanPayments = Loan::partialPaymentsForPeriod((int) $period['id']);
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
            'incomeBudgeted' => $incomeBudgeted,
            'incomeActual' => $incomeActual,
            'incomeOutstanding' => $incomeOutstanding,
            'incomeTotal' => $incomeTotal,
            'partialLoanPayments' => $partialLoanPayments,
        ]);
    }
}
