<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
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

        View::render('pots/show', [
            'pot' => $pot,
            'ledger' => Pot::ledger($id, (float) $pot['base_amount']),
            'periods' => BudgetPeriod::all(),
            'openForm' => !empty($_GET['open']),
        ]);
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $potId = (int) ($_POST['pot_id'] ?? 0);
        $periodId = (int) ($_POST['period_id'] ?? 0) ?: null;
        $returnTo = $_POST['return'] ?? 'potje';

        $txn = PotTransaction::find($id);
        $pot = Pot::find($potId);
        PotTransaction::delete($id);

        if ($txn && $pot) {
            Activity::log('potjes', "Mutatie in potje '{$pot['name']}' verwijderd: {$txn['description']}", (float) $txn['amount']);
        }

        View::flash('Transactie verwijderd.');
        if ($returnTo === 'kasstroom') {
            header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        } else {
            header('Location: ' . View::url('potje', ['id' => $potId]));
        }
        exit;
    }

    /**
     * Overboeking tussen los saldo en een potje, of tussen twee potjes.
     * "Los saldo" wordt gemodelleerd als het ontbreken van een potje aan
     * die kant: alleen de kant(en) met een potje krijgen een
     * pot_transactions-rij, zodat het bestaande saldomechanisme (dat
     * pot_transactions per periode van/bij het saldo optelt) de rest doet.
     */
    public static function transfer(): void
    {
        $periodId = (int) ($_POST['period_id'] ?? 0) ?: null;
        $date = $_POST['txn_date'] ?? '';
        $fromPotId = (int) ($_POST['from_pot_id'] ?? 0) ?: null;
        $toPotId = (int) ($_POST['to_pot_id'] ?? 0) ?: null;
        $amount = abs((float) str_replace(',', '.', $_POST['amount'] ?? '0'));
        $description = trim($_POST['description'] ?? '');

        $fromPot = $fromPotId ? Pot::find($fromPotId) : null;
        $toPot = $toPotId ? Pot::find($toPotId) : null;

        $invalid = $date === '' || $amount <= 0
            || (!$fromPot && !$toPot)
            || ($fromPotId && $toPotId && $fromPotId === $toPotId);

        if ($invalid) {
            View::flash('Kies een geldige overboeking (bedrag, datum en een andere bron/bestemming).', 'error');
            header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
            exit;
        }

        $user = Auth::user();
        $fromLabel = $fromPot ? $fromPot['name'] : 'saldo';
        $toLabel = $toPot ? $toPot['name'] : 'saldo';
        $label = $description !== '' ? $description : "Overboeking: {$fromLabel} \u{2192} {$toLabel}";
        // Bij potje-naar-potje krijgt elke kant de id van de andere kant als
        // transfer_pot_id: dat markeert de rij als "raakt los saldo niet",
        // zodat de kasstroomlijst hem overslaat (i.p.v. hem als storting/
        // opname op los saldo te tonen, wat hij niet is).
        if ($fromPot) {
            PotTransaction::create($fromPot['id'], $user['id'] ?? null, $periodId, $date, $label, -$amount, $toPot['id'] ?? null);
            Activity::log('potjes', "Overboeking vanuit potje '{$fromPot['name']}' naar {$toLabel}: {$label}", -$amount);
        }
        if ($toPot) {
            PotTransaction::create($toPot['id'], $user['id'] ?? null, $periodId, $date, $label, $amount, $fromPot['id'] ?? null);
            Activity::log('potjes', "Overboeking naar potje '{$toPot['name']}' vanuit {$fromLabel}: {$label}", $amount);
        }

        View::flash('Overboeking uitgevoerd.');
        header('Location: ' . View::url('kasstroom', ['period' => $periodId]));
        exit;
    }
}
