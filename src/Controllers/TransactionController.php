<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Models\Pot;
use App\Models\PotTransaction;
use App\Models\Transaction;
use App\Support\View;

final class TransactionController
{
    public static function index(): void
    {
        $period = BudgetPeriod::resolveFromRequest();
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
        $editOverboekingId = isset($_GET['edit_overboeking']) ? (int) $_GET['edit_overboeking'] : null;

        $filters = [
            'type' => $_GET['type'] ?? 'alle',
            'pot_id' => $_GET['pot_id'] ?? '',
            'sort' => $_GET['sort'] ?? 'datum',
            'dir' => $_GET['dir'] ?? 'asc',
        ];

        View::render('kasstroom/index', [
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'transactions' => $period ? Transaction::forPeriodUnified((int) $period['id'], $filters) : [],
            'filters' => $filters,
            'expectedBalance' => $period ? BudgetPeriod::endingBalance((int) $period['id']) : null,
            'pots' => $period ? Pot::allForPeriod((int) $period['id']) : Pot::all(),
            'fixedCosts' => $period ? FixedCost::forPeriod((int) $period['id']) : [],
            'incomeItems' => $period ? IncomeItem::forPeriod((int) $period['id']) : [],
            'editing' => $editId ? Transaction::find($editId) : null,
            'editingOverboeking' => $editOverboekingId ? PotTransaction::find($editOverboekingId) : null,
            'openForm' => $editId !== null || $editOverboekingId !== null || !empty($_GET['open']),
            'activeTab' => ($_GET['tab'] ?? '') === 'overboeken' || $editOverboekingId !== null ? 'overboeken' : 'uitgave',
        ]);
    }

    /**
     * De kasstroom-mutatie is uitsluitend voor uitgaven: het bedrag wordt
     * altijd als negatief opgeslagen, ongeacht hoe het ingevuld is. Geld
     * terugboeken of tussen potjes verplaatsen gaat via "Overboeken", en
     * echt nieuw geld voeg je toe bij Inkomen.
     *
     * "Bron" kan i.p.v. een potje ook een vaste last of inkomst zijn: dat
     * koppelt de mutatie eraan (fixed_cost_id/income_item_id) en werkt
     * meteen begroot/status/terugkerend van die regel bij — het
     * bewerkformulier van de last/inkomst zelf is dan niet meer nodig,
     * dit formulier vervangt het voor gekoppelde regels.
     */
    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $date = $_POST['txn_date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $amount = -abs((float) str_replace(',', '.', $_POST['amount'] ?? '0'));
        $settled = !empty($_POST['is_settled']);

        [$potId, $fixedCostId, $incomeItemId] = self::parseSource((string) ($_POST['source'] ?? ''));

        if ($description === '' || $date === '' || $periodId === 0) {
            View::flash('Vul een datum en omschrijving in.', 'error');
            header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
            exit;
        }

        $oldTxn = $id > 0 ? Transaction::find($id) : null;

        $sourceLabel = self::sourceLabel($potId, $fixedCostId, $incomeItemId);
        if ($id > 0) {
            Transaction::update($id, $date, $description, $amount, $settled, $potId, $fixedCostId, $incomeItemId);
            Activity::log('kasstroom', 'Uitgave bijgewerkt: ' . $description . $sourceLabel, $amount);
            View::flash('Transactie opgeslagen.');
        } else {
            $id = Transaction::create($periodId, $date, $description, $amount, $settled, $potId, $fixedCostId, $incomeItemId);
            Activity::log('kasstroom', 'Uitgave toegevoegd: ' . $description . $sourceLabel, $amount);
            View::flash('Transactie toegevoegd.');
        }

        if ($fixedCostId) {
            self::updateLinkedLineItem(FixedCost::class, $fixedCostId, $description);
        }
        if ($incomeItemId) {
            self::updateLinkedLineItem(IncomeItem::class, $incomeItemId, $description);
        }

        // De vorige koppeling (indien gewijzigd of losgekoppeld) moet ook
        // opnieuw zijn "werkelijk"-bedrag krijgen, anders blijft dat de
        // net verplaatste mutatie nog meetellen.
        if ($oldTxn) {
            if (!empty($oldTxn['fixed_cost_id']) && (int) $oldTxn['fixed_cost_id'] !== $fixedCostId) {
                FixedCost::syncActualFromTransactions((int) $oldTxn['fixed_cost_id']);
            }
            if (!empty($oldTxn['income_item_id']) && (int) $oldTxn['income_item_id'] !== $incomeItemId) {
                IncomeItem::syncActualFromTransactions((int) $oldTxn['income_item_id']);
            }
        }

        header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);

        $txn = Transaction::find($id);
        Transaction::delete($id);
        if ($txn) {
            $pot = $txn['pot_id'] ? Pot::find((int) $txn['pot_id']) : null;
            $potSuffix = $pot ? " (potje: {$pot['name']})" : '';
            Activity::log('kasstroom', 'Mutatie verwijderd: ' . $txn['description'] . $potSuffix, (float) $txn['amount']);

            if (!empty($txn['fixed_cost_id'])) {
                FixedCost::syncActualFromTransactions((int) $txn['fixed_cost_id']);
            }
            if (!empty($txn['income_item_id'])) {
                IncomeItem::syncActualFromTransactions((int) $txn['income_item_id']);
            }
        }
        View::flash('Transactie verwijderd.');

        header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        exit;
    }

    /**
     * "Bron" is één select met potjes/lasten/inkomsten door elkaar,
     * onderscheiden via een prefix op de option-waarde (bijv. "pot:3").
     *
     * @return array{0: ?int, 1: ?int, 2: ?int} [potId, fixedCostId, incomeItemId]
     */
    private static function parseSource(string $source): array
    {
        if (str_starts_with($source, 'pot:')) {
            return [(int) substr($source, 4) ?: null, null, null];
        }
        if (str_starts_with($source, 'fixed_cost:')) {
            return [null, (int) substr($source, 11) ?: null, null];
        }
        if (str_starts_with($source, 'income:')) {
            return [null, null, (int) substr($source, 7) ?: null];
        }

        return [null, null, null];
    }

    private static function sourceLabel(?int $potId, ?int $fixedCostId, ?int $incomeItemId): string
    {
        if ($potId) {
            $pot = Pot::find($potId);

            return $pot ? " (potje: {$pot['name']})" : '';
        }
        if ($fixedCostId) {
            return ' (gekoppeld aan vaste last)';
        }
        if ($incomeItemId) {
            return ' (gekoppeld aan inkomst)';
        }

        return '';
    }

    /**
     * @param class-string<FixedCost>|class-string<IncomeItem> $model
     */
    private static function updateLinkedLineItem(string $model, int $id, string $description): void
    {
        $budgeted = (float) str_replace(',', '.', $_POST['budgeted'] ?? '0');
        $status = trim($_POST['status'] ?? '');
        $isRecurring = !empty($_POST['is_recurring']);
        $recurrenceInterval = $model::normalizeInterval((string) ($_POST['recurrence_interval'] ?? 'maandelijks'));
        $recurrenceMode = $model::normalizeMode((string) ($_POST['recurrence_mode'] ?? 'periode'));
        $recurrenceDate = trim($_POST['recurrence_date'] ?? '') ?: null;

        $model::updateFull($id, $description, $budgeted, null, $status, $isRecurring, $recurrenceInterval, $recurrenceMode, $recurrenceDate);
        $model::syncActualFromTransactions($id);
    }
}
