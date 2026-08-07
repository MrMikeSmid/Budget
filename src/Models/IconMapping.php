<?php

namespace App\Models;

use App\Support\Config;
use App\Support\Database;

/**
 * Koppeling van een omschrijving (bijv. "Netflix") aan een zelf-geüploade
 * afbeelding — zodat elke vaste last/inkomst met die (exacte, hoofdletter-
 * ongevoelige) omschrijving automatisch dat icoon toont. De afbeelding zelf
 * staat op schijf onder storage/households/{id}/icons/, icon_path bevat
 * alleen de bestandsnaam.
 */
final class IconMapping
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM icon_mappings ORDER BY description')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM icon_mappings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lijst van koppelingen (omschrijving + mapping-id) voor snel opzoeken
     * bij het tonen van een lijst met lasten/inkomsten — zie match().
     * Koppelingen waarvan het bestand niet (meer) bestaat (bijv. na het
     * weghalen van de vorige meegeleverde iconenset) worden overgeslagen —
     * de aanroeper toont dan gewoon de placeholder-letter i.p.v. een kapot
     * plaatje.
     *
     * Langste omschrijving eerst: bij een titel die meerdere gekoppelde
     * woorden bevat (bijv. "belasting" én "belastingdienst" zijn allebei
     * gekoppeld) wint de langere, specifiekere match in match().
     *
     * @return array<int, array{description: string, id: int}>
     */
    public static function lookup(): array
    {
        $mappings = [];
        foreach (self::all() as $row) {
            if (self::absolutePath($row['icon_path']) === null) {
                continue;
            }
            $mappings[] = ['description' => $row['description'], 'id' => (int) $row['id']];
        }

        usort($mappings, static fn (array $a, array $b) => mb_strlen($b['description']) <=> mb_strlen($a['description']));

        return $mappings;
    }

    /**
     * Zoekt de eerste (langste) gekoppelde omschrijving die ergens in $text
     * voorkomt — hoofdletterongevoelig, jokerteken-achtig: "belasting" matcht
     * dus ook "Voorlopige aanslag belasting 2026" of "Belastingdienst".
     *
     * @param array<int, array{description: string, id: int}> $mappings
     */
    public static function match(string $text, array $mappings): ?int
    {
        $haystack = mb_strtolower($text);
        foreach ($mappings as $mapping) {
            if ($mapping['description'] !== '' && str_contains($haystack, mb_strtolower($mapping['description']))) {
                return $mapping['id'];
            }
        }

        return null;
    }

    public static function upsert(string $description, string $iconPath): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT OR REPLACE INTO icon_mappings (description, icon_path) VALUES (:description, :icon_path)'
        );
        $stmt->execute(['description' => $description, 'icon_path' => $iconPath]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM icon_mappings WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function iconsDir(): string
    {
        $householdId = (int) ($_SESSION['household_id'] ?? 0);
        $storageDir = Config::get()['storage_dir'];

        return $storageDir . '/households/' . $householdId . '/icons';
    }

    /**
     * Absoluut pad naar een geüploade afbeelding, of null als de bestandsnaam
     * ongeldig is of het bestand niet (meer) bestaat. Gebruikt basename() zodat
     * een pad als "../../iets" nooit buiten de iconenmap van het huishouden
     * kan uitkomen.
     */
    public static function absolutePath(?string $filename): ?string
    {
        $filename = basename((string) $filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $path = self::iconsDir() . '/' . $filename;

        return is_file($path) ? $path : null;
    }
}
