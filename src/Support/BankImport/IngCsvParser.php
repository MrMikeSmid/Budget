<?php

namespace App\Support\BankImport;

/**
 * ING-CSV ("Af- en bijschrijvingen downloaden" → CSV, puntkommagescheiden):
 * Datum;Naam / Omschrijving;Rekening;Tegenrekening;Code;Af Bij;Bedrag (EUR);
 * Mutatiesoort;Mededelingen;Saldo na mutatie;Tag
 *
 * Geen stabiele, unieke referentie per mutatie in dit formaat — bank_reference
 * is daarom altijd null (dedupe valt terug op datum+bedrag+omschrijving).
 */
final class IngCsvParser
{
    public static function parse(string $contents): array
    {
        $lines = BankImportSupport::splitLines($contents);
        if (empty($lines)) {
            throw new BankImportParseException('Dit ING-bestand bevat geen regels.');
        }

        $rows = [];
        foreach ($lines as $index => $line) {
            $fields = str_getcsv($line, ';');

            // Header ("Datum;Naam / Omschrijving;...") overslaan.
            if ($index === 0 && isset($fields[0]) && mb_strtolower(trim($fields[0])) === 'datum') {
                continue;
            }

            if (count($fields) < 7) {
                continue; // lege/onvolledige regel
            }

            $rawDate = trim($fields[0]);
            $description = trim($fields[1]);
            $direction = mb_strtolower(trim($fields[5]));
            $rawAmount = trim($fields[6]);
            $mededelingen = trim($fields[8] ?? '');

            if ($rawDate === '' || $rawAmount === '') {
                continue;
            }

            $date = BankImportSupport::parseDate($rawDate, 'Ymd');
            if ($date === null) {
                continue;
            }

            $amount = BankImportSupport::parseAmount($rawAmount);
            if (str_starts_with($direction, 'af')) {
                $amount = -abs($amount);
            } elseif (str_starts_with($direction, 'bij')) {
                $amount = abs($amount);
            }

            if ($mededelingen !== '' && $mededelingen !== $description) {
                $description = $description !== '' ? $description . ' — ' . $mededelingen : $mededelingen;
            }

            $rows[] = [
                'date' => $date,
                'description' => $description !== '' ? $description : 'Onbekende mutatie',
                'amount' => $amount,
                'bank_reference' => null,
            ];
        }

        if (empty($rows)) {
            throw new BankImportParseException('Geen mutaties gevonden in dit bestand — klopt het gekozen bank/formaat (ING CSV)?');
        }

        return $rows;
    }
}
