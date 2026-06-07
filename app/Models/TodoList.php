<?php

declare(strict_types=1);

namespace App\Models;

final class TodoList
{
    private const ONLINE_THRESHOLD_SECONDS = 10;

    public function forUser(int $userId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT l.*, u.name AS owner_name,
                (SELECT COUNT(*) FROM todo_items i WHERE i.list_id = l.id) AS item_count,
                (SELECT COUNT(*) FROM todo_items i WHERE i.list_id = l.id AND i.is_completed = 1) AS completed_count,
                (SELECT COUNT(*) + 1 FROM list_members lm WHERE lm.list_id = l.id) AS member_count
            FROM todo_lists l
            JOIN users u ON u.id = l.owner_id
            LEFT JOIN list_members access ON access.list_id = l.id AND access.user_id = :user_id
            WHERE l.owner_id = :user_id OR access.user_id IS NOT NULL
            ORDER BY l.updated_at DESC, l.id DESC
        SQL);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(int $ownerId, string $title, string $emoji, string $color): int
    {
        $stmt = db()->prepare('INSERT INTO todo_lists (owner_id, title, emoji, color) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ownerId, trim($title), $emoji, $color]);
        return (int) db()->lastInsertId();
    }

    public function findAccessible(int $id, int $userId): ?array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT l.*, u.name AS owner_name, u.email AS owner_email
            FROM todo_lists l
            JOIN users u ON u.id = l.owner_id
            LEFT JOIN list_members m ON m.list_id = l.id AND m.user_id = ?
            WHERE l.id = ? AND (l.owner_id = ? OR m.user_id IS NOT NULL)
        SQL);
        $stmt->execute([$userId, $id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function items(int $listId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT i.*, creator.name AS creator_name, completer.name AS completer_name
            FROM todo_items i
            JOIN users creator ON creator.id = i.created_by
            LEFT JOIN users completer ON completer.id = i.completed_by
            WHERE i.list_id = ?
            ORDER BY i.is_completed ASC, i.id DESC
        SQL);
        $stmt->execute([$listId]);
        return $stmt->fetchAll();
    }

    public function members(int $listId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT u.id, u.name, u.email, u.last_seen_at, 0 AS is_owner FROM list_members m JOIN users u ON u.id = m.user_id WHERE m.list_id = ?
            UNION ALL
            SELECT u.id, u.name, u.email, u.last_seen_at, 1 AS is_owner FROM todo_lists l JOIN users u ON u.id = l.owner_id WHERE l.id = ?
            ORDER BY is_owner DESC, name ASC
        SQL);
        $stmt->execute([$listId, $listId]);
        return array_map(static function (array $member): array {
            $lastSeen = $member['last_seen_at'] ? strtotime($member['last_seen_at'] . ' UTC') : false;
            $member['is_online'] = $lastSeen !== false && $lastSeen >= time() - self::ONLINE_THRESHOLD_SECONDS;
            unset($member['last_seen_at']);
            return $member;
        }, $stmt->fetchAll());
    }

    public function liveState(int $listId): array
    {
        $items = array_map(static fn(array $item): array => [
            'id' => (int) $item['id'],
            'title' => $item['title'],
            'is_completed' => (bool) $item['is_completed'],
            'creator_name' => $item['creator_name'],
            'completer_name' => $item['completer_name'],
        ], $this->items($listId));
        $members = array_map(static fn(array $member): array => [
            'id' => (int) $member['id'],
            'name' => $member['name'],
            'is_owner' => (bool) $member['is_owner'],
            'is_online' => (bool) $member['is_online'],
        ], $this->members($listId));
        $done = count(array_filter($items, static fn(array $item): bool => $item['is_completed']));
        $total = count($items);
        $state = [
            'items' => $items,
            'members' => $members,
            'stats' => [
                'done' => $done,
                'total' => $total,
                'open' => $total - $done,
                'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            ],
        ];
        $state['revision'] = hash('sha256', json_encode([$items, $members], JSON_THROW_ON_ERROR));
        return $state;
    }

    public function addItem(int $listId, int $userId, string $title): void
    {
        $stmt = db()->prepare('INSERT INTO todo_items (list_id, created_by, title) VALUES (?, ?, ?)');
        $stmt->execute([$listId, $userId, trim($title)]);
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$listId]);
    }

    public function toggleItem(int $itemId, int $listId, int $userId): void
    {
        $stmt = db()->prepare(<<<'SQL'
            UPDATE todo_items SET
                is_completed = CASE is_completed WHEN 1 THEN 0 ELSE 1 END,
                completed_by = CASE is_completed WHEN 1 THEN NULL ELSE ? END,
                completed_at = CASE is_completed WHEN 1 THEN NULL ELSE CURRENT_TIMESTAMP END
            WHERE id = ? AND list_id = ?
        SQL);
        $stmt->execute([$userId, $itemId, $listId]);
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$listId]);
    }

    public function share(int $listId, int $ownerId, int $memberId): void
    {
        $stmt = db()->prepare('INSERT OR IGNORE INTO list_members (list_id, user_id, invited_by) VALUES (?, ?, ?)');
        $stmt->execute([$listId, $memberId, $ownerId]);
    }

    public function delete(int $listId, int $ownerId): void
    {
        db()->prepare('DELETE FROM todo_lists WHERE id = ? AND owner_id = ?')->execute([$listId, $ownerId]);
    }
}
