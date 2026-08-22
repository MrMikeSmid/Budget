<?php

namespace App\Support\BankImport;

/**
 * Kiest de juiste bankparser op basis van de door de gebruiker gekozen bank
 * (niet automatisch gedetecteerd uit de inhoud — de gebruiker weet zelf welke
 * bank het is, dat is betrouwbaarder dan gokken op bestandsinhoud).
 */
final class BankImportParser
{
    public const BANKS = [
        'ing' => 'ING (CSV)',
        'knab' => 'Knab (CSV)',
        'abn_camt053' => 'ABN AMRO (CAMT.053)',
        'abn_mt940' => 'ABN AMRO (MT940)',
    ];

    /**
     * @return array<int, array{date: string, description: string, amount: float, bank_reference: ?string}>
     */
    public static function parse(string $bank, string $contents): array
    {
        return match ($bank) {
            'ing' => IngCsvParser::parse($contents),
            'knab' => KnabCsvParser::parse($contents),
            'abn_camt053' => AbnCamt053Parser::parse($contents),
            'abn_mt940' => AbnMt940Parser::parse($contents),
            default => throw new BankImportParseException('Onbekende bank/formaat gekozen.'),
        };
    }
}
