<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\Pot;
use App\Models\Transaction;
use App\Support\View;

final class TransactionController
{
    public static function index(): void
    {
        $period = BudgetPeriod::resolveFromRequest();
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        View::render('kasstroom/index', [
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'transactions' => $period ? Transaction::forPeriod((int) $period['id']) : [],
            'pots' => Pot::all(),
            'editing' => $editId ? Transaction::find($editId) : null,
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $date = $_POST['txn_date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $amount = (float) str_replace(',', '.', $_POST['amount'] ?? '0');
        $settled = !empty($_POST['is_settled']);
        $potId = (int) ($_POST['pot_id'] ?? 0) ?: null;
        $pot = $potId ? Pot::find($potId) : null;
        $potSuffix = $pot ? " (potje: {$pot['name']})" : '';

        if ($description === '' || $date === '' || $periodId === 0) {
            View::flash('Vul een datum en omschrijving in.', 'error');
        } elseif ($id > 0) {
            Transaction::update($id, $date, $description, $amount, $settled, $potId);
            Activity::log('kasstroom', 'Mutatie bijgewerkt: ' . $description . $potSuffix, $amount);
            View::flash('Transactie opgeslagen.');
        } else {
            Transaction::create($periodId, $date, $description, $amount, $settled, $potId);
            Activity::log('kasstroom', 'Mutatie toegevoegd: ' . $description . $potSuffix, $amount);
            View::flash('Transactie toegevoegd.');
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
        }
        View::flash('Transactie verwijderd.');

        header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        exit;
    }
}
