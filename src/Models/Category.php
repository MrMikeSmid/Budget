<?php

namespace App\Models;

use App\Support\Database;

/**
 * Gedeelde categorielijst voor inkomsten, vaste lasten en leningen — geen
 * scheiding per module, maar wel een type (inkomsten/uitgaven) dat bepaalt
 * welke kant van de categoriedetailpagina getoond wordt.
 */
final class Category
{
    public const TYPES = [
        'uitgaven' => 'Uitgaven',
        'inkomsten' => 'Inkomsten',
    ];

    public static function normalizeType(string $type): string
    {
        return array_key_exists($type, self::TYPES) ? $type : 'uitgaven';
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }

    public static function allByType(string $type): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE type = :type ORDER BY name');
        $stmt->execute(['type' => self::normalizeType($type)]);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(string $name, string $type): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO categories (name, type) VALUES (:name, :type)');
        $stmt->execute(['name' => $name, 'type' => self::normalizeType($type)]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $type): void
    {
        $stmt = Database::connection()->prepare('UPDATE categories SET name = :name, type = :type WHERE id = :id');
        $stmt->execute(['name' => $name, 'type' => self::normalizeType($type), 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
