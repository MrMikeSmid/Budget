<?php

namespace App\Models;

use App\Support\Database;

/**
 * Gedeelde categorielijst voor inkomsten, vaste lasten en leningen — geen
 * scheiding per type, zodat één categorie ("Boodschappen", "Auto", ...) de
 * uitgaven én inkomsten daarbinnen bij elkaar kan optellen.
 */
final class Category
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(string $name): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE categories SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
