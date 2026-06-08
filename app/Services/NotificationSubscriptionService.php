<?php

declare(strict_types=1);

namespace App\Services;

final class NotificationSubscriptionService
{
    public function save(int $userId, string $subscriptionId, string $userAgent = ''): void
    {
        $stmt = db()->prepare(<<<'SQL'
            INSERT INTO notification_subscriptions (user_id, subscription_id, user_agent, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(subscription_id) DO UPDATE SET
                user_id = excluded.user_id,
                user_agent = excluded.user_agent,
                updated_at = CURRENT_TIMESTAMP
        SQL);
        $stmt->execute([$userId, $subscriptionId, mb_substr(trim($userAgent), 0, 500)]);
    }

    public function delete(int $userId, string $subscriptionId): void
    {
        $stmt = db()->prepare('DELETE FROM notification_subscriptions WHERE user_id = ? AND subscription_id = ?');
        $stmt->execute([$userId, $subscriptionId]);
    }

    public function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT id, subscription_id, user_agent, created_at, updated_at FROM notification_subscriptions WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @param list<int> $userIds @return list<string> */
    public function idsForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = db()->prepare('SELECT subscription_id FROM notification_subscriptions WHERE user_id IN (' . $placeholders . ') ORDER BY id ASC');
        $stmt->execute($userIds);
        return array_values(array_column($stmt->fetchAll(), 'subscription_id'));
    }

    public function findForUser(int $userId, int $id): ?array
    {
        $stmt = db()->prepare('SELECT id, subscription_id FROM notification_subscriptions WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }
}
