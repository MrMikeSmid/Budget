<?php

namespace App\Support\BankImport;

/**
 * MT940 (SWIFT-statement), zoals ABN AMRO die aanbiedt via "Bij- en
 * afschrijvingen downloaden" → MT940. Regelgebaseerd tag-formaat:
 * elke mutatie is een :61:-regel (datum, bedrag, D/C-teken, referentie),
 * direct gevolgd door één of meer :86:-regels met de omschrijving.
 *
 * Minder eenvoudig robuust te parsen dan CAMT.053 (geen XML-schema om op
 * te valideren), dus deze parser is voorzichtig: de financiële kern
 * (datum/bedrag/teken) moet kloppen of de regel wordt overgeslagen i.p.v.
 * een gok te wagen; de referentie is best-effort (mag null zijn).
 */
final class AbnMt940Parser
{
    public static function parse(string $contents): array
    {
        $lines = BankImportSupport::splitLines($contents);
        if (empty($lines)) {
            throw new BankImportParseException('Dit MT940-bestand bevat geen regels.');
        }

        $rows = [];
        $current = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, ':61:')) {
                if ($current !== null) {
                    $rows[] = self::finalizeRow($current);
                }
                $current = self::parseStatementLine(substr($line, 4));
                continue;
            }

            if (str_starts_with($line, ':86:') && $current !== null) {
                $current['description_parts'][] = trim(substr($line, 4));
                continue;
            }

            // Continuering van een meerregelige :86: (regels zonder eigen
            // tag horen bij de vorige :86:), en het einde van een mutatie
            // zodra een andere tag begint (bijv. de volgende :61: of :62F:).
            if ($current !== null && !str_starts_with($line, ':')) {
                $current['description_parts'][] = trim($line);
                continue;
            }

            if ($current !== null && !str_starts_with($line, ':86:') && !str_starts_with($line, ':61:')) {
                $rows[] = self::finalizeRow($current);
                $current = null;
            }
        }

        if ($current !== null) {
            $rows[] = self::finalizeRow($current);
        }

        $rows = array_values(array_filter($rows));

        if (empty($rows)) {
            throw new BankImportParseException('Geen mutaties gevonden in dit bestand — klopt het gekozen bank/formaat (ABN AMRO MT940)?');
        }

        return $rows;
    }

    /**
     * @return array{date: ?string, amount: ?float, reference: ?string, description_parts: string[]}|null
     */
    private static function parseStatementLine(string $body): ?array
    {
        // Waardedatum YYMMDD, optionele boekdatum MMDD, D/C(/RD/RC)-teken,
        // bedrag (komma-decimaal), rest (transactietype + referentie).
        if (!preg_match('/^(\d{6})(\d{4})?(R?[DC])([\d,]+)(.*)$/', $body, $m)) {
            return null;
        }

        $date = BankImportSupport::parseDate($m[1], 'ymd');
        if ($date === null) {
            return null;
        }

        $isDebit = str_contains($m[3], 'D');
        $amount = BankImportSupport::parseAmount($m[4]);
        $amount = $isDebit ? -$amount : $amount;

        // Na het transactietype (1 letter + 3 tekens, bijv. "NTRF") volgt de
        // klantreferentie tot aan een eventuele "//bankreferentie" — best
        // effort, "NONREF" (staat voor "geen referentie") telt niet mee.
        $reference = null;
        if (preg_match('/^.{4}([A-Za-z0-9]+)/', $m[5], $refMatch)) {
            $candidate = trim($refMatch[1]);
            if ($candidate !== '' && mb_strtolower($candidate) !== 'nonref') {
                $reference = $candidate;
            }
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'reference' => $reference,
            'description_parts' => [],
        ];
    }

    private static function finalizeRow(?array $row): ?array
    {
        if ($row === null || $row['date'] === null || $row['amount'] === null) {
            return null;
        }

        $description = trim(implode(' ', array_filter($row['description_parts'], static fn (string $p): bool => $p !== '')));

        return [
            'date' => $row['date'],
            'description' => $description !== '' ? $description : 'Onbekende mutatie',
            'amount' => $row['amount'],
            'bank_reference' => $row['reference'],
        ];
    }
}
