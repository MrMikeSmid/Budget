<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\Pot;
use App\Models\PotTransaction;
use App\Support\Auth;
use App\Support\View;

final class PotTransactionController
{
    public static function index(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $pot = Pot::withDetails($id);

        if (!$pot) {
            http_response_code(404);
            View::render('errors/404', ['page' => 'potje']);
            return;
        }

        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        View::render('pots/show', [
            'pot' => $pot,
            'ledger' => Pot::ledger($id, (float) $pot['base_amount']),
            'editing' => $editId ? PotTransaction::find($editId) : null,
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $potId = (int) ($_POST['pot_id'] ?? 0);
        $date = $_POST['txn_date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $amount = (float) str_replace(',', '.', $_POST['amount'] ?? '0');

        $pot = Pot::find($potId);

        if ($description === '' || $date === '' || !$pot) {
            View::flash('Vul een datum en omschrijving in.', 'error');
        } elseif ($id > 0) {
            PotTransaction::update($id, $date, $description, $amount);
            Activity::log('potjes', "Mutatie in potje '{$pot['name']}' bijgewerkt: {$description}", $amount);
            View::flash('Transactie opgeslagen.');
        } else {
            $user = Auth::user();
            PotTransaction::create($potId, $user['id'] ?? null, $date, $description, $amount);
            Activity::log('potjes', "Mutatie in potje '{$pot['name']}': {$description}", $amount);
            View::flash('Transactie toegevoegd.');
        }

        header('Location: ' . View::url('potje', ['id' => $potId]));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $potId = (int) ($_POST['pot_id'] ?? 0);

        $txn = PotTransaction::find($id);
        $pot = Pot::find($potId);
        PotTransaction::delete($id);

        if ($txn && $pot) {
            Activity::log('potjes', "Mutatie in potje '{$pot['name']}' verwijderd: {$txn['description']}", (float) $txn['amount']);
        }

        View::flash('Transactie verwijderd.');
        header('Location: ' . View::url('potje', ['id' => $potId]));
        exit;
    }
}
