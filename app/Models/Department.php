<?php

declare(strict_types=1);

namespace App\Models;

final class Department
{
    public function all(): array
    {
        return db()->query('SELECT * FROM departments ORDER BY sort_order ASC, name ASC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        $department = $stmt->fetch();
        return $department ?: null;
    }

    public function create(string $name, string $description): int
    {
        $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM departments')->fetchColumn();
        $stmt = db()->prepare('INSERT INTO departments (name, description, sort_order) VALUES (?, ?, ?)');
        $stmt->execute([$name, $description, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $name, string $description): void
    {
        $stmt = db()->prepare('UPDATE departments SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$name, $description, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
    }
}
