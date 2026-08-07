<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\FixedCost;
use App\Models\Loan;
use App\Support\View;

final class LoanController
{
    public static function index(): void
    {
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        View::render('loans/index', [
            'loans' => Loan::all(),
            'editing' => $editId ? Loan::find($editId) : null,
            'hasActivePeriod' => BudgetPeriod::active() !== null,
            'categories' => Category::all(),
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $totalAmount = (float) str_replace(',', '.', $_POST['total_amount'] ?? '0');
        $monthlyPayment = (float) str_replace(',', '.', $_POST['monthly_payment'] ?? '0');
        $note = trim($_POST['note'] ?? '');
        $interval = FixedCost::normalizeInterval((string) ($_POST['recurrence_interval'] ?? 'maandelijks'));
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;

        if ($name === '' || $totalAmount <= 0 || $monthlyPayment <= 0) {
            View::flash('Vul een naam, een totaalbedrag en een termijnbedrag (beide groter dan 0) in.', 'error');
            header('Location: ' . View::url('leningen'));
            exit;
        }

        if ($id > 0) {
            Loan::update($id, $name, $totalAmount, $monthlyPayment, $note, $categoryId);
            Activity::log('leningen', 'Lening bijgewerkt: ' . $name);
            View::flash('Lening opgeslagen.');
            header('Location: ' . View::url('leningen'));
            exit;
        }

        $activePeriod = BudgetPeriod::active();
        if (!$activePeriod) {
            View::flash('Maak eerst een budgetperiode aan voordat je een lening toevoegt — de eerste termijn moet ergens op de lasten komen te staan.', 'error');
            header('Location: ' . View::url('leningen'));
            exit;
        }

        $loanId = Loan::create($name, $totalAmount, $monthlyPayment, $note, $categoryId);

        $fixedCostId = FixedCost::createFull(
            (int) $activePeriod['id'],
            $name,
            $monthlyPayment,
            null,
            '',
            true,
            $interval,
            'periode',
            null,
            null,
            $loanId,
            $categoryId
        );

        // Er wordt vooruit gepland: een terugkerende last moet ook verschijnen
        // in periodes die al bestonden vóórdat deze lening er was (zie
        // FixedCostController::save() voor hetzelfde patroon bij gewone
        // vaste lasten — bij leningen ontbrak deze stap, waardoor een
        // termijn niet meekwam in een al bestaande latere periode).
        FixedCost::fillFuturePeriods((int) $activePeriod['id']);

        Activity::log('leningen', 'Lening aangemaakt: ' . $name, -$monthlyPayment);
        Activity::log('vaste-lasten', 'Vaste last toegevoegd (lening): ' . $name, -$monthlyPayment);

        View::flash('Lening aangemaakt en als vaste last toegevoegd aan "' . $activePeriod['name'] . '".');
        header('Location: ' . View::url('leningen'));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $loan = Loan::find($id);
        Loan::delete($id);
        if ($loan) {
            Activity::log('leningen', 'Lening verwijderd: ' . $loan['name']);
        }
        View::flash('Lening verwijderd. Eerder aangemaakte vaste lasten blijven staan, maar zijn niet meer aan deze lening gekoppeld.');
        header('Location: ' . View::url('leningen'));
        exit;
    }
}
