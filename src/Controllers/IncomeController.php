<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\IncomeItem;
use App\Support\View;

final class IncomeController extends LineItemController
{
    protected static function model(): string
    {
        return IncomeItem::class;
    }

    protected static function view(): string
    {
        return 'income/index';
    }

    protected static function page(): string
    {
        return 'inkomsten';
    }

    protected static function label(): string
    {
        return 'Inkomst';
    }

    protected static function amountSign(): int
    {
        return 1;
    }

    /**
     * Overschrijft LineItemController::save(): inkomsten hebben, net als
     * vaste lasten, een terugkeerfrequentie (interval/modus/datum) i.p.v.
     * altijd elke periode terug te komen.
     */
    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $budgeted = (float) str_replace(',', '.', $_POST['budgeted'] ?? '0');
        $actualRaw = trim($_POST['actual'] ?? '');
        $actual = $actualRaw === '' ? null : (float) str_replace(',', '.', $actualRaw);
        $status = trim($_POST['status'] ?? '');
        $isRecurring = !empty($_POST['is_recurring']);
        $recurrenceInterval = IncomeItem::normalizeInterval((string) ($_POST['recurrence_interval'] ?? 'maandelijks'));
        $recurrenceMode = IncomeItem::normalizeMode((string) ($_POST['recurrence_mode'] ?? 'periode'));
        $recurrenceDate = trim($_POST['recurrence_date'] ?? '') ?: null;

        if ($description === '' || $periodId === 0) {
            View::flash('Vul een omschrijving in.', 'error');
            header('Location: ' . View::url('inkomsten', ['period' => $periodId]));
            exit;
        }

        if ($id > 0) {
            IncomeItem::updateFull($id, $description, $budgeted, $actual, $status, $isRecurring, $recurrenceInterval, $recurrenceMode, $recurrenceDate);
            Activity::log('inkomsten', 'Inkomst bijgewerkt: ' . $description, $budgeted);
            View::flash('Regel opgeslagen.');
        } else {
            IncomeItem::createFull($periodId, $description, $budgeted, $actual, $status, $isRecurring, $recurrenceInterval, $recurrenceMode, $recurrenceDate);
            Activity::log('inkomsten', 'Inkomst toegevoegd: ' . $description, $budgeted);
            View::flash('Regel toegevoegd.');
        }

        // Er wordt vooruit gepland: een (net) terugkerende regel moet ook
        // verschijnen in periodes die al bestonden vóórdat deze regel er was.
        if ($isRecurring) {
            IncomeItem::fillFuturePeriods($periodId);
        }

        header('Location: ' . View::url('inkomsten', ['period' => $periodId]));
        exit;
    }
}
