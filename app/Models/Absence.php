<?php

declare(strict_types=1);

namespace App\Models;

final class Absence
{
    public function forPerson(int $personId): array
    {
        $stmt = db()->prepare('SELECT * FROM absences WHERE person_id = ? ORDER BY start_date DESC');
        $stmt->execute([$personId]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        return db()->query(<<<'SQL'
            SELECT a.*, p.name AS person_name
            FROM absences a
            JOIN people p ON p.id = a.person_id
            ORDER BY a.start_date DESC
        SQL)->fetchAll();
    }

    /** Currently active absences among staff linked to a park, used by the parkrapportage. */
    public function activeForPark(int $parkId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT a.*, p.name AS person_name
            FROM absences a
            JOIN people p ON p.id = a.person_id
            JOIN person_parks pp ON pp.person_id = p.id AND pp.park_id = ?
            WHERE a.status != 'hersteld'
            ORDER BY a.start_date ASC
        SQL);
        $stmt->execute([$parkId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM absences WHERE id = ?');
        $stmt->execute([$id]);
        $absence = $stmt->fetch();
        return $absence ?: null;
    }

    public function create(int $personId, string $startDate, ?string $endDate, string $reason, string $status, string $notes): int
    {
        $stmt = db()->prepare('INSERT INTO absences (person_id, start_date, end_date, reason, status, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$personId, $startDate, $endDate, $reason, $status, $notes]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $startDate, ?string $endDate, string $reason, string $status, string $notes): void
    {
        $stmt = db()->prepare('UPDATE absences SET start_date = ?, end_date = ?, reason = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$startDate, $endDate, $reason, $status, $notes, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM absences WHERE id = ?')->execute([$id]);
    }
}
