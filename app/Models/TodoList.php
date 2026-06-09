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
            SELECT i.*, creator.name AS creator_name, completer.name AS completer_name,
                (SELECT COUNT(*) FROM todo_item_comments c WHERE c.item_id = i.id) AS comment_count
            FROM todo_items i
            JOIN users creator ON creator.id = i.created_by
            LEFT JOIN users completer ON completer.id = i.completed_by
            WHERE i.list_id = ?
            ORDER BY i.is_completed ASC, i.id DESC
        SQL);
        $stmt->execute([$listId]);
        $items = $stmt->fetchAll();
        $comments = $this->commentsForList($listId);
        foreach ($items as &$item) {
            $item['comments'] = $comments[(int) $item['id']] ?? [];
        }
        unset($item);
        return $items;
    }

    /** @return array<int, list<array<string, mixed>>> */
    private function commentsForList(int $listId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT c.id, c.item_id, c.body, c.created_at, u.id AS user_id, u.name AS author_name
            FROM todo_item_comments c
            JOIN todo_items i ON i.id = c.item_id
            JOIN users u ON u.id = c.user_id
            WHERE i.list_id = ?
            ORDER BY c.id ASC
        SQL);
        $stmt->execute([$listId]);
        $comments = [];
        foreach ($stmt->fetchAll() as $comment) {
            $comments[(int) $comment['item_id']][] = $comment;
        }
        return $comments;
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
            'priority' => $item['priority'],
            'due_date' => $item['due_date'],
            'has_image' => $item['image_filename'] !== null && $item['image_filename'] !== '',
            'creator_name' => $item['creator_name'],
            'completer_name' => $item['completer_name'],
            'comment_count' => (int) $item['comment_count'],
            'comments' => array_map(static fn(array $comment): array => [
                'id' => (int) $comment['id'],
                'body' => $comment['body'],
                'created_at' => $comment['created_at'],
                'user_id' => (int) $comment['user_id'],
                'author_name' => $comment['author_name'],
            ], $item['comments']),
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

    /** @return list<int> */
    public function participantIdsExcept(int $listId, int $userId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT owner_id AS user_id FROM todo_lists WHERE id = :list_id AND owner_id != :user_id
            UNION
            SELECT user_id FROM list_members WHERE list_id = :list_id AND user_id != :user_id
        SQL);
        $stmt->execute(['list_id' => $listId, 'user_id' => $userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    public function addItem(
        int $listId,
        int $userId,
        string $title,
        string $priority = 'none',
        ?string $dueDate = null,
        ?string $imageFilename = null
    ): int {
        $stmt = db()->prepare(<<<'SQL'
            INSERT INTO todo_items (list_id, created_by, title, priority, due_date, image_filename)
            VALUES (?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$listId, $userId, trim($title), $priority, $dueDate, $imageFilename]);
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$listId]);
        return (int) db()->lastInsertId();
    }

    public function itemImage(int $itemId, int $listId): ?string
    {
        $stmt = db()->prepare('SELECT image_filename FROM todo_items WHERE id = ? AND list_id = ?');
        $stmt->execute([$itemId, $listId]);
        $filename = $stmt->fetchColumn();
        return is_string($filename) && $filename !== '' ? $filename : null;
    }

    public function addComment(int $itemId, int $listId, int $userId, string $body): bool
    {
        $stmt = db()->prepare(<<<'SQL'
            INSERT INTO todo_item_comments (item_id, user_id, body)
            SELECT id, ?, ? FROM todo_items WHERE id = ? AND list_id = ?
        SQL);
        $stmt->execute([$userId, trim($body), $itemId, $listId]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$listId]);
        return true;
    }

    public function toggleItem(int $itemId, int $listId, int $userId): ?array
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
        $item = db()->prepare('SELECT id, title, is_completed FROM todo_items WHERE id = ? AND list_id = ?');
        $item->execute([$itemId, $listId]);
        return $item->fetch() ?: null;
    }

    public function deleteCompletedItem(int $itemId, int $listId): bool
    {
        $stmt = db()->prepare('DELETE FROM todo_items WHERE id = ? AND list_id = ? AND is_completed = 1');
        $stmt->execute([$itemId, $listId]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$listId]);
        return true;
    }

    public function share(int $listId, int $ownerId, int $memberId): void
    {
        $stmt = db()->prepare('INSERT OR IGNORE INTO list_members (list_id, user_id, invited_by) VALUES (?, ?, ?)');
        $stmt->execute([$listId, $memberId, $ownerId]);
    }

    /** @return list<string> */
    public function imageFilenames(int $listId): array
    {
        $stmt = db()->prepare("SELECT image_filename FROM todo_items WHERE list_id = ? AND image_filename IS NOT NULL AND image_filename != ''");
        $stmt->execute([$listId]);
        return array_values(array_filter(array_column($stmt->fetchAll(), 'image_filename'), 'is_string'));
    }

    public function delete(int $listId, int $ownerId): bool
    {
        $stmt = db()->prepare('DELETE FROM todo_lists WHERE id = ? AND owner_id = ?');
        $stmt->execute([$listId, $ownerId]);
        return $stmt->rowCount() > 0;
    }
}
