<?php

namespace App\Controllers;

use App\Models\AiAdviceCache;
use App\Models\BudgetPeriod;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Models\Loan;
use App\Models\Pot;
use App\Support\GeminiService;
use App\Support\View;

/**
 * JSON-endpoint dat het dashboard asynchroon aanroept voor het AI-advies
 * (zie assets/js/app.js). Cachet het resultaat per periode zodat niet elke
 * paginaweergave een nieuwe (betaalde) Gemini-aanroep kost.
 */
final class AiAdviceController
{
    private const CACHE_TTL_SECONDS = 1800;

    public static function index(): void
    {
        header('Content-Type: application/json');

        $periodId = (int) ($_GET['period'] ?? 0);
        $period = $periodId > 0 ? BudgetPeriod::find($periodId) : null;

        if (!$period) {
            echo json_encode(['ok' => false, 'error' => 'Geen geldige periode geselecteerd.']);
            return;
        }

        $forceRefresh = !empty($_GET['refresh']);

        if (!$forceRefresh) {
            $cached = AiAdviceCache::get($periodId);
            if ($cached && self::isFresh($cached['generated_at'])) {
                echo json_encode(['ok' => true, 'text' => $cached['advice_text'], 'cached' => true]);
                return;
            }
        }

        $result = GeminiService::advise(self::buildSummary($periodId));

        if ($result['ok']) {
            AiAdviceCache::save($periodId, $result['text']);
            echo json_encode(['ok' => true, 'text' => $result['text'], 'cached' => false]);
            return;
        }

        echo json_encode(['ok' => false, 'error' => $result['error']]);
    }

    private static function isFresh(string $generatedAt): bool
    {
        return (time() - strtotime($generatedAt . ' UTC')) < self::CACHE_TTL_SECONDS;
    }

    /**
     * Alleen samengevatte, geanonimiseerde bedragen — geen namen, geen
     * individuele omschrijvingen (die kunnen namen bevatten, bijv. "Loon
     * Mike"), geen rekeninggegevens.
     */
    private static function buildSummary(int $periodId): string
    {
        $balance = BudgetPeriod::endingBalance($periodId);
        $paidActual = FixedCost::paidTotal($periodId);
        $openBudgeted = FixedCost::outstanding($periodId);

        $incomeTotals = IncomeItem::totals($periodId);
        $incomeActual = IncomeItem::receivedTotal($periodId);
        $incomeOutstanding = IncomeItem::outstanding($periodId);

        $pots = Pot::allForPeriod($periodId);
        $leefpotjesTotal = array_sum(array_map(
            static fn ($p) => (float) $p['resolved_amount'],
            array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'leefpotje')
        ));
        $spaarpotjesTotal = array_sum(array_map(
            static fn ($p) => (float) $p['resolved_amount'],
            array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'spaarpotje')
        ));

        $unpaidCosts = FixedCost::unpaidForPeriod($periodId);
        $unreceivedIncome = IncomeItem::unreceivedForPeriod($periodId);
        $partialLoans = Loan::partialPaymentsForPeriod($periodId);

        $lines = [
            sprintf('Saldo op de rekening: %s', View::money($balance)),
            sprintf(
                'Inkomsten: begroot %s, werkelijk ontvangen %s, nog te ontvangen %s (%d regels nog niet ontvangen)',
                View::money((float) $incomeTotals['budgeted']),
                View::money($incomeActual),
                View::money($incomeOutstanding),
                count($unreceivedIncome)
            ),
            sprintf(
                'Vaste lasten: al betaald %s, nog openstaand %s (%d regels nog niet betaald)',
                View::money($paidActual),
                View::money($openBudgeted),
                count($unpaidCosts)
            ),
            sprintf('Leefpotjes totaal: %s', View::money($leefpotjesTotal)),
            sprintf('Spaarpotjes totaal: %s', View::money($spaarpotjesTotal)),
        ];

        if (!empty($partialLoans)) {
            $lines[] = sprintf('Er zijn %d leningtermijnen deze periode waarvan maar een deel betaald is.', count($partialLoans));
        }

        return implode("\n", $lines);
    }
}
