<?php

declare(strict_types=1);

namespace App\Services;

final class PushSubscriptionService
{
    public function save(int $userId, string $token, string $userAgent = ''): void
    {
        $stmt = db()->prepare(<<<'SQL'
            INSERT INTO push_subscriptions (user_id, token, user_agent, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(token) DO UPDATE SET
                user_id = excluded.user_id,
                user_agent = excluded.user_agent,
                updated_at = CURRENT_TIMESTAMP
        SQL);
        $stmt->execute([$userId, $token, mb_substr(trim($userAgent), 0, 500)]);
    }

    public function delete(int $userId, string $token): void
    {
        $stmt = db()->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND token = ?');
        $stmt->execute([$userId, $token]);
    }

    public function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT id, token, user_agent, created_at, updated_at FROM push_subscriptions WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @param list<int> $userIds @return list<string> */
    public function tokensForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = db()->prepare('SELECT token FROM push_subscriptions WHERE user_id IN (' . $placeholders . ') ORDER BY id ASC');
        $stmt->execute($userIds);
        return array_values(array_column($stmt->fetchAll(), 'token'));
    }

    public function findForUser(int $userId, int $subscriptionId): ?array
    {
        $stmt = db()->prepare('SELECT id, token FROM push_subscriptions WHERE id = ? AND user_id = ?');
        $stmt->execute([$subscriptionId, $userId]);
        return $stmt->fetch() ?: null;
    }
}
