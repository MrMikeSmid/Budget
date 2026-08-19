<?php

namespace App\Support;

/**
 * Selecteert welke periodes bij een zelf gekozen "van"/"tot en met"-reeks
 * horen en berekent totaaloverzichten. Puur rekenwerk, geen
 * database-toegang.
 */
final class Statistics
{
    /**
     * Alle periodes van (en met) $fromId t/m (en met) $toId, chronologisch
     * — $periods moet al chronologisch gesorteerd zijn (zie
     * BudgetPeriod::allWithTotals(), op start_date ASC). De volgorde
     * waarin $fromId/$toId gekozen zijn maakt niet uit: de vroegste van de
     * twee geldt als start.
     *
     * @param array $periods output van BudgetPeriod::allWithTotals()
     */
    public static function between(array $periods, int $fromId, int $toId): array
    {
        $fromIndex = self::indexOf($periods, $fromId);
        $toIndex = self::indexOf($periods, $toId);

        if ($fromIndex === null || $toIndex === null) {
            return [];
        }

        if ($fromIndex > $toIndex) {
            [$fromIndex, $toIndex] = [$toIndex, $fromIndex];
        }

        return array_slice($periods, $fromIndex, $toIndex - $fromIndex + 1);
    }

    private static function indexOf(array $periods, int $id): ?int
    {
        foreach ($periods as $index => $p) {
            if ((int) $p['id'] === $id) {
                return $index;
            }
        }

        return null;
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
