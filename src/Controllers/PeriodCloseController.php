<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Models\Pot;
use App\Support\Database;
use App\Support\View;

/**
 * "Periode afsluiten": openstaande/niet-volledig-betaalde vaste lasten
 * (optioneel) en het overgebleven saldo (optioneel) meenemen naar een
 * andere, al bestaande periode, en die periode daarna actief zetten.
 * Potjes hoeven nooit expliciet meegenomen te worden — die bestaan al
 * periode-overstijgend (zie Pot::allForPeriod()) en worden hier alleen ter
 * informatie getoond.
 */
final class PeriodCloseController
{
    public static function confirm(): void
    {
        $period = BudgetPeriod::resolveFromRequest();
        if (!$period) {
            header('Location: ' . View::url('vaste-lasten'));
            exit;
        }

        if (!empty($period['closed_at'])) {
            View::flash('Deze periode is al afgesloten.', 'error');
            header('Location: ' . View::url('vaste-lasten', ['period' => $period['id']]));
            exit;
        }

        $periodId = (int) $period['id'];
        $otherPeriods = array_values(array_filter(
            BudgetPeriod::all(),
            static fn (array $p): bool => (int) $p['id'] !== $periodId
        ));

        // Standaard de eerstvolgende periode na deze (op startdatum), als die
        // bestaat — anders laat de gebruiker gewoon zelf kiezen.
        $future = array_values(array_filter(
            $otherPeriods,
            static fn (array $p): bool => $p['start_date'] > $period['start_date']
        ));
        usort($future, static fn (array $a, array $b) => $a['start_date'] <=> $b['start_date']);
        $defaultTargetId = $future[0]['id'] ?? null;

        $endingBalance = BudgetPeriod::endingBalance($periodId);
        $pots = Pot::allForPeriod($periodId);
        $hasLinkedPot = !empty(array_filter(
            $pots,
            static fn (array $p): bool => (int) ($p['linked_period_id'] ?? 0) === $periodId
        ));

        View::render('period-close/confirm', [
            'period' => $period,
            'otherPeriods' => $otherPeriods,
            'defaultTargetId' => $defaultTargetId,
            'outstandingItems' => FixedCost::outstandingItems($periodId),
            'pots' => $pots,
            'endingBalance' => $endingBalance,
            'hasLinkedPot' => $hasLinkedPot,
            'showBalanceQuestion' => $endingBalance > 0.01 && !$hasLinkedPot,
        ]);
    }

    public static function execute(): void
    {
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $targetId = (int) ($_POST['target_period_id'] ?? 0);

        $period = BudgetPeriod::find($periodId);
        $target = BudgetPeriod::find($targetId);

        if (!$period || !$target || $periodId === $targetId) {
            View::flash('Kies een andere, bestaande periode om naar over te zetten.', 'error');
            header('Location: ' . View::url('periode-afsluiten', ['period' => $periodId]));
            exit;
        }

        if (!empty($period['closed_at'])) {
            View::flash('Deze periode is al afgesloten.', 'error');
            header('Location: ' . View::url('vaste-lasten', ['period' => $periodId]));
            exit;
        }

        $carryIds = array_map('intval', (array) ($_POST['carry_fixed_costs'] ?? []));
        $carriedCount = 0;
        foreach (FixedCost::outstandingItems($periodId) as $item) {
            if (!in_array((int) $item['id'], $carryIds, true)) {
                continue;
            }

            self::carryFixedCost($item, $targetId);
            $carriedCount++;
        }

        $carriedBalance = false;
        if (!empty($_POST['carry_balance'])) {
            $endingBalance = BudgetPeriod::endingBalance($periodId);
            $hasLinkedPot = !empty(array_filter(
                Pot::allForPeriod($periodId),
                static fn (array $p): bool => (int) ($p['linked_period_id'] ?? 0) === $periodId
            ));

            if ($endingBalance > 0.01 && !$hasLinkedPot) {
                IncomeItem::create($targetId, 'Meegenomen saldo ' . $period['name'], $endingBalance, null, '', false);
                Activity::log('inkomsten', 'Meegenomen saldo van ' . $period['name'] . ' naar ' . $target['name'], $endingBalance);
                $carriedBalance = true;
            }
        }

        BudgetPeriod::setActive($targetId);
        BudgetPeriod::markClosed($periodId);
        $_SESSION['selected_period_id'] = $targetId;
        Activity::log('periods', 'Periode afgesloten: ' . $period['name'] . ' → ' . $target['name']);

        $message = 'Periode afgesloten.';
        if ($carriedCount > 0 || $carriedBalance) {
            $parts = [];
            if ($carriedCount > 0) {
                $parts[] = $carriedCount . ' last' . ($carriedCount === 1 ? '' : 'en') . ' meegenomen';
            }
            if ($carriedBalance) {
                $parts[] = 'saldo meegenomen';
            }
            $message = 'Periode afgesloten — ' . implode(' en ', $parts) . ' naar ' . $target['name'] . '.';
        }
        View::flash($message);

        header('Location: ' . View::url('vaste-lasten', ['period' => $targetId]));
        exit;
    }

    /**
     * Neemt het openstaande bedrag van één last mee naar de doelperiode.
     * Niet-terugkerend: een nieuwe, losse regel met dat bedrag. Terugkerend:
     * bovenop het bedrag van het al bestaande (of nieuw aan te maken)
     * voorkomen van diezelfde terugkerende reeks in de doelperiode — zo
     * telt het openstaande bedrag níet los van de gewone maandelijkse
     * termijn, maar erbovenop.
     */
    private static function carryFixedCost(array $item, int $targetId): void
    {
        $outstanding = (float) $item['outstanding_amount'];
        if ($outstanding <= 0) {
            return;
        }

        $categoryId = $item['category_id'] ? (int) $item['category_id'] : null;

        if (empty($item['is_recurring'])) {
            FixedCost::createFull(
                $targetId,
                (string) $item['description'],
                $outstanding,
                null,
                'Open',
                false,
                'maandelijks',
                'periode',
                null,
                null,
                $item['loan_id'] ? (int) $item['loan_id'] : null,
                $categoryId
            );
        } else {
            $groupKey = $item['recurrence_group_id'] ? (int) $item['recurrence_group_id'] : (int) $item['id'];

            $stmt = Database::connection()->prepare(
                'SELECT id, budgeted FROM fixed_costs WHERE period_id = :period_id AND COALESCE(recurrence_group_id, id) = :group_key LIMIT 1'
            );
            $stmt->bindValue('period_id', $targetId, \PDO::PARAM_INT);
            $stmt->bindValue('group_key', $groupKey, \PDO::PARAM_INT);
            $stmt->execute();
            $existing = $stmt->fetch();

            if ($existing) {
                $update = Database::connection()->prepare('UPDATE fixed_costs SET budgeted = :budgeted WHERE id = :id');
                $update->execute([
                    'budgeted' => (float) $existing['budgeted'] + $outstanding,
                    'id' => (int) $existing['id'],
                ]);
            } else {
                FixedCost::createFull(
                    $targetId,
                    (string) $item['description'],
                    (float) $item['budgeted'] + $outstanding,
                    null,
                    'Open',
                    true,
                    (string) $item['recurrence_interval'],
                    (string) $item['recurrence_mode'],
                    $item['recurrence_date'] ?: null,
                    $groupKey,
                    $item['loan_id'] ? (int) $item['loan_id'] : null,
                    $categoryId
                );
            }
        }

        Activity::log('vaste-lasten', 'Openstaand bedrag meegenomen: ' . $item['description'], -$outstanding);
    }
}
