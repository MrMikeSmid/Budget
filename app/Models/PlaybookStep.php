<?php

declare(strict_types=1);

namespace App\Models;

final class PlaybookStep
{
    public function forPlaybook(int $playbookId, ?int $parkId = null): array
    {
        $sql = <<<'SQL'
            SELECT s.*, pa.name AS park_name
            FROM playbook_steps s
            LEFT JOIN parks pa ON pa.id = s.park_id
            WHERE s.playbook_id = ?
        SQL;
        $params = [$playbookId];
        if ($parkId !== null) {
            // Steps without a park apply everywhere, so they stay visible in a park's filtered view too.
            $sql .= ' AND (s.park_id = ? OR s.park_id IS NULL)';
            $params[] = $parkId;
        }
        $sql .= ' ORDER BY s.date ASC, s.created_at ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM playbook_steps WHERE id = ?');
        $stmt->execute([$id]);
        $step = $stmt->fetch();
        return $step ?: null;
    }

    public function create(int $playbookId, ?int $parkId, string $title, string $body, string $scheduleType, string $date): int
    {
        $stmt = db()->prepare('INSERT INTO playbook_steps (playbook_id, park_id, title, body, schedule_type, date) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$playbookId, $parkId, $title, $body, $scheduleType, $date]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, ?int $parkId, string $title, string $body, string $scheduleType, string $date): void
    {
        $stmt = db()->prepare('UPDATE playbook_steps SET park_id = ?, title = ?, body = ?, schedule_type = ?, date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$parkId, $title, $body, $scheduleType, $date, $id]);
    }

    public function toggle(int $id): void
    {
        $step = $this->find($id);
        if (!$step) {
            return;
        }
        $newStatus = $step['status'] === 'afgerond' ? 'open' : 'afgerond';
        db()->prepare('UPDATE playbook_steps SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$newStatus, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM playbook_steps WHERE id = ?')->execute([$id]);
    }
}
