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
        $stmt = db()->prepare('INSERT INTO users (email, name) VALUES (?, ?)');
        $stmt->execute([$email, $name ?: 'Nieuw lid']);
        return $this->find((int) db()->lastInsertId());
    }

    public function updateProfile(int $id, string $name): void
    {
        $stmt = db()->prepare('UPDATE users SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([trim($name), $id]);
    }

    public function setPassword(int $id, string $password): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
}
