<?php

namespace App\Support\BankImport;

/**
 * Kleine, door alle parsers gedeelde hulpfuncties (BOM/regeleinden
 * afhandelen, Nederlandse komma-bedragen, datumformaten).
 */
final class BankImportSupport
{
    /**
     * Splitst bestandsinhoud in regels, ontdoet zich van een eventuele
     * UTF-8 BOM (ING/Knab-exports hebben die vaak) en lege regels.
     *
     * @return string[]
     */
    public static function splitLines(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $contents = str_replace("\r\n", "\n", $contents);
        $lines = explode("\n", $contents);

        return array_values(array_filter(array_map('rtrim', $lines), static fn (string $l): bool => $l !== ''));
    }

    /**
     * Nederlandse komma-decimaal (en optioneel duizendtal-punten) naar float.
     * Retourneert altijd een positief bedrag — de aanroeper bepaalt het teken.
     */
    public static function parseAmount(string $raw): float
    {
        $raw = trim($raw);
        $raw = str_replace('.', '', $raw); // duizendtal-scheiding
        $raw = str_replace(',', '.', $raw);

        return abs((float) $raw);
    }

    /**
     * Parseert een datum in een gegeven PHP date()-formaat (bijv. 'Ymd' voor
     * ING, 'd-m-Y' voor Knab) naar 'Y-m-d'. Geeft null bij een ongeldige
     * datum i.p.v. een gok te wagen.
     */
    public static function parseDate(string $raw, string $format): ?string
    {
        $raw = trim($raw);
        $date = \DateTime::createFromFormat($format, $raw);
        if ($date === false) {
            return null;
        }

        $errors = \DateTime::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
