<?php

declare(strict_types=1);

namespace App\Models;

final class PlaybookStep
{
    public function forPlaybook(int $playbookId): array
    {
        $stmt = db()->prepare('SELECT * FROM playbook_steps WHERE playbook_id = ? ORDER BY date ASC, created_at ASC');
        $stmt->execute([$playbookId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM playbook_steps WHERE id = ?');
        $stmt->execute([$id]);
        $step = $stmt->fetch();
        return $step ?: null;
    }

    public function create(int $playbookId, string $title, string $body, string $scheduleType, string $date): int
    {
        $stmt = db()->prepare('INSERT INTO playbook_steps (playbook_id, title, body, schedule_type, date) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$playbookId, $title, $body, $scheduleType, $date]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $title, string $body, string $scheduleType, string $date): void
    {
        $stmt = db()->prepare('UPDATE playbook_steps SET title = ?, body = ?, schedule_type = ?, date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$title, $body, $scheduleType, $date, $id]);
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
