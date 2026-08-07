<?php

namespace App\Support;

use App\Models\IconMapping;
use PDO;
use Throwable;

/**
 * Eenmalige, automatische omzetting van de oude, per-huishouden
 * icoonkoppelingen (van vóór iconen app-breed werden) naar de centrale
 * database — zie IconMapping. storage/ overleeft elke deploy en er is geen
 * mogelijkheid om handmatig op de server een script te draaien, dus dit
 * moet net als LegacyImporter vanuit een gewone request kunnen gebeuren.
 *
 * Kopieert bewust (i.p.v. verplaatst): de oude per-huishouden tabel en
 * bestanden blijven gewoon staan, onbereikt door de rest van de app. Geen
 * enkele destructieve actie op huishouden-data, dus geen risico — in het
 * ergste geval draait dit nog eens en doet INSERT OR IGNORE niets bij een
 * omschrijving die al bestaat.
 */
final class IconMappingGlobalizer
{
    public static function runIfNeeded(): void
    {
        $storageDir = Config::get()['storage_dir'];
        $markerPath = $storageDir . '/.icon-globalize.done';

        if (is_file($markerPath)) {
            return;
        }

        $lock = fopen($storageDir . '/.icon-globalize.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return;
        }

        try {
            if (is_file($markerPath)) {
                return;
            }

            self::migrate($storageDir);

            file_put_contents($markerPath, date('c'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function migrate(string $storageDir): void
    {
        $householdsDir = $storageDir . '/households';
        if (!is_dir($householdsDir)) {
            return;
        }

        $globalIconsDir = IconMapping::iconsDir();
        if (!is_dir($globalIconsDir) && !mkdir($globalIconsDir, 0775, true) && !is_dir($globalIconsDir)) {
            return;
        }

        $app = AppDatabase::connection();

        foreach (scandir($householdsDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dbPath = $householdsDir . '/' . $entry . '/database.sqlite';
            if (is_file($dbPath)) {
                self::migrateHousehold($app, $dbPath, $householdsDir . '/' . $entry . '/icons', $globalIconsDir);
            }
        }
    }

    private static function migrateHousehold(PDO $app, string $dbPath, string $localIconsDir, string $globalIconsDir): void
    {
        try {
            $household = new PDO('sqlite:' . $dbPath);
            $household->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $hasTable = $household->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='icon_mappings'"
            )->fetch();
            if (!$hasTable) {
                return;
            }

            $rows = $household->query('SELECT description, icon_path FROM icon_mappings')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Eén kapotte/onbereikbare huishouden-database mag de rest van
            // de import niet blokkeren.
            return;
        }

        $insert = $app->prepare(
            'INSERT OR IGNORE INTO icon_mappings (description, icon_path) VALUES (:description, :icon_path)'
        );

        foreach ($rows as $row) {
            $sourceFile = $localIconsDir . '/' . basename((string) $row['icon_path']);
            if (!is_file($sourceFile)) {
                continue;
            }

            $extension = pathinfo($sourceFile, PATHINFO_EXTENSION);
            $newFilename = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');

            if (!@copy($sourceFile, $globalIconsDir . '/' . $newFilename)) {
                continue;
            }

            $insert->execute(['description' => $row['description'], 'icon_path' => $newFilename]);
        }
    }
}
