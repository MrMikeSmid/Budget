<?php

namespace App\Support;

/**
 * Groepeert periodes (met hun totalen) tot maand/kwartaal/jaar-buckets
 * en berekent totaaloverzichten. Puur rekenwerk, geen database-toegang.
 */
final class Statistics
{
    public const RANGES = ['maand', 'kwartaal', 'jaar'];

    /**
     * @param array $periods output van BudgetPeriod::allWithTotals()
     */
    public static function group(array $periods, string $range): array
    {
        if ($range === 'maand') {
            return array_map(static function (array $p) {
                return [
                    'label' => $p['name'],
                    'income_budgeted' => (float) $p['income_budgeted'],
                    'income_actual' => (float) $p['income_actual'],
                    'fixed_budgeted' => (float) $p['fixed_budgeted'],
                    'fixed_actual' => (float) $p['fixed_actual'],
                    'ending_balance' => (float) $p['ending_balance'],
                    'period_count' => 1,
                ];
            }, $periods);
        }

        $buckets = [];

        foreach ($periods as $p) {
            $date = strtotime((string) $p['start_date']);
            $year = (int) date('Y', $date);

            if ($range === 'jaar') {
                $key = (string) $year;
                $label = (string) $year;
            } else {
                $quarter = (int) ceil((int) date('n', $date) / 3);
                $key = $year . '-Q' . $quarter;
                $label = 'Q' . $quarter . ' ' . $year;
            }

            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $label,
                    'income_budgeted' => 0.0,
                    'income_actual' => 0.0,
                    'fixed_budgeted' => 0.0,
                    'fixed_actual' => 0.0,
                    'ending_balance' => 0.0,
                    'period_count' => 0,
                ];
            }

            $buckets[$key]['income_budgeted'] += (float) $p['income_budgeted'];
            $buckets[$key]['income_actual'] += (float) $p['income_actual'];
            $buckets[$key]['fixed_budgeted'] += (float) $p['fixed_budgeted'];
            $buckets[$key]['fixed_actual'] += (float) $p['fixed_actual'];
            $buckets[$key]['ending_balance'] = (float) $p['ending_balance']; // laatste periode in de bucket
            $buckets[$key]['period_count']++;
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * @param array $periods output van BudgetPeriod::allWithTotals()
     */
    public static function grandTotals(array $periods): array
    {
        $totals = [
            'income_budgeted' => 0.0,
            'income_actual' => 0.0,
            'fixed_budgeted' => 0.0,
            'fixed_actual' => 0.0,
            'period_count' => count($periods),
        ];

        foreach ($periods as $p) {
            $totals['income_budgeted'] += (float) $p['income_budgeted'];
            $totals['income_actual'] += (float) $p['income_actual'];
            $totals['fixed_budgeted'] += (float) $p['fixed_budgeted'];
            $totals['fixed_actual'] += (float) $p['fixed_actual'];
        }

        $totals['net_actual'] = $totals['income_actual'] - $totals['fixed_actual'];
        $totals['net_budgeted'] = $totals['income_budgeted'] - $totals['fixed_budgeted'];

        return $totals;
    }
}
