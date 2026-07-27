<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function exists(): bool
    {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? COLLATE NOCASE');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $email, string $name, string $password): int
    {
        $stmt = db()->prepare('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) db()->lastInsertId();
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, (string) $user['password_hash']);
    }

    public function setPassword(int $id, string $password): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
}
