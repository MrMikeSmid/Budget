<?php

namespace App\Support\BankImport;

use DOMDocument;
use DOMXPath;

/**
 * CAMT.053 (ISO 20022 XML), zoals ABN AMRO die aanbiedt via "Bij- en
 * afschrijvingen downloaden" → CAMT.053. Gestructureerde XML met een
 * schema, betrouwbaarder te parsen dan het regelgebaseerde MT940.
 */
final class AbnCamt053Parser
{
    public static function parse(string $contents): array
    {
        $document = new DOMDocument();

        // Bewust GEEN LIBXML_NOENT/LIBXML_DTDLOAD: PHP's standaardgedrag
        // laadt externe entities/DTD's niet, dat moet zo blijven om XXE via
        // een kwaadaardig geüpload bestand te voorkomen.
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new BankImportParseException('Dit bestand is geen geldige XML — klopt het gekozen bank/formaat (ABN AMRO CAMT.053)?');
        }

        // local-name() i.p.v. een vast namespace-URI: ABN AMRO's camt.053
        // kan per exportmoment een andere schemaversie gebruiken
        // (bijv. ...camt.053.001.02 vs .001.06) — dit maakt de parser
        // ongevoelig voor dat versienummer.
        $xpath = new DOMXPath($document);
        $entries = $xpath->query('//*[local-name()="Ntry"]');

        if ($entries === false || $entries->length === 0) {
            throw new BankImportParseException('Geen mutaties (Ntry-elementen) gevonden in dit bestand — klopt het gekozen bank/formaat (ABN AMRO CAMT.053)?');
        }

        $rows = [];
        foreach ($entries as $entry) {
            $row = self::parseEntry($xpath, $entry);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            throw new BankImportParseException('Geen bruikbare mutaties gevonden in dit CAMT.053-bestand.');
        }

        return $rows;
    }

    private static function parseEntry(DOMXPath $xpath, \DOMElement $entry): ?array
    {
        $amountText = self::firstText($xpath, './/*[local-name()="Amt"]', $entry);
        $direction = self::firstText($xpath, './/*[local-name()="CdtDbtInd"]', $entry);
        $dateText = self::firstText($xpath, './/*[local-name()="BookgDt"]/*[local-name()="Dt"]', $entry)
            ?? self::firstText($xpath, './/*[local-name()="BookgDt"]/*[local-name()="DtTm"]', $entry);

        if ($amountText === null || $direction === null || $dateText === null) {
            return null;
        }

        $date = substr($dateText, 0, 10); // 'DtTm' bevat ook een tijdsdeel
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $amount = abs((float) $amountText);
        $amount = mb_strtoupper($direction) === 'DBIT' ? -$amount : $amount;

        $description = self::firstText($xpath, './/*[local-name()="RmtInf"]/*[local-name()="Ustrd"]', $entry)
            ?? self::firstText($xpath, './/*[local-name()="AddtlNtryInf"]', $entry)
            ?? self::firstText($xpath, './/*[local-name()="Nm"]', $entry)
            ?? 'Onbekende mutatie';

        $reference = self::firstText($xpath, './/*[local-name()="AcctSvcrRef"]', $entry)
            ?? self::firstText($xpath, './/*[local-name()="EndToEndId"]', $entry);
        if ($reference !== null && mb_strtoupper($reference) === 'NOTPROVIDED') {
            $reference = null;
        }

        return [
            'date' => $date,
            'description' => trim($description),
            'amount' => $amount,
            'bank_reference' => $reference,
        ];
    }

    private static function firstText(DOMXPath $xpath, string $query, \DOMElement $context): ?string
    {
        $nodes = $xpath->query($query, $context);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = trim($nodes->item(0)->textContent);

        return $text !== '' ? $text : null;
    }
}
