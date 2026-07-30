<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\Pot;
use App\Support\View;

final class PotController
{
    public static function index(): void
    {
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        View::render('pots/index', [
            'pots' => Pot::all(),
            'periods' => BudgetPeriod::all(),
            'editing' => $editId ? Pot::find($editId) : null,
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $linkedPeriodId = (int) ($_POST['linked_period_id'] ?? 0) ?: null;
        $amountRaw = trim($_POST['amount'] ?? '');
        $amount = $amountRaw === '' ? null : (float) str_replace(',', '.', $amountRaw);

        if ($name === '') {
            View::flash('Vul een naam in.', 'error');
        } elseif ($id > 0) {
            Pot::update($id, $name, $icon, $amount, $note, $linkedPeriodId);
            Activity::log('potjes', 'Potje bijgewerkt: ' . $name);
            View::flash('Potje opgeslagen.');
        } else {
            Pot::create($name, $icon, $amount, $note, $linkedPeriodId);
            Activity::log('potjes', 'Potje aangemaakt: ' . $name);
            View::flash('Potje toegevoegd.');
        }

        header('Location: ' . View::url('potjes'));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $pot = Pot::find($id);
        Pot::delete($id);
        if ($pot) {
            Activity::log('potjes', 'Potje verwijderd: ' . $pot['name']);
        }
        View::flash('Potje verwijderd.');
        header('Location: ' . View::url('potjes'));
        exit;
    }
}
