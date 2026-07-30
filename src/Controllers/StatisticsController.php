<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
use App\Models\Pot;
use App\Support\Statistics;
use App\Support\View;

final class StatisticsController
{
    public static function index(): void
    {
        $range = $_GET['range'] ?? 'maand';
        if (!in_array($range, Statistics::RANGES, true)) {
            $range = 'maand';
        }

        $periods = BudgetPeriod::allWithTotals();
        $buckets = Statistics::group($periods, $range);
        $totals = Statistics::grandTotals($periods);
        $pots = Pot::all();

        View::render('statistics/index', [
            'range' => $range,
            'buckets' => $buckets,
            'totals' => $totals,
            'pots' => $pots,
        ]);
    }
}
