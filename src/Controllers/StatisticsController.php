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

        $allPeriods = BudgetPeriod::allWithTotals();

        $selectedPeriod = null;
        $requiredCount = null;

        if ($range === 'maand') {
            $chosen = BudgetPeriod::resolveFromRequest();
            $selectedPeriod = $chosen ? self::findById($allPeriods, (int) $chosen['id']) : null;
            $buckets = $selectedPeriod ? [$selectedPeriod] : [];
        } else {
            $requiredCount = Statistics::RANGE_PERIOD_COUNTS[$range];
            $buckets = Statistics::lastCreated($allPeriods, $requiredCount);
        }

        View::render('statistics/index', [
            'range' => $range,
            'periods' => $allPeriods,
            'selectedPeriod' => $selectedPeriod,
            'buckets' => $buckets,
            'totals' => Statistics::grandTotals($buckets),
            'requiredCount' => $requiredCount,
            'availableCount' => count($allPeriods),
            'pots' => Pot::all(),
        ]);
    }

    private static function findById(array $periods, int $id): ?array
    {
        foreach ($periods as $p) {
            if ((int) $p['id'] === $id) {
                return $p;
            }
        }

        return null;
    }
}
