<?php

namespace App\Models;

use App\Support\Database;

/**
 * Koppeling van een omschrijving (bijv. "Netflix") aan een merk-icoon uit
 * BrandIcons — zodat elke vaste last/inkomst met die (exacte, hoofdletter-
 * ongevoelige) omschrijving automatisch het bijbehorende icoon toont.
 */
final class IconMapping
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM icon_mappings ORDER BY description')->fetchAll();
    }

    /**
     * Omschrijving (lowercase) => icoon-slug, voor snel opzoeken bij het
     * tonen van een lijst met lasten/inkomsten.
     */
    public static function lookup(): array
    {
        $map = [];
        foreach (self::all() as $row) {
            $map[mb_strtolower($row['description'])] = $row['icon_slug'];
        }

        return $map;
    }

    public static function upsert(string $description, string $iconSlug): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT OR REPLACE INTO icon_mappings (description, icon_slug) VALUES (:description, :icon_slug)'
        );
        $stmt->execute(['description' => $description, 'icon_slug' => $iconSlug]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM icon_mappings WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
