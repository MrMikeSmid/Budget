<?php

namespace App\Support;

/**
 * Selecteert welke periodes bij een gekozen tijdrange horen en berekent
 * totaaloverzichten. Puur rekenwerk, geen database-toegang.
 *
 * "Maand" toont één zelf gekozen periode; "kwartaal"/"jaar" tonen de
 * laatst aangemaakte 3 resp. 12 periodes — niet per se kalenderkwartalen/
 * -jaren, want een periode hoeft geen kalendermaand te zijn.
 */
final class Statistics
{
    public const RANGES = ['maand', 'kwartaal', 'jaar'];
    public const RANGE_PERIOD_COUNTS = ['kwartaal' => 3, 'jaar' => 12];

    /**
     * De laatste $count periodes op volgorde van aanmaken (hoogste id
     * eerst), teruggegeven in chronologische volgorde (oudste eerst) zodat
     * grafiek/tabel netjes van links naar rechts oplopen.
     *
     * @param array $periods output van BudgetPeriod::allWithTotals()
     */
    public static function lastCreated(array $periods, int $count): array
    {
        $sorted = $periods;
        usort($sorted, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);

        return array_reverse(array_slice($sorted, 0, $count));
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
