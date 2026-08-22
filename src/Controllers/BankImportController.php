<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\FixedCost;
use App\Models\Transaction;
use App\Support\BankImport\BankImportParseException;
use App\Support\BankImport\BankImportParser;
use App\Support\View;

/**
 * Bankbestand (ING/Knab-CSV, ABN AMRO CAMT.053/MT940) inlezen, alleen de
 * uitgaven eruit halen, en per regel laten aanvinken of het een vaste last
 * is (en of die terugkerend is) en welke categorie erbij hoort — vóórdat er
 * iets weggeschreven wordt. Geparste rijen leven alleen in de sessie tussen
 * upload → review → commit, er komt geen permanente opslag van het bestand
 * of een "pending import"-tabel bij.
 */
final class BankImportController
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = [
        'ing' => ['csv'],
        'knab' => ['csv'],
        'abn_camt053' => ['xml'],
        'abn_mt940' => ['sta', 'swi', 'txt', '940'],
    ];

    public static function index(): void
    {
        View::render('bank-import/upload', [
            'banks' => BankImportParser::BANKS,
        ]);
    }

    public static function upload(): void
    {
        $bank = (string) ($_POST['bank'] ?? '');
        $file = $_FILES['bestand'] ?? null;

        if (!array_key_exists($bank, BankImportParser::BANKS) || !$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('Kies een bank en een bestand.', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            View::flash('Dat bestand kon niet gebruikt worden — controleer of het niet te groot is (max 8 MB).', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        $extension = mb_strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS[$bank] ?? [], true)) {
            View::flash('Dat bestandstype hoort niet bij de gekozen bank/formaat.', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false || trim($contents) === '') {
            View::flash('Kon het bestand niet lezen — probeer het opnieuw.', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        try {
            $rows = BankImportParser::parse($bank, $contents);
        } catch (BankImportParseException $e) {
            View::flash($e->getMessage(), 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        $expenses = array_values(array_filter($rows, static fn (array $r): bool => $r['amount'] < 0));

        if (empty($expenses)) {
            View::flash('Geen uitgaven gevonden in dit bestand.', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        foreach ($expenses as &$row) {
            $duplicate = Transaction::findDuplicate($row['date'], $row['amount'], $row['description'], $row['bank_reference']);
            $row['is_duplicate'] = $duplicate !== null;
        }
        unset($row);

        $_SESSION['bank_import_rows'] = $expenses;
        $_SESSION['bank_import_bank'] = $bank;

        header('Location: ' . View::url('kasstroom-import-review'));
        exit;
    }

    public static function review(): void
    {
        $rows = $_SESSION['bank_import_rows'] ?? [];
        if (empty($rows)) {
            View::flash('Geen import gevonden — upload eerst een bestand.', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        View::render('bank-import/review', [
            'rows' => $rows,
            'bank' => (string) ($_SESSION['bank_import_bank'] ?? ''),
            'banks' => BankImportParser::BANKS,
            'categories' => Category::allByType('uitgaven'),
        ]);
    }

    public static function commit(): void
    {
        $rows = $_SESSION['bank_import_rows'] ?? [];
        if (empty($rows)) {
            View::flash('Geen import gevonden — upload eerst een bestand.', 'error');
            header('Location: ' . View::url('kasstroom-import'));
            exit;
        }

        $importedCount = 0;
        $fixedLastCount = 0;
        $skippedNoPeriod = 0;
        $ignoredCount = 0;

        foreach ($rows as $index => $row) {
            if (!empty($row['is_duplicate'])) {
                continue;
            }

            if (!empty($_POST['negeren'][$index])) {
                $ignoredCount++;
                continue;
            }

            $period = BudgetPeriod::findByDate($row['date']);
            if (!$period) {
                $skippedNoPeriod++;
                continue;
            }

            $periodId = (int) $period['id'];
            $categoryId = (int) ($_POST['category_id'][$index] ?? 0) ?: null;
            $isFixedCost = !empty($_POST['vaste_last'][$index]);
            $isRecurring = $isFixedCost && !empty($_POST['terugkerend'][$index]);

            $fixedCostId = null;
            if ($isFixedCost) {
                $fixedCostId = FixedCost::createFull(
                    $periodId,
                    $row['description'],
                    abs((float) $row['amount']),
                    null,
                    'Betaald',
                    $isRecurring,
                    'maandelijks',
                    'periode',
                    null,
                    null,
                    null,
                    $categoryId
                );
            }

            Transaction::create(
                $periodId,
                $row['date'],
                $row['description'],
                (float) $row['amount'],
                true,
                null,
                $fixedCostId,
                null,
                $categoryId,
                $row['bank_reference'] ?? null
            );

            if ($fixedCostId) {
                FixedCost::syncActualFromTransactions($fixedCostId);
                if ($isRecurring) {
                    FixedCost::fillFuturePeriods($periodId);
                }
                $fixedLastCount++;
            }

            $importedCount++;
        }

        unset($_SESSION['bank_import_rows'], $_SESSION['bank_import_bank']);

        if ($importedCount > 0) {
            Activity::log(
                'kasstroom',
                sprintf('%d mutatie%s geïmporteerd (%d als vaste last)', $importedCount, $importedCount === 1 ? '' : 's', $fixedLastCount)
            );
        }

        $message = $importedCount . ' mutatie' . ($importedCount === 1 ? '' : 's') . ' geïmporteerd'
            . ($fixedLastCount > 0 ? ', waarvan ' . $fixedLastCount . ' als vaste last' : '') . '.';
        if ($ignoredCount > 0) {
            $message .= ' ' . $ignoredCount . ' regel' . ($ignoredCount === 1 ? '' : 's') . ' genegeerd.';
        }
        if ($skippedNoPeriod > 0) {
            $message .= ' ' . $skippedNoPeriod . ' regel' . ($skippedNoPeriod === 1 ? '' : 's') . ' overgeslagen: geen periode gevonden voor die datum.';
        }
        View::flash($message, $skippedNoPeriod > 0 ? 'warning' : 'success');

        header('Location: ' . View::url('vaste-lasten'));
        exit;
    }
}
