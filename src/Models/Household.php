<?php

namespace App\Models;

use App\Support\AppDatabase;

final class Household
{
    public static function find(int $id): ?array
    {
        $stmt = AppDatabase::connection()->prepare('SELECT * FROM households WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $household = $stmt->fetch();

        return $household ?: null;
    }

    /**
     * Maakt een nieuw huishouden mét eigen SQLite-bestand aan en voegt de
     * aanmaker meteen toe als (enige) lid. $householdsDir is het absolute pad
     * naar storage/households — de nieuwe database wordt daaronder aangemaakt
     * en de eerste keer geopend (via Database), zodat het volledige schema
     * (database/migrations/*.sql) meteen klaarstaat.
     */
    public static function createWithOwner(string $name, int $ownerUserId, string $householdsDir): int
    {
        $pdo = AppDatabase::connection();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO households (name, db_path) VALUES (:name, :db_path)');
            $stmt->execute(['name' => $name, 'db_path' => '']);
            $id = (int) $pdo->lastInsertId();

            $relativePath = "households/{$id}/database.sqlite";
            $update = $pdo->prepare('UPDATE households SET db_path = :db_path WHERE id = :id');
            $update->execute(['db_path' => $relativePath, 'id' => $id]);

            HouseholdMember::add($id, $ownerUserId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Bestand + schema meteen aanmaken zodat een net aangemaakt huishouden
        // nooit een "nog niet bestaand db-bestand" state kan hebben.
        \App\Support\Database::useHouseholdDb($householdsDir . '/' . $id . '/database.sqlite');
        \App\Support\Database::connection();

        return $id;
    }

    public static function rename(int $id, string $name): void
    {
        $stmt = AppDatabase::connection()->prepare('UPDATE households SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }
}
