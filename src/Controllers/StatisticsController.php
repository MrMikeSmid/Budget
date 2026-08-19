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
        $allPeriods = BudgetPeriod::allWithTotals();

        $defaultId = self::defaultPeriodId($allPeriods);
        $fromId = isset($_GET['from']) ? (int) $_GET['from'] : $defaultId;
        $toId = isset($_GET['to']) ? (int) $_GET['to'] : $defaultId;

        $buckets = ($fromId && $toId) ? Statistics::between($allPeriods, $fromId, $toId) : [];

        View::render('statistics/index', [
            'periods' => $allPeriods,
            'fromId' => $fromId,
            'toId' => $toId,
            'buckets' => $buckets,
            'totals' => Statistics::grandTotals($buckets),
            'pots' => Pot::all(),
        ]);
    }

    /**
     * De actieve periode als standaardkeuze, anders de laatste (meest
     * recente) periode — of 0 als er nog helemaal geen periode bestaat.
     */
    private static function defaultPeriodId(array $periods): int
    {
        foreach ($periods as $p) {
            if (!empty($p['is_active'])) {
                return (int) $p['id'];
            }
        }

        return $periods ? (int) $periods[array_key_last($periods)]['id'] : 0;
    }
}
