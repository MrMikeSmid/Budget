<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
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

        if ($name === '' || $start === '' || $end === '') {
            View::flash('Vul een naam en begin- en einddatum in.', 'error');
            header('Location: ' . View::url('periods'));
            exit;
        }

        if ($id > 0) {
            BudgetPeriod::update($id, $name, $start, $end, $opening);
        } else {
            $id = BudgetPeriod::create($name, $start, $end, $opening);
        }

        if ($makeActive) {
            BudgetPeriod::setActive($id);
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
        BudgetPeriod::delete($id);
        View::flash('Periode verwijderd.');
        header('Location: ' . View::url('periods'));
        exit;
    }
}
