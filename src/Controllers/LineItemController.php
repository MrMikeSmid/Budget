<?php

namespace App\Controllers;

use App\Models\BudgetPeriod;
use App\Support\View;

/**
 * Gedeelde basis voor IncomeController en FixedCostController: beide
 * beheren regels (omschrijving/begroot/werkelijk/status) binnen een periode.
 */
abstract class LineItemController
{
    /** @return class-string<\App\Models\LineItem> */
    abstract protected static function model(): string;

    abstract protected static function view(): string;

    abstract protected static function page(): string;

    public static function index(): void
    {
        $period = BudgetPeriod::resolveFromRequest();
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
        $model = static::model();

        View::render(static::view(), [
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'items' => $period ? $model::forPeriod((int) $period['id']) : [],
            'totals' => $period ? $model::totals((int) $period['id']) : ['budgeted' => 0, 'actual' => 0],
            'outstanding' => $period ? $model::outstanding((int) $period['id']) : 0,
            'editing' => $editId ? $model::find($editId) : null,
        ]);
    }

    public static function save(): void
    {
        $model = static::model();
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $budgeted = (float) str_replace(',', '.', $_POST['budgeted'] ?? '0');
        $actualRaw = trim($_POST['actual'] ?? '');
        $actual = $actualRaw === '' ? null : (float) str_replace(',', '.', $actualRaw);
        $status = trim($_POST['status'] ?? '');
        $isRecurring = !empty($_POST['is_recurring']);

        if ($description === '' || $periodId === 0) {
            View::flash('Vul een omschrijving in.', 'error');
        } elseif ($id > 0) {
            $model::update($id, $description, $budgeted, $actual, $status, $isRecurring);
            View::flash('Regel opgeslagen.');
        } else {
            $model::create($periodId, $description, $budgeted, $actual, $status, $isRecurring);
            View::flash('Regel toegevoegd.');
        }

        header('Location: ' . View::url(static::page(), ['period' => $periodId]));
        exit;
    }

    public static function delete(): void
    {
        $model = static::model();
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);

        $model::delete($id);
        View::flash('Regel verwijderd.');

        header('Location: ' . View::url(static::page(), ['period' => $periodId]));
        exit;
    }
}
