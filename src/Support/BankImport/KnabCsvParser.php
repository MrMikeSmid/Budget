<?php

namespace App\Support\BankImport;

/**
 * Knab-CSV ("Zoeken & Downloaden" → CSV, komma-gescheiden max 2000 mutaties):
 * Rekeningnummer;Transactiedatum;Valutacode;CreditDebet;Bedrag;
 * Tegenrekeningnummer;Tegenrekeninghouder;Valutadatum;Betaalwijze;
 * Omschrijving;Type betaling;Machtigingsnummer;Incassant ID;Adres;
 * Referentie;Boekdatum
 *
 * Ondanks de naam "CSV (komma-gescheiden)" gebruikt Knab zelf puntkomma als
 * veldscheiding (bevestigd bij onderzoek) — zelfde als ING/de meeste NL-banken.
 */
final class KnabCsvParser
{
    private const COL_DATE = 1;
    private const COL_CREDIT_DEBET = 3;
    private const COL_AMOUNT = 4;
    private const COL_COUNTERPARTY_NAME = 6;
    private const COL_DESCRIPTION = 9;
    private const COL_REFERENCE = 14;

    public static function parse(string $contents): array
    {
        $lines = BankImportSupport::splitLines($contents);
        if (empty($lines)) {
            throw new BankImportParseException('Dit Knab-bestand bevat geen regels.');
        }

        $rows = [];
        foreach ($lines as $index => $line) {
            $fields = str_getcsv($line, ';', '"', '');

            // Header overslaan — ongeacht regelnummer, want sommige exports
            // hebben eerst een "KNAB EXPORT"-preambule-regel vóór de echte
            // header (die dan op index 1 staat i.p.v. 0).
            if (isset($fields[0]) && mb_strtolower(trim($fields[0])) === 'rekeningnummer') {
                continue;
            }

            if (count($fields) <= self::COL_REFERENCE) {
                continue; // lege/onvolledige regel (bijv. de "KNAB EXPORT"-preambule)
            }

            $rawDate = trim($fields[self::COL_DATE]);
            $direction = mb_strtolower(trim($fields[self::COL_CREDIT_DEBET]));
            $rawAmount = trim($fields[self::COL_AMOUNT]);
            $omschrijving = trim($fields[self::COL_DESCRIPTION]);
            $counterparty = trim($fields[self::COL_COUNTERPARTY_NAME]);
            $reference = trim($fields[self::COL_REFERENCE]);

            if ($rawDate === '' || $rawAmount === '') {
                continue;
            }

            $date = BankImportSupport::parseDate($rawDate, 'd-m-Y');
            if ($date === null) {
                continue;
            }

            $amount = BankImportSupport::parseAmount($rawAmount);
            // "Debet"/"D" = uitgave, "Credit"/"C" = inkomst — alleen de eerste
            // letter checken is robuust tegen kleine schrijfwijze-verschillen.
            $amount = str_starts_with($direction, 'd') ? -$amount : $amount;

            // Bij pastransacties bevat "Omschrijving" alleen plaats/tijd/pas-
            // nummer-boilerplate (bijv. "SCHOORL 21-08-2026 12:08 Pas: 4407")
            // — de echte winkelnaam staat in "Tegenrekeninghouder". Bij
            // overschrijvingen/iDEAL bevat "Omschrijving" juist wél nuttige
            // info (notitie, afzender), die als aanvulling wordt toegevoegd
            // als die niet al in de naam voorkomt.
            $description = $counterparty !== '' ? $counterparty : ($omschrijving !== '' ? $omschrijving : 'Onbekende mutatie');
            if (
                $omschrijving !== ''
                && $omschrijving !== $description
                && !preg_match('/pas:\s*\d+\s*$/i', $omschrijving)
                && stripos($omschrijving, $description) === false
            ) {
                $description .= ' — ' . $omschrijving;
            }

            $rows[] = [
                'date' => $date,
                'description' => $description,
                'amount' => $amount,
                'bank_reference' => $reference !== '' ? $reference : null,
            ];
        }

        if (empty($rows)) {
            throw new BankImportParseException('Geen mutaties gevonden in dit bestand — klopt het gekozen bank/formaat (Knab CSV)?');
        }

        return $rows;
    }
}
