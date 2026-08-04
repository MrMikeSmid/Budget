<?php

namespace App\Controllers;

use App\Models\Activity;
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

    /** Naam voor in de activiteiten-tijdlijn, bijv. "Inkomst" of "Vaste last". */
    abstract protected static function label(): string;

    /** +1 voor inkomsten, -1 voor lasten, zodat de tijdlijn de juiste kleur/richting toont. */
    abstract protected static function amountSign(): int;

    public static function index(): void
    {
        $period = BudgetPeriod::resolveFromRequest();
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
        $model = static::model();

        // Een regel die aan een kasstroommutatie gekoppeld is, wordt via die
        // mutatie bewerkt (die kent ook het werkelijke bedrag en de datum) —
        // dit eigen bewerkformulier is dan niet meer het aangewezen scherm.
        if ($editId && $period) {
            $linkedTransactionId = $model::linkedTransactionId($editId);
            if ($linkedTransactionId) {
                header('Location: ' . View::url('kasstroom', ['period' => $period['id'], 'edit' => $linkedTransactionId]));
                exit;
            }
        }

        View::render(static::view(), [
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'items' => $period ? $model::forPeriod((int) $period['id']) : [],
            'totals' => $period ? $model::totals((int) $period['id']) : ['budgeted' => 0, 'actual' => 0],
            'outstanding' => $period ? $model::outstanding((int) $period['id']) : 0,
            'editing' => $editId ? $model::find($editId) : null,
            'openForm' => !empty($_GET['open']),
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
            Activity::log(static::page(), static::label() . ' bijgewerkt: ' . $description, $budgeted * static::amountSign());
            View::flash('Regel opgeslagen.');
        } else {
            $model::create($periodId, $description, $budgeted, $actual, $status, $isRecurring);
            Activity::log(static::page(), static::label() . ' toegevoegd: ' . $description, $budgeted * static::amountSign());
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

        $item = $model::find($id);
        $model::delete($id);
        if ($item) {
            $amount = (float) $item['budgeted'] * static::amountSign();
            Activity::log(static::page(), static::label() . ' verwijderd: ' . $item['description'], $amount);
        }
        View::flash('Regel verwijderd.');

        header('Location: ' . View::url(static::page(), ['period' => $periodId]));
        exit;
    }
}
