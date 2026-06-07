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
        $stmt = db()->prepare('INSERT INTO users (email, name, push_external_id, is_admin) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $name ?: 'Nieuw lid', 'samen-' . bin2hex(random_bytes(24)), $isAdmin]);
        return $this->find((int) db()->lastInsertId());
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
