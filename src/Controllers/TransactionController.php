<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
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

        if ($description === '' || $date === '' || $periodId === 0) {
            View::flash('Vul een datum en omschrijving in.', 'error');
        } elseif ($id > 0) {
            Transaction::update($id, $date, $description, $amount, $settled);
            View::flash('Transactie opgeslagen.');
        } else {
            Transaction::create($periodId, $date, $description, $amount, $settled);
            View::flash('Transactie toegevoegd.');
        }

        header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);

        Transaction::delete($id);
        View::flash('Transactie verwijderd.');

        header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        exit;
    }
}
