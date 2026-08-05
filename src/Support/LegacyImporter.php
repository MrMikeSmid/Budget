<?php

namespace App\Support;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Eenmalige, automatische omzetting van de oude, single-tenant database (van
 * vóór de huishouden-opsplitsing) naar een eigen huishouden in de nieuwe,
 * centrale database. storage/ overleeft elke deploy en er is geen
 * mogelijkheid om handmatig op de server een script te draaien, dus dit
 * moet vanuit een gewone request kunnen gebeuren — veilig, ook als meerdere
 * requests dit tegelijk proberen, en zonder ooit een moment te hebben
 * waarop de bestaande data nergens meer aan hangt.
 *
 * Bewust NIET gegated op "heeft households al rijen" — zodra publieke
 * registratie live is groeit die tabel door gewone nieuwe accounts, wat de
 * gate zou kunnen laten dichtklappen voordat de import ooit gedraaid heeft.
 * De enige betrouwbare trigger is: bestaat het oude databasebestand nog.
 *
 * Het huishouden en de gebruikers die hieruit voortkomen krijgen hun id's
 * via het normale autoincrement (geen vast gereserveerd nummer): op een
 * host waar requests niet betrouwbaar op elkaar wachten (bijv. omdat
 * flock() niet overal hetzelfde gedrag heeft) kan een gelijktijdige
 * registratie al een laag id hebben ingenomen vóór de import zijn eigen,
 * vaste id kon claimen. Een aparte legacy_import-rij markeert ondubbelzinnig
 * welk (autoincrement-)huishouden-id de import heeft opgeleverd.
 */
final class LegacyImporter
{
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
        $app = AppDatabase::connection();
        $householdId = self::importMetadataIfNeeded($app, $oldDbPath);

        $newHouseholdDir = $householdsDir . '/' . $householdId;
        $newDbPath = $newHouseholdDir . '/database.sqlite';

        // Metadata (users/household/lidmaatschap) EERST, bestand verplaatsen
        // ALS LAATSTE STAP. Faalt de metadata-stap, dan staat het oude bestand
        // nog gewoon op zijn plek en probeert de volgende request het gewoon
        // opnieuw — geen enkel moment waarop de echte data onbereikbaar is.
        if (!is_file($newDbPath)) {
            if (!is_dir($newHouseholdDir) && !mkdir($newHouseholdDir, 0775, true) && !is_dir($newHouseholdDir)) {
                throw new RuntimeException("Kon map niet aanmaken: {$newHouseholdDir}");
            }

            if (!rename($oldDbPath, $newDbPath)) {
                throw new RuntimeException('Kon de bestaande database niet verplaatsen naar het huishouden-pad.');
            }
        }
    }

    private static function importMetadataIfNeeded(PDO $app, string $oldDbPath): int
    {
        $existing = $app->query('SELECT household_id FROM legacy_import WHERE id = 1')->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }

        $legacy = new PDO('sqlite:' . $oldDbPath);
        $legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $legacy->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $legacyUsers = $legacy->query('SELECT id, name, email, password_hash, created_at FROM users')->fetchAll();

        $app->beginTransaction();
        try {
            $app->exec('INSERT INTO households (name, db_path) VALUES (' . $app->quote(self::LEGACY_HOUSEHOLD_NAME) . ", '')");
            $householdId = (int) $app->lastInsertId();
            $app->exec(
                'UPDATE households SET db_path = ' . $app->quote('households/' . $householdId . '/database.sqlite')
                . " WHERE id = {$householdId}"
            );

            $findByEmail = $app->prepare('SELECT id FROM users WHERE email = :email');
            $insertUser = $app->prepare(
                'INSERT INTO users (name, email, password_hash, email_verified_at, created_at)
                 VALUES (:name, :email, :password_hash, :verified_at, :created_at)'
            );
            $insertMember = $app->prepare(
                "INSERT OR IGNORE INTO household_members (household_id, user_id, joined_at)
                 VALUES ({$householdId}, :user_id, :joined_at)"
            );

            foreach ($legacyUsers as $user) {
                // Bestaat er al een centraal account met dit e-mailadres
                // (bijv. van een eerdere, gedeeltelijk gelukte poging), koppel
                // dat gewoon aan dit huishouden i.p.v. te falen — met een
                // vast e-mailadres van een van de twee echte gebruikers is
                // een toevallige botsing met iemand anders uitgesloten.
                $findByEmail->execute(['email' => $user['email']]);
                $existingUserId = $findByEmail->fetchColumn();

                if ($existingUserId !== false) {
                    $newUserId = (int) $existingUserId;
                } else {
                    $insertUser->execute([
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'password_hash' => $user['password_hash'],
                        // Bestaande accounts zijn al vertrouwd (al maandenlang in
                        // gebruik) — geen verificatie-eis met terugwerkende kracht.
                        'verified_at' => $user['created_at'],
                        'created_at' => $user['created_at'],
                    ]);
                    $newUserId = (int) $app->lastInsertId();
                }

                $insertMember->execute(['user_id' => $newUserId, 'joined_at' => $user['created_at']]);
            }

            $app->exec("INSERT INTO legacy_import (id, household_id) VALUES (1, {$householdId})");

            $app->commit();
        } catch (Throwable $e) {
            $app->rollBack();
            throw $e;
        }

        return $householdId;
    }
}
