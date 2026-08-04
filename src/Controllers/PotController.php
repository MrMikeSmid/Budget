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
        $period = BudgetPeriod::resolveFromRequest();

        View::render('pots/index', [
            'pots' => $period ? Pot::allForPeriod((int) $period['id']) : Pot::all(),
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'openForm' => !empty($_GET['open']),
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0) ?: null;
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $linkedPeriodId = (int) ($_POST['linked_period_id'] ?? 0) ?: null;
        $amountRaw = trim($_POST['amount'] ?? '');
        $amount = $amountRaw === '' ? null : (float) str_replace(',', '.', $amountRaw);
        $type = Pot::normalizeType((string) ($_POST['type'] ?? 'leefpotje'));
        $returnTo = $_POST['return'] ?? '';
        $redirect = $returnTo === 'potje' && $id > 0
            ? View::url('potje', ['id' => $id])
            : View::url('potjes', $periodId ? ['period' => $periodId] : []);

        if ($name === '') {
            View::flash('Vul een naam in.', 'error');
        } elseif ($id > 0) {
            Pot::update($id, $name, $icon, $amount, $note, $linkedPeriodId, $type);
            Activity::log('potjes', 'Potje bijgewerkt: ' . $name);
            View::flash('Potje opgeslagen.');
        } else {
            Pot::create($name, $icon, $amount, $note, $linkedPeriodId, $type, $periodId);
            Activity::log('potjes', 'Potje aangemaakt: ' . $name);
            View::flash('Potje toegevoegd.');
        }

        header('Location: ' . $redirect);
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0) ?: null;
        $pot = Pot::find($id);
        Pot::delete($id, $periodId);
        if ($pot) {
            Activity::log('potjes', 'Potje verwijderd: ' . $pot['name']);
        }
        View::flash('Potje verwijderd.');
        header('Location: ' . View::url('potjes', $periodId ? ['period' => $periodId] : []));
        exit;
    }
}
