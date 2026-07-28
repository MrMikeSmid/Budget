<?php

declare(strict_types=1);

namespace App\Models;

final class Item
{
    public function all(?int $parkId = null, ?string $category = null, ?string $status = null, ?string $type = null): array
    {
        $sql = 'SELECT i.*, pa.name AS park_name, pe.name AS person_name FROM items i JOIN parks pa ON pa.id = i.park_id LEFT JOIN people pe ON pe.id = i.person_id WHERE 1=1';
        $params = [];
        if ($parkId !== null) {
            $sql .= ' AND i.park_id = ?';
            $params[] = $parkId;
        }
        if ($category !== null) {
            $sql .= ' AND i.category = ?';
            $params[] = $category;
        }
        if ($status !== null) {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        }
        if ($type !== null) {
            $sql .= ' AND i.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY (i.due_date IS NULL), i.due_date ASC, i.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function forPark(int $parkId, ?string $category = null, ?string $status = null): array
    {
        $sql = 'SELECT i.*, p.name AS person_name FROM items i LEFT JOIN people p ON p.id = i.person_id WHERE i.park_id = ?';
        $params = [$parkId];
        if ($category !== null) {
            $sql .= ' AND i.category = ?';
            $params[] = $category;
        }
        if ($status !== null) {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY (i.due_date IS NULL), i.due_date ASC, i.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function forPerson(int $personId): array
    {
        $stmt = db()->prepare('SELECT * FROM items WHERE person_id = ? ORDER BY (due_date IS NULL), due_date ASC, created_at DESC');
        $stmt->execute([$personId]);
        return $stmt->fetchAll();
    }

    /** Open items due within the given number of days (or already overdue), across all parks. */
    public function dueSoon(int $withinDays = 7): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT i.*, pa.name AS park_name, pe.name AS person_name
            FROM items i
            JOIN parks pa ON pa.id = i.park_id
            LEFT JOIN people pe ON pe.id = i.person_id
            WHERE i.status IN ('open', 'in_uitvoering')
              AND i.due_date IS NOT NULL
              AND i.due_date <= date('now', '+' || ? || ' days')
            ORDER BY i.due_date ASC
        SQL);
        $stmt->execute([$withinDays]);
        return $stmt->fetchAll();
    }

    public function openCountsByPark(): array
    {
        $rows = db()->query(<<<'SQL'
            SELECT park_id, COUNT(*) AS total
            FROM items
            WHERE status IN ('open', 'in_uitvoering')
            GROUP BY park_id
        SQL)->fetchAll();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['park_id']] = (int) $row['total'];
        }
        return $counts;
    }

    /** Open items (any type) for a park, used by the parkrapportage. */
    public function openForPark(int $parkId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT i.*, pe.name AS person_name
            FROM items i
            LEFT JOIN people pe ON pe.id = i.person_id
            WHERE i.park_id = ? AND i.status IN ('open', 'in_uitvoering')
            ORDER BY (i.due_date IS NULL), i.due_date ASC, i.created_at DESC
        SQL);
        $stmt->execute([$parkId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    public function create(int $parkId, string $category, string $type, ?int $personId, string $title, string $body, ?string $dueDate, string $guestName = ''): int
    {
        $stmt = db()->prepare('INSERT INTO items (park_id, category, type, person_id, guest_name, title, body, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$parkId, $category, $type, $personId, $guestName, $title, $body, $dueDate]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $title, string $body, ?string $dueDate, string $status, string $guestName = ''): void
    {
        $completedAt = $status === 'afgerond' ? "CURRENT_TIMESTAMP" : "NULL";
        $stmt = db()->prepare("UPDATE items SET title = ?, body = ?, due_date = ?, status = ?, guest_name = ?, completed_at = {$completedAt}, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$title, $body, $dueDate, $status, $guestName, $id]);
    }

    public function toggle(int $id): void
    {
        $item = $this->find($id);
        if (!$item) {
            return;
        }
        $newStatus = $item['status'] === 'afgerond' ? 'open' : 'afgerond';
        $completedAt = $newStatus === 'afgerond' ? "CURRENT_TIMESTAMP" : "NULL";
        $stmt = db()->prepare("UPDATE items SET status = ?, completed_at = {$completedAt}, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
    }
}
