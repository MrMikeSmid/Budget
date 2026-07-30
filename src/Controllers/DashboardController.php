<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Models\Pot;
use App\Models\Transaction;
use App\Support\View;

final class DashboardController
{
    public static function index(): void
    {
        $period = BudgetPeriod::resolveFromRequest();

        $incomeTotals = ['budgeted' => 0, 'actual' => 0];
        $fixedTotals = ['budgeted' => 0, 'actual' => 0];
        $fixedOutstanding = 0.0;
        $transactions = [];
        $balance = null;

        if ($period) {
            $incomeTotals = IncomeItem::totals((int) $period['id']);
            $fixedTotals = FixedCost::totals((int) $period['id']);
            $fixedOutstanding = FixedCost::outstanding((int) $period['id']);
            $transactions = Transaction::forPeriod((int) $period['id']);
            $balance = BudgetPeriod::endingBalance((int) $period['id']);
        }

        View::render('dashboard/index', [
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'incomeTotals' => $incomeTotals,
            'fixedTotals' => $fixedTotals,
            'fixedOutstanding' => $fixedOutstanding,
            'balance' => $balance,
            'recentTransactions' => array_slice(array_reverse($transactions), 0, 5),
            'pots' => Pot::all(),
        ]);
    }
}
