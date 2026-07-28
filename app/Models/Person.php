<?php

declare(strict_types=1);

namespace App\Models;

final class Person
{
    public function all(?int $parkId = null, ?string $type = null): array
    {
        $sql = <<<'SQL'
            SELECT p.*, (
                SELECT GROUP_CONCAT(pa.name, ', ')
                FROM person_parks pp
                JOIN parks pa ON pa.id = pp.park_id
                WHERE pp.person_id = p.id
            ) AS park_names
            FROM people p
            WHERE 1=1
        SQL;
        $params = [];
        if ($parkId !== null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM person_parks pp2 WHERE pp2.person_id = p.id AND pp2.park_id = ?)';
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

    /** People associated with a specific park (used for park-scoped lists and item person-pickers). */
    public function forPark(int $parkId, ?string $type = null): array
    {
        $sql = 'SELECT p.* FROM people p JOIN person_parks pp ON pp.person_id = p.id AND pp.park_id = ? WHERE 1=1';
        $params = [$parkId];
        if ($type !== null) {
            $sql .= ' AND p.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY p.is_active DESC, p.name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function parksForPerson(int $personId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT pa.* FROM parks pa
            JOIN person_parks pp ON pp.park_id = pa.id
            WHERE pp.person_id = ?
            ORDER BY pa.sort_order ASC, pa.name ASC
        SQL);
        $stmt->execute([$personId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM people WHERE id = ?');
        $stmt->execute([$id]);
        $person = $stmt->fetch();
        return $person ?: null;
    }

    /** @param int[] $parkIds */
    public function create(string $type, string $name, string $role, string $email, string $phone, string $notes, array $parkIds = [], ?string $applicationStatus = null): int
    {
        $stmt = db()->prepare('INSERT INTO people (type, name, role, email, phone, notes, application_status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$type, $name, $role, $email, $phone, $notes, $applicationStatus]);
        $id = (int) db()->lastInsertId();
        $this->setParks($id, $parkIds);
        return $id;
    }

    public function update(int $id, string $type, string $name, string $role, string $email, string $phone, string $notes, bool $isActive, ?string $applicationStatus): void
    {
        $stmt = db()->prepare('UPDATE people SET type = ?, name = ?, role = ?, email = ?, phone = ?, notes = ?, is_active = ?, application_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$type, $name, $role, $email, $phone, $notes, $isActive ? 1 : 0, $applicationStatus, $id]);
    }

    /** Replace the full set of parks this person is associated with. @param int[] $parkIds */
    public function setParks(int $personId, array $parkIds): void
    {
        db()->prepare('DELETE FROM person_parks WHERE person_id = ?')->execute([$personId]);
        $stmt = db()->prepare('INSERT OR IGNORE INTO person_parks (person_id, park_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $parkIds)) as $parkId) {
            $stmt->execute([$personId, $parkId]);
        }
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM people WHERE id = ?')->execute([$id]);
    }
}
