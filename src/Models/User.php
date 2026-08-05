<?php

namespace App\Models;

use App\Support\AppDatabase;

final class User
{
    public static function find(int $id): ?array
    {
        $stmt = AppDatabase::connection()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = AppDatabase::connection()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Nieuwe accounts zijn altijd onbevestigd — moeten eerst de
     * verificatielink volgen voor ze kunnen inloggen (zie Auth::attempt()).
     */
    public static function create(string $name, string $email, string $password): int
    {
        $stmt = AppDatabase::connection()->prepare(
            'INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (:name, :email, :password_hash, NULL)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return (int) AppDatabase::connection()->lastInsertId();
    }

    public static function markVerified(int $id): void
    {
        $stmt = AppDatabase::connection()->prepare(
            "UPDATE users SET email_verified_at = datetime('now') WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public static function isVerified(array $user): bool
    {
        return !empty($user['email_verified_at']);
    }
}
