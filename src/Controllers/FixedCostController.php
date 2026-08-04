<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\Loan;
use App\Support\View;

final class FixedCostController extends LineItemController
{
    protected static function model(): string
    {
        return FixedCost::class;
    }

    protected static function view(): string
    {
        return 'fixed-costs/index';
    }

    protected static function page(): string
    {
        return 'vaste-lasten';
    }

    protected static function label(): string
    {
        return 'Vaste last';
    }

    protected static function amountSign(): int
    {
        return -1;
    }

    /**
     * Overschrijft LineItemController::save(): vaste lasten hebben extra
     * terugkeer-velden (interval/modus/datum) en kunnen aan een lening
     * gekoppeld zijn, waarbij "Betaald" een aflossing op die lening boekt.
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
        $recurrenceInterval = FixedCost::normalizeInterval((string) ($_POST['recurrence_interval'] ?? 'maandelijks'));
        $recurrenceMode = FixedCost::normalizeMode((string) ($_POST['recurrence_mode'] ?? 'periode'));
        $recurrenceDate = trim($_POST['recurrence_date'] ?? '') ?: null;

        // Een nieuwe last is nog niet betaald — de betaling wordt verwerkt
        // op de kasstroompagina (via "Bron" koppelen aan deze last).
        if ($id === 0 && $status === '') {
            $status = 'Open';
        }

        if ($description === '' || $periodId === 0) {
            View::flash('Vul een omschrijving in.', 'error');
            header('Location: ' . View::url('vaste-lasten', ['period' => $periodId]));
            exit;
        }

        if ($id > 0) {
            FixedCost::updateFull($id, $description, $budgeted, $actual, $status, $isRecurring, $recurrenceInterval, $recurrenceMode, $recurrenceDate);
            self::syncLoanPayment($id, $status, $actual, $budgeted);
            Activity::log('vaste-lasten', 'Vaste last bijgewerkt: ' . $description, $budgeted * -1);
            View::flash('Regel opgeslagen.');
        } else {
            $newId = FixedCost::createFull($periodId, $description, $budgeted, $actual, $status, $isRecurring, $recurrenceInterval, $recurrenceMode, $recurrenceDate);
            self::syncLoanPayment($newId, $status, $actual, $budgeted);
            Activity::log('vaste-lasten', 'Vaste last toegevoegd: ' . $description, $budgeted * -1);
            View::flash('Regel toegevoegd.');
        }

        // Er wordt vooruit gepland: een (net) terugkerende last moet ook
        // verschijnen in periodes die al bestonden vóórdat deze last er was.
        if ($isRecurring) {
            FixedCost::fillFuturePeriods($periodId);
        }

        header('Location: ' . View::url('vaste-lasten', ['period' => $periodId]));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0);

        $item = FixedCost::find($id);
        FixedCost::delete($id); // ON DELETE CASCADE ruimt een eventuele loan_payment vanzelf op
        if ($item) {
            Activity::log('vaste-lasten', 'Vaste last verwijderd: ' . $item['description'], (float) $item['budgeted'] * -1);
        }
        View::flash('Regel verwijderd.');

        header('Location: ' . View::url('vaste-lasten', ['period' => $periodId]));
        exit;
    }

    /**
     * Boekt of draait een lening-aflossing terug op basis van de status.
     * "Betaald" (of variant) boekt de aflossing; alles anders draait 'm terug.
     * Een bestaande boeking wordt bijgewerkt als het bedrag is aangepast.
     */
    private static function syncLoanPayment(int $fixedCostId, string $status, ?float $actual, float $budgeted): void
    {
        $item = FixedCost::find($fixedCostId);
        if (!$item || empty($item['loan_id'])) {
            return;
        }

        $isPaid = str_starts_with(mb_strtolower(trim($status)), 'betaald');
        $existing = Loan::paymentForFixedCost($fixedCostId);
        $amount = $actual ?? $budgeted;

        if ($isPaid && !$existing) {
            Loan::addPayment((int) $item['loan_id'], $fixedCostId, $amount);
        } elseif ($isPaid && $existing && (float) $existing['amount'] !== $amount) {
            Loan::updatePaymentAmountForFixedCost($fixedCostId, $amount);
        } elseif (!$isPaid && $existing) {
            Loan::removePaymentForFixedCost($fixedCostId);
        }
    }
}
