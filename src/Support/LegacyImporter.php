<?php

namespace App\Support;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Eenmalige, automatische omzetting van de oude, single-tenant database (van
 * vóór de huishouden-opsplitsing) naar huishouden #1 in de nieuwe, centrale
 * database. storage/ overleeft elke deploy en er is geen mogelijkheid om
 * handmatig op de server een script te draaien, dus dit moet vanuit een
 * gewone request kunnen gebeuren — veilig, ook als twee requests dit
 * tegelijk proberen, en zonder ooit een moment te hebben waarop de bestaande
 * data nergens meer aan hangt.
 *
 * Bewust NIET gegated op "heeft households al rijen" — zodra publieke
 * registratie live is groeit die tabel door gewone nieuwe accounts, wat de
 * gate zou kunnen laten dichtklappen voordat de import ooit gedraaid heeft.
 * De enige betrouwbare trigger is: bestaat het oude databasebestand nog.
 */
final class LegacyImporter
{
    private const LEGACY_HOUSEHOLD_ID = 1;
    private const LEGACY_HOUSEHOLD_NAME = 'Huishouden';

    public static function runIfNeeded(): void
    {
        $config = Config::get();
        $oldDbPath = $config['db_path'];

        if (!is_file($oldDbPath)) {
            return;
        }

        $lockPath = dirname($oldDbPath) . '/.legacy-import.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            // Kon de lock niet krijgen; een andere request is er al mee bezig
            // of we konden het lockbestand niet aanmaken. Geen probleem: de
            // volgende request probeert het gewoon opnieuw.
            return;
        }

        try {
            // Dubbel checken ná het verkrijgen van de lock: een andere
            // request kan de import al hebben afgerond terwijl wij wachtten.
            if (!is_file($oldDbPath)) {
                return;
            }

            self::import($oldDbPath, $config['households_dir']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function import(string $oldDbPath, string $householdsDir): void
    {
        $newHouseholdDir = $householdsDir . '/' . self::LEGACY_HOUSEHOLD_ID;
        $newDbPath = $newHouseholdDir . '/database.sqlite';

        $app = AppDatabase::connection();
        $metadataDone = (bool) $app->query(
            'SELECT 1 FROM households WHERE id = ' . self::LEGACY_HOUSEHOLD_ID
        )->fetchColumn();

        // Metadata (users/household/lidmaatschap) EERST, bestand verplaatsen
        // ALS LAATSTE STAP. Faalt de metadata-stap, dan staat het oude bestand
        // nog gewoon op zijn plek en probeert de volgende request het gewoon
        // opnieuw — geen enkel moment waarop de echte data onbereikbaar is.
        if (!$metadataDone) {
            self::importMetadata($app, $oldDbPath);
        }

        if (!is_file($newDbPath)) {
            if (!is_dir($newHouseholdDir)) {
                mkdir($newHouseholdDir, 0775, true);
            }

            if (!rename($oldDbPath, $newDbPath)) {
                throw new RuntimeException('Kon de bestaande database niet verplaatsen naar het huishouden-pad.');
            }
        }
    }

    private static function importMetadata(PDO $app, string $oldDbPath): void
    {
        $legacy = new PDO('sqlite:' . $oldDbPath);
        $legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $legacy->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $legacyUsers = $legacy->query('SELECT id, name, email, password_hash, created_at FROM users')->fetchAll();

        $app->beginTransaction();
        try {
            $app->exec(sprintf(
                "INSERT OR IGNORE INTO households (id, name, db_path) VALUES (%d, %s, %s)",
                self::LEGACY_HOUSEHOLD_ID,
                $app->quote(self::LEGACY_HOUSEHOLD_NAME),
                $app->quote('households/' . self::LEGACY_HOUSEHOLD_ID . '/database.sqlite')
            ));

            $insertUser = $app->prepare(
                'INSERT INTO users (id, name, email, password_hash, email_verified_at, created_at)
                 VALUES (:id, :name, :email, :password_hash, :verified_at, :created_at)'
            );
            $insertMember = $app->prepare(
                'INSERT OR IGNORE INTO household_members (household_id, user_id, joined_at)
                 VALUES (' . self::LEGACY_HOUSEHOLD_ID . ', :user_id, :joined_at)'
            );

            foreach ($legacyUsers as $user) {
                try {
                    $insertUser->execute([
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'password_hash' => $user['password_hash'],
                        // Bestaande accounts zijn al vertrouwd (al maandenlang in
                        // gebruik) — geen verificatie-eis met terugwerkende kracht.
                        'verified_at' => $user['created_at'],
                        'created_at' => $user['created_at'],
                    ]);
                } catch (Throwable $e) {
                    // Kan alleen gebeuren als er al een centraal account met
                    // hetzelfde e-mailadres of id bestaat (bijv. een verse
                    // registratie die net dat e-mailadres pakte). Met echte
                    // financiële data op het spel niet stilzwijgend negeren.
                    throw new RuntimeException(
                        "Migratie-conflict bij het overzetten van gebruiker '{$user['email']}': " . $e->getMessage(),
                        0,
                        $e
                    );
                }

                $insertMember->execute(['user_id' => $user['id'], 'joined_at' => $user['created_at']]);
            }

            $app->commit();
        } catch (Throwable $e) {
            $app->rollBack();
            throw $e;
        }
    }
}
