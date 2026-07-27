<?php

declare(strict_types=1);

namespace App\Models;

final class Person
{
    public function all(?int $parkId = null, ?string $type = null): array
    {
        $sql = 'SELECT p.*, pa.name AS park_name FROM people p JOIN parks pa ON pa.id = p.park_id WHERE 1=1';
        $params = [];
        if ($parkId !== null) {
            $sql .= ' AND p.park_id = ?';
            $params[] = $parkId;
        }
        if ($type !== null) {
            $sql .= ' AND p.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY p.is_active DESC, p.name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function forPark(int $parkId, ?string $type = null): array
    {
        if ($type !== null) {
            $stmt = db()->prepare('SELECT * FROM people WHERE park_id = ? AND type = ? ORDER BY is_active DESC, name ASC');
            $stmt->execute([$parkId, $type]);
            return $stmt->fetchAll();
        }
        $stmt = db()->prepare('SELECT * FROM people WHERE park_id = ? ORDER BY is_active DESC, name ASC');
        $stmt->execute([$parkId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM people WHERE id = ?');
        $stmt->execute([$id]);
        $person = $stmt->fetch();
        return $person ?: null;
    }

    public function create(int $parkId, string $type, string $name, string $role, string $email, string $phone, string $notes): int
    {
        $stmt = db()->prepare('INSERT INTO people (park_id, type, name, role, email, phone, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$parkId, $type, $name, $role, $email, $phone, $notes]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $name, string $role, string $email, string $phone, string $notes, bool $isActive): void
    {
        $stmt = db()->prepare('UPDATE people SET name = ?, role = ?, email = ?, phone = ?, notes = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$name, $role, $email, $phone, $notes, $isActive ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM people WHERE id = ?')->execute([$id]);
    }
}
