<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Support\View;

final class PeriodController
{
    public static function index(): void
    {
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        View::render('periods/index', [
            'periods' => BudgetPeriod::all(),
            'editing' => $editId ? BudgetPeriod::find($editId) : null,
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        $opening = (float) str_replace(',', '.', $_POST['opening_balance'] ?? '0');
        $makeActive = !empty($_POST['is_active']);
        $copyRecurring = !empty($_POST['copy_recurring']);

        if ($name === '' || $start === '' || $end === '') {
            View::flash('Vul een naam en begin- en einddatum in.', 'error');
            header('Location: ' . View::url('periods'));
            exit;
        }

        $isNew = $id === 0;

        if ($isNew) {
            $id = BudgetPeriod::create($name, $start, $end, $opening);
            Activity::log('periods', 'Periode aangemaakt: ' . $name);
        } else {
            BudgetPeriod::update($id, $name, $start, $end, $opening);
            Activity::log('periods', 'Periode bijgewerkt: ' . $name);
        }

        if ($makeActive) {
            BudgetPeriod::setActive($id);
        }

        if ($isNew && $copyRecurring) {
            $previous = BudgetPeriod::previousBefore($id, $start);
            if ($previous) {
                IncomeItem::copyRecurring((int) $previous['id'], $id);
                FixedCost::copyRecurring((int) $previous['id'], $id);
            }
        }

        View::flash('Periode opgeslagen.');
        header('Location: ' . View::url('periods'));
        exit;
    }

    public static function activate(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        BudgetPeriod::setActive($id);
        View::flash('Periode actief gezet.');
        header('Location: ' . View::url('periods'));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $period = BudgetPeriod::find($id);
        BudgetPeriod::delete($id);
        if ($period) {
            Activity::log('periods', 'Periode verwijderd: ' . $period['name']);
        }
        View::flash('Periode verwijderd.');
        header('Location: ' . View::url('periods'));
        exit;
    }
}
