<?php

declare(strict_types=1);

namespace App\Models;

final class Playbook
{
    public function all(?int $departmentId = null): array
    {
        $sql = <<<'SQL'
            SELECT pb.*, d.name AS department_name
            FROM playbooks pb
            JOIN departments d ON d.id = pb.department_id
            WHERE 1=1
        SQL;
        $params = [];
        if ($departmentId !== null) {
            $sql .= ' AND pb.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY pb.title ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT pb.*, d.name AS department_name
            FROM playbooks pb
            JOIN departments d ON d.id = pb.department_id
            WHERE pb.id = ?
        SQL);
        $stmt->execute([$id]);
        $playbook = $stmt->fetch();
        return $playbook ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT pb.*, d.name AS department_name
            FROM playbooks pb
            JOIN departments d ON d.id = pb.department_id
            WHERE pb.share_token = ?
        SQL);
        $stmt->execute([$token]);
        $playbook = $stmt->fetch();
        return $playbook ?: null;
    }

    public function create(string $title, int $departmentId, ?int $leaderPersonId, string $leaderName, string $description): int
    {
        $stmt = db()->prepare('INSERT INTO playbooks (title, department_id, leader_person_id, leader_name, description, share_token) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $departmentId, $leaderPersonId, $leaderName, $description, $this->generateToken()]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $title, int $departmentId, ?int $leaderPersonId, string $leaderName, string $description): void
    {
        $stmt = db()->prepare('UPDATE playbooks SET title = ?, department_id = ?, leader_person_id = ?, leader_name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$title, $departmentId, $leaderPersonId, $leaderName, $description, $id]);
    }

    public function regenerateToken(int $id): string
    {
        $token = $this->generateToken();
        db()->prepare('UPDATE playbooks SET share_token = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$token, $id]);
        return $token;
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM playbooks WHERE id = ?')->execute([$id]);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
