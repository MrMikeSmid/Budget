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
