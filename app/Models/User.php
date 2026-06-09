<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? COLLATE NOCASE');
        $stmt->execute([mb_strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function findOrCreate(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $existing = $this->findByEmail($email);
        if ($existing) {
            return $existing;
        }
        $local = explode('@', $email)[0];
        $name = ucwords(str_replace(['.', '_', '-'], ' ', $local));
        $isAdmin = $this->shouldCreateAsAdmin($email) ? 1 : 0;
        $stmt = db()->prepare('INSERT INTO users (email, name, is_admin) VALUES (?, ?, ?)');
        $stmt->execute([$email, $name ?: 'Nieuw lid', $isAdmin]);
        return $this->find((int) db()->lastInsertId());
    }

    public function allForAdmin(): array
    {
        $stmt = db()->query(<<<'SQL'
            SELECT
                u.id,
                u.name,
                u.email,
                CASE WHEN u.password_hash IS NOT NULL AND u.password_hash != '' THEN 1 ELSE 0 END AS has_password,
                u.is_admin,
                u.created_at,
                u.last_login_at,
                COUNT(ns.id) AS notification_subscription_count
            FROM users u
            LEFT JOIN notification_subscriptions ns ON ns.user_id = u.id
            GROUP BY u.id
            ORDER BY u.created_at DESC, u.id DESC
        SQL);

        $accounts = $stmt->fetchAll();
        $listsByUser = [];
        $memberships = db()->query(<<<'SQL'
            SELECT memberships.user_id, memberships.id, memberships.title, memberships.emoji, memberships.is_owner
            FROM (
                SELECT l.owner_id AS user_id, l.id, l.title, l.emoji, 1 AS is_owner
                FROM todo_lists l

                UNION ALL

                SELECT lm.user_id, l.id, l.title, l.emoji, 0 AS is_owner
                FROM list_members lm
                JOIN todo_lists l ON l.id = lm.list_id
                WHERE lm.accepted_at IS NOT NULL
            ) memberships
            ORDER BY memberships.title COLLATE NOCASE ASC, memberships.id ASC
        SQL)->fetchAll();

        foreach ($memberships as $membership) {
            $listsByUser[(int) $membership['user_id']][] = [
                'id' => (int) $membership['id'],
                'title' => $membership['title'],
                'emoji' => $membership['emoji'],
                'is_owner' => (int) $membership['is_owner'],
            ];
        }

        foreach ($accounts as &$account) {
            $account['lists'] = $listsByUser[(int) $account['id']] ?? [];
        }
        unset($account);

        return $accounts;
    }

    public function recordLogin(int $id): void
    {
        db()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
    }

    public function updateProfile(int $id, string $name): void
    {
        $stmt = db()->prepare('UPDATE users SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([trim($name), $id]);
    }

    public function setProfileImage(int $id, string $filename): void
    {
        $stmt = db()->prepare('UPDATE users SET profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$filename, $id]);
    }

    public function touchPresence(int $id): void
    {
        db()->prepare('UPDATE users SET last_seen_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
    }

    public function setPassword(int $id, string $password): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    private function shouldCreateAsAdmin(string $email): bool
    {
        $adminEmail = (string) config('admin_email', '');
        if ($adminEmail !== '') {
            return mb_strtolower($email) === $adminEmail;
        }

        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    }
}
